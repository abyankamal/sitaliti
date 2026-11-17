<?php

namespace App\Services;

use App\Contracts\BudgetReservationServiceInterface;
use App\DTOs\BudgetContextData;
use App\Exceptions\PaguOverlimitException;
use App\Models\AnggaranKasBulanan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BudgetReservationService implements BudgetReservationServiceInterface
{
    /**
     * Mengambil alokasi kuota RAK bulanan beserta sisa akumulasi carry-over untuk rekening tertentu.
     *
     * @return array<string, mixed>
     *
     * @throws PaguOverlimitException
     */
    public function getPopupAllocationData(string $anggaranRekeningId, int $bulanDipilih, ?int $activeMonth = null): array
    {
        if ($bulanDipilih < 1 || $bulanDipilih > 12) {
            throw new InvalidArgumentException("Bulan RAK tidak valid: {$bulanDipilih}.");
        }

        $rows = AnggaranKasBulanan::where('anggaran_rekening_id', $anggaranRekeningId)
            ->orderBy('bulan', 'asc')
            ->get()
            ->keyBy('bulan');

        $targetRow = $rows->get($bulanDipilih);
        if (! $targetRow) {
            throw new PaguOverlimitException(
                "Alokasi RAK bulan {$bulanDipilih} untuk rekening {$anggaranRekeningId} belum diimpor."
            );
        }

        $systemMonth = $activeMonth ?? (int) date('n');
        $isFutureMonth = $bulanDipilih > $systemMonth;
        $context = $this->calculateMonthlyBudgetContext($rows, $bulanDipilih);

        if ($isFutureMonth) {
            $statusLock = 'LOCKED_FUTURE';
            $sisaDapatDigunakan = 0.0;
        } else {
            $statusLock = 'OPEN';
            $sisaDapatDigunakan = $context['sisa_dapat_digunakan'];
        }

        $contextDTO = new BudgetContextData(
            rekeningId: $anggaranRekeningId,
            selectedMonth: $bulanDipilih,
            activeSystemMonth: $systemMonth,
            isFutureMonth: $isFutureMonth,
            paguMurniBulan: $context['pagu_murni_bulan'],
            carryOverLalu: $context['carry_over_lalu'],
            totalTersedia: $context['total_tersedia'],
            terkunciKomitmen: $context['terkunci_komitmen'],
            sisaDapatDigunakan: $sisaDapatDigunakan,
            statusLock: $statusLock
        );

        return $contextDTO->toArray();
    }

    /**
     * Mengunci pagu kuitansi saat disubmit ke PPTK menggunakan transaksi atomik dan pessimistic row locking.
     *
     * @throws PaguOverlimitException
     * @throws InvalidArgumentException
     */
    public function reserveBudget(string $anggaranRekeningId, int $bulan, float $nominalBruto, ?int $activeMonth = null): void
    {
        if ($nominalBruto <= 0.0) {
            throw new InvalidArgumentException("Nominal reservasi anggaran harus lebih besar dari 0. Diberikan: {$nominalBruto}");
        }

        $systemMonth = $activeMonth ?? (int) date('n');
        if ($bulan > $systemMonth) {
            throw new PaguOverlimitException("Tidak dapat mereservasi pagu untuk bulan masa depan (Bulan {$bulan} > Bulan Aktif {$systemMonth}).");
        }

        DB::transaction(function () use ($anggaranRekeningId, $bulan, $nominalBruto) {
            // Kunci baris dengan urutan deterministik ORDER BY bulan ASC untuk mencegah race condition & deadlock
            $rows = AnggaranKasBulanan::where('anggaran_rekening_id', $anggaranRekeningId)
                ->orderBy('bulan', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('bulan');

            $targetRow = $rows->get($bulan);
            if (! $targetRow) {
                throw new PaguOverlimitException("Alokasi RAK bulan {$bulan} untuk rekening {$anggaranRekeningId} belum diimpor.");
            }

            $context = $this->calculateMonthlyBudgetContext($rows, $bulan);
            $nominalCents = (int) round($nominalBruto * 100);

            if ($nominalCents > $context['sisa_dapat_digunakan_cents']) {
                throw new PaguOverlimitException(
                    sprintf(
                        'Alokasi pagu tidak mencukupi. Dibutuhkan: Rp %s, Tersedia: Rp %s.',
                        number_format($nominalBruto, 2, ',', '.'),
                        number_format($context['sisa_dapat_digunakan'], 2, ',', '.')
                    )
                );
            }

            $targetRow->terkunci_bulan = (float) $targetRow->terkunci_bulan + $nominalBruto;
            $targetRow->save();
        });
    }

    /**
     * Melepas kuncian pagu jika kuitansi ditolak revisi mayor atau dibatalkan oleh operator.
     */
    public function releaseBudget(string $anggaranRekeningId, int $bulan, float $nominalBruto): void
    {
        DB::transaction(function () use ($anggaranRekeningId, $bulan, $nominalBruto) {
            $targetRow = AnggaranKasBulanan::where('anggaran_rekening_id', $anggaranRekeningId)
                ->where('bulan', $bulan)
                ->lockForUpdate()
                ->first();

            if ($targetRow) {
                $targetRow->terkunci_bulan = max(0.0, (float) $targetRow->terkunci_bulan - $nominalBruto);
                $targetRow->save();
            }
        });
    }

    /**
     * Membukukan pagu yang terkunci menjadi realisasi belanja definitif (saat SP2D SIPD-RI dicatat).
     */
    public function settleBudget(string $anggaranRekeningId, int $bulan, float $nominalRealisasi): void
    {
        DB::transaction(function () use ($anggaranRekeningId, $bulan, $nominalRealisasi) {
            $targetRow = AnggaranKasBulanan::where('anggaran_rekening_id', $anggaranRekeningId)
                ->where('bulan', $bulan)
                ->lockForUpdate()
                ->first();

            if ($targetRow) {
                $targetRow->terkunci_bulan = max(0.0, (float) $targetRow->terkunci_bulan - $nominalRealisasi);
                $targetRow->realisasi_bulan = (float) $targetRow->realisasi_bulan + $nominalRealisasi;
                $targetRow->save();
            }
        });
    }

    /**
     * Menghitung konteks pagu bulanan secara kumulatif (dalam sen integer)
     * termasuk pemotongan carry-over bila bulan berikutnya telah memakai sisa periode lalu.
     *
     * @param  Collection<int, AnggaranKasBulanan>  $rows
     * @return array{
     *     pagu_murni_bulan: float,
     *     carry_over_lalu: float,
     *     total_tersedia: float,
     *     terkunci_komitmen: float,
     *     sisa_dapat_digunakan: float,
     *     sisa_dapat_digunakan_cents: int
     * }
     */
    private function calculateMonthlyBudgetContext($rows, int $bulanDipilih): array
    {
        $targetRow = $rows->get($bulanDipilih);

        $paguMurniCents = (int) round((float) $targetRow->alokasi_pagu * 100);
        $realisasiCents = (int) round((float) $targetRow->realisasi_bulan * 100);
        $terkunciCents = (int) round((float) $targetRow->terkunci_bulan * 100);

        $cumulativePrevCents = 0;
        for ($m = 1; $m < $bulanDipilih; $m++) {
            if ($prevRow = $rows->get($m)) {
                $cumulativePrevCents += (int) round((float) $prevRow->alokasi_pagu * 100)
                    - (int) round((float) $prevRow->realisasi_bulan * 100)
                    - (int) round((float) $prevRow->terkunci_bulan * 100);
            }
        }

        $futureCumulativeCents = 0;
        $minFutureCumulativeCents = 0;
        for ($k = $bulanDipilih + 1; $k <= 12; $k++) {
            if ($futureRow = $rows->get($k)) {
                $futureCumulativeCents += (int) round((float) $futureRow->alokasi_pagu * 100)
                    - (int) round((float) $futureRow->realisasi_bulan * 100)
                    - (int) round((float) $futureRow->terkunci_bulan * 100);

                if ($futureCumulativeCents < $minFutureCumulativeCents) {
                    $minFutureCumulativeCents = $futureCumulativeCents;
                }
            }
        }

        $effectiveCarryOverCents = $cumulativePrevCents + $minFutureCumulativeCents;
        $totalTersediaCents = $paguMurniCents + $effectiveCarryOverCents;
        $sisaDapatDigunakanCents = max(0, $totalTersediaCents - $realisasiCents - $terkunciCents);

        return [
            'pagu_murni_bulan' => round($paguMurniCents / 100, 2),
            'carry_over_lalu' => round($effectiveCarryOverCents / 100, 2),
            'total_tersedia' => round($totalTersediaCents / 100, 2),
            'terkunci_komitmen' => round($terkunciCents / 100, 2),
            'sisa_dapat_digunakan' => round($sisaDapatDigunakanCents / 100, 2),
            'sisa_dapat_digunakan_cents' => $sisaDapatDigunakanCents,
        ];
    }
}
