<?php

namespace App\Services;

use App\Contracts\BudgetReservationServiceInterface;
use App\DTOs\BudgetContextData;
use App\Exceptions\PaguOverlimitException;
use InvalidArgumentException;

class BudgetReservationService implements BudgetReservationServiceInterface
{
    /**
     * State penyimpanan alokasi pagu 12 bulan per rekening (in-memory ledger).
     * Saat migrasi database master RAK siap di Phase 3, state ini dapat membaca langsung dari tabel anggaran_kas_bulanan.
     *
     * @var array<string, array<int, array{alokasi_pagu: float, terkunci_komitmen: float, realisasi_bulan: float}>>
     */
    private array $ledger = [];

    /**
     * Default master alokasi kas 12 bulan jika rekening baru diakses pertama kali.
     * Menggunakan template RAK Belanja ATK / Operasional kantor.
     *
     * @var array<int, float>
     */
    private array $defaultMonthlyAllocation = [
        1 => 10000000.0, // Jan
        2 => 15000000.0, // Feb
        3 => 15000000.0, // Mar
        4 => 12000000.0, // Apr
        5 => 12000000.0, // Mei
        6 => 18000000.0, // Jun
        7 => 10000000.0, // Jul
        8 => 12000000.0, // Agu
        9 => 15000000.0, // Sep
        10 => 12000000.0, // Okt
        11 => 15000000.0, // Nov
        12 => 20000000.0, // Des
    ];

    public function __construct()
    {
        //
    }

    /**
     * Memastikan state kas 12 bulan untuk rekening terinisialisasi.
     *
     * @param string $anggaranRekeningId
     * @return void
     */
    private function ensureRekeningInitialized(string $anggaranRekeningId): void
    {
        if (!isset($this->ledger[$anggaranRekeningId])) {
            $this->ledger[$anggaranRekeningId] = [];
            foreach ($this->defaultMonthlyAllocation as $bulan => $alokasi) {
                $this->ledger[$anggaranRekeningId][$bulan] = [
                    'alokasi_pagu' => $alokasi,
                    'terkunci_komitmen' => 0.0,
                    'realisasi_bulan' => ($bulan === 1) ? 6000000.0 : 0.0, // Default bulan 1 ada realisasi lalu
                ];
            }
        }
    }

