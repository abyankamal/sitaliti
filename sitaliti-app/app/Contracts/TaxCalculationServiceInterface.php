<?php

namespace App\Contracts;

interface TaxCalculationServiceInterface
{
    /**
     * Menghitung simulasi potongan pajak otomatis berdasarkan kategori belanja, nominal bruto, dan opsi NPWP/PPh.
     * Sesuai ketentuan UU HPP, UU HKPD (PBJT 10%), dan PMK 59/2022.
     *
     * @param  string  $kategoriChecklist  Contoh: 'B1_ATK', 'B2_MAMIN', 'B3_HONOR', 'B4_PERJADIN', 'B5_PEMELIHARAAN', 'B6_MODAL'
     * @param  float  $nilaiBruto  Nilai kuitansi bruto
     * @param array{
     *     has_npwp?: bool,
     *     golongan_asn?: string|null,
     *     is_rekanan_badan?: bool,
     *     is_sewa_kendaraan?: bool,
     *     is_pkp?: bool,
     *     pph21_custom_rate?: float|null,
     *     tahun_anggaran?: int
     * } $options
     * @return array{
     *     nilai_bruto: float,
     *     dpp: float,
     *     ppn: float,
     *     pbjt_mamin: float,
     *     pph21: float,
     *     pph22: float,
     *     pph23: float,
     *     bea_meterai: float,
     *     total_pajak: float,
     *     nilai_neto: float,
     *     rincian_pajak: array<int, array{
     *         kode_pajak: string,
     *         nama_pajak: string,
     *         tarif: float,
     *         tipe_tarif: string,
     *         nominal: float
     *     }>
     * }
     */
    public function calculateTax(string $kategoriChecklist, float $nilaiBruto, array $options = []): array;

    /**
     * Memvalidasi kepatuhan pajak:
     * - Keseimbangan matematis: Bruto - Total Pajak = Neto
     * - Wajib meterai sesuai ambang batas & nominal tahun anggaran terkait
     * - Pemisahan mutlak: PPN 11% dan PBJT 10% tidak boleh dipungut bersamaan
     */
    public function validateTaxCompliance(float $nilaiBruto, array $taxDetails, ?int $tahun = null): bool;
}
