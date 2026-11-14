<?php

namespace App\Services;

use App\Contracts\BudgetReservationServiceInterface;
use App\DTOs\BudgetContextData;
use App\Exceptions\PaguOverlimitException;
use App\Models\AnggaranKasBulanan;
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

        $paguMurniBulan = (float) $targetRow->alokasi_pagu;
        $terkunciKomitmen = (float) $targetRow->terkunci_bulan;
        $realisasiBulan = (float) $targetRow->realisasi_bulan;

        // 1. Akumulasi Carry-Over dari bulan 1 s.d. (bulanDipilih - 1)
        $carryOverLalu = 0.0;
        for ($m = 1; $m < $bulanDipilih; $m++) {
            if ($prevRow = $rows->get($m)) {
                $sisaBulanLalu = (float) $prevRow->alokasi_pagu
                    - (float) $prevRow->realisasi_bulan
                    - (float) $prevRow->terkunci_bulan;

                if ($sisaBulanLalu > 0) {
                    $carryOverLalu += $sisaBulanLalu;
                }
            }
        }

        // 2. Evaluasi status kunci bulan masa depan
        if ($isFutureMonth) {
            $statusLock = 'LOCKED_FUTURE';
            $sisaDapatDigunakan = 0.0;
        } else {
            $statusLock = 'OPEN';
            $totalTersedia = $paguMurniBulan + $carryOverLalu - $realisasiBulan;
            $sisaDapatDigunakan = max(0.0, $totalTersedia - $terkunciKomitmen);
        }

        $contextDTO = new BudgetContextData(
            rekeningId: $anggaranRekeningId,
            selectedMonth: $bulanDipilih,
            activeSystemMonth: $systemMonth,
            isFutureMonth: $isFutureMonth,
            paguMurniBulan: $paguMurniBulan,
            carryOverLalu: $carryOverLalu,
            totalTersedia: $paguMurniBulan + $carryOverLalu,
            terkunciKomitmen: $terkunciKomitmen,
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

            $carryOverLalu = 0.0;
            for ($m = 1; $m < $bulan; $m++) {
                if ($prevRow = $rows->get($m)) {
                    $sisaBulanLalu = (float) $prevRow->alokasi_pagu
                        - (float) $prevRow->realisasi_bulan
                        - (float) $prevRow->terkunci_bulan;

                    if ($sisaBulanLalu > 0) {
                        $carryOverLalu += $sisaBulanLalu;
                    }
                }
            }

            $totalTersedia = (float) $targetRow->alokasi_pagu + $carryOverLalu - (float) $targetRow->realisasi_bulan;
            $sisaDapatDigunakan = max(0.0, $totalTersedia - (float) $targetRow->terkunci_bulan);

            if ($nominalBruto > $sisaDapatDigunakan) {
                throw new PaguOverlimitException(
                    sprintf(
                        'Alokasi pagu tidak mencukupi. Dibutuhkan: Rp %s, Tersedia: Rp %s.',
                        number_format($nominalBruto, 2, ',', '.'),
                        number_format($sisaDapatDigunakan, 2, ',', '.')
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
}