    /**
     * Mengambil alokasi kuota RAK bulanan beserta sisa akumulasi carry-over untuk rekening tertentu.
     *
     * @param string $anggaranRekeningId
     * @param int $bulanDipilih
     * @param int|null $activeMonth
     * @return array<string, mixed>
     */
    public function getPopupAllocationData(string $anggaranRekeningId, int $bulanDipilih, ?int $activeMonth = null): array
    {
        $this->ensureRekeningInitialized($anggaranRekeningId);

        $systemMonth = $activeMonth ?? (int) date('n');
        $isFutureMonth = $bulanDipilih > $systemMonth;

        $rekeningKas = $this->ledger[$anggaranRekeningId];
        $paguMurniBulan = $rekeningKas[$bulanDipilih]['alokasi_pagu'] ?? 0.0;
        $terkunciKomitmen = $rekeningKas[$bulanDipilih]['terkunci_komitmen'] ?? 0.0;
        $realisasiBulan = $rekeningKas[$bulanDipilih]['realisasi_bulan'] ?? 0.0;

        // 1. Akumulasi Carry-Over dari bulan 1 s.d. (bulanDipilih - 1)
        // Carry over = sum(alokasi_pagu - realisasi_bulan - terkunci_komitmen) untuk bulan yang telah lampau
        $carryOverLalu = 0.0;
        for ($m = 1; $m < $bulanDipilih; $m++) {
            if (isset($rekeningKas[$m])) {
                $sisaBulanLalu = $rekeningKas[$m]['alokasi_pagu'] - $rekeningKas[$m]['realisasi_bulan'] - $rekeningKas[$m]['terkunci_komitmen'];
                if ($sisaBulanLalu > 0) {
                    $carryOverLalu += $sisaBulanLalu;
                }
            }
        }

        // 2. Evaluasi status kunci bulan masa depan
        if ($isFutureMonth) {
            $statusLock = 'LOCKED_FUTURE';
            $totalTersedia = 0.0;
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
     * Mengunci pagu kuitansi saat disubmit ke PPTK.
     *
     * @param string $anggaranRekeningId
     * @param int $bulan
     * @param float $nominalBruto
     * @param int|null $activeMonth
     * @throws PaguOverlimitException
     * @throws InvalidArgumentException
     * @return void
     */
    public function reserveBudget(string $anggaranRekeningId, int $bulan, float $nominalBruto, ?int $activeMonth = null): void
    {
        // 1. Proteksi nilai negatif atau nol
        if ($nominalBruto <= 0.0) {
            throw new InvalidArgumentException("Nominal reservasi anggaran harus lebih besar dari 0. Diberikan: {$nominalBruto}");
        }

        $this->ensureRekeningInitialized($anggaranRekeningId);
        $systemMonth = $activeMonth ?? (int) date('n');

        // 2. Proteksi bulan masa depan
        if ($bulan > $systemMonth) {
            throw new PaguOverlimitException("Tidak dapat mereservasi pagu untuk bulan masa depan (Bulan {$bulan} > Bulan Aktif {$systemMonth}).");
        }

        // 3. Evaluasi sisa pagu tersedia
        $currentContext = $this->getPopupAllocationData($anggaranRekeningId, $bulan, $activeMonth);

        if ($nominalBruto > $currentContext['sisa_dapat_digunakan']) {
            throw new PaguOverlimitException(
                sprintf(
                    "Alokasi pagu tidak mencukupi. Dibutuhkan: Rp %s, Tersedia: Rp %s.",
                    number_format($nominalBruto, 2, ',', '.'),
                    number_format($currentContext['sisa_dapat_digunakan'], 2, ',', '.')
                )
            );
        }

        // 4. Lakukan penguncian pagu
        $this->ledger[$anggaranRekeningId][$bulan]['terkunci_komitmen'] += $nominalBruto;
    }

    /**
     * Melepas kuncian pagu jika kuitansi ditolak revisi mayor atau dibatalkan oleh operator.
     * Dilengkapi proteksi underflow (tidak pernah negatif).
     *
     * @param string $anggaranRekeningId
     * @param int $bulan
     * @param float $nominalBruto
     * @return void
     */
    public function releaseBudget(string $anggaranRekeningId, int $bulan, float $nominalBruto): void
    {
        $this->ensureRekeningInitialized($anggaranRekeningId);

        $currentLocked = $this->ledger[$anggaranRekeningId][$bulan]['terkunci_komitmen'] ?? 0.0;
        $this->ledger[$anggaranRekeningId][$bulan]['terkunci_komitmen'] = max(0.0, $currentLocked - $nominalBruto);
    }

    /**
     * Membukukan pagu yang terkunci menjadi realisasi belanja definitif (saat SP2D SIPD-RI dicatat).
     *
     * @param string $anggaranRekeningId
     * @param int $bulan
     * @param float $nominalRealisasi
     * @return void
     */
    public function settleBudget(string $anggaranRekeningId, int $bulan, float $nominalRealisasi): void
    {
        $this->ensureRekeningInitialized($anggaranRekeningId);

        // Lepas kuncian komitmen
        $currentLocked = $this->ledger[$anggaranRekeningId][$bulan]['terkunci_komitmen'] ?? 0.0;
        $this->ledger[$anggaranRekeningId][$bulan]['terkunci_komitmen'] = max(0.0, $currentLocked - $nominalRealisasi);

        // Tambah realisasi definitif
        $this->ledger[$anggaranRekeningId][$bulan]['realisasi_bulan'] += $nominalRealisasi;
    }
}

