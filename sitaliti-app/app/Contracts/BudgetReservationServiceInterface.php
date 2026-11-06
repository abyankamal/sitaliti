<?php

namespace App\Contracts;

interface BudgetReservationServiceInterface
{
    /**
     * Mengambil alokasi kuota RAK bulanan beserta sisa akumulasi carry-over untuk rekening tertentu.
     * Sesuai kebutuhan RAKSelectorModal.vue & budgetService.getRakContext().
     *
     * @param string $anggaranRekeningId ID Rekening Belanja
     * @param int $bulanDipilih Bulan yang dipilih pengguna (1 s.d. 12)
     * @param int|null $activeMonth Override bulan aktif sistem (untuk testing / penutupan buku)
     * @return array{
     *     rekening_id: string,
     *     selected_month: int,
     *     active_system_month: int,
     *     is_future_month: bool,
     *     pagu_murni_bulan: float,
     *     carry_over_lalu: float,
     *     total_tersedia: float,
     *     terkunci_komitmen: float,
     *     sisa_dapat_digunakan: float,
     *     status_lock: string
     * }
     */
    public function getPopupAllocationData(string $anggaranRekeningId, int $bulanDipilih, ?int $activeMonth = null): array;

    /**
     * Mengunci pagu kuitansi saat disubmit ke PPTK menggunakan transaksi atomic pessimistic lock (lockForUpdate).
     * Mengevaluasi proteksi agar sisa pagu bulan berjalan dan bulan mendatang tidak defisit.
     *
     * @param string $anggaranRekeningId
     * @param int $bulan
     * @param float $nominalBruto
     * @param int|null $activeMonth
     * @throws \App\Exceptions\PaguOverlimitException Jika saldo tidak mencukupi atau melanggar jadwal kas
     * @throws \InvalidArgumentException Jika nominal <= 0
     * @return void
     */
    public function reserveBudget(string $anggaranRekeningId, int $bulan, float $nominalBruto, ?int $activeMonth = null): void;

    /**
     * Melepas kuncian pagu jika kuitansi ditolak revisi mayor atau dibatalkan oleh operator.
     *
     * @param string $anggaranRekeningId
     * @param int $bulan
     * @param float $nominalBruto
     * @return void
     */
    public function releaseBudget(string $anggaranRekeningId, int $bulan, float $nominalBruto): void;

    /**
     * Membukukan pagu yang terkunci menjadi realisasi belanja definitif (saat SP2D SIPD-RI dicatat).
     *
     * @param string $anggaranRekeningId
     * @param int $bulan
     * @param float $nominalRealisasi
     * @return void
     */
    public function settleBudget(string $anggaranRekeningId, int $bulan, float $nominalRealisasi): void;
}
