<?php

namespace App\Contracts;

interface RakImporterServiceInterface
{
    /**
     * Membaca dan mengimpor data hierarki RAK dari file XLSX menggunakan streaming parser hemat memori.
     * Mengisi tabel: master_unit_kerja, anggaran_rekening, dan anggaran_kas_bulanan (12 bulan).
     *
     * @param  string  $filePath  Path absolut file Excel RAK (.xlsx)
     * @param  int  $tahunAnggaran  Tahun anggaran RAK (default 2026)
     * @return array{
     *     total_unit: int,
     *     total_subkegiatan: int,
     *     total_rekening: int,
     *     total_pagu_tahunan: float,
     *     status: string,
     *     errors: array<int, string>
     * }
     */
    public function importFromFile(string $filePath, int $tahunAnggaran = 2026): array;

    /**
     * Memvalidasi format kolom dan integritas data file Excel RAK sebelum dieksekusi.
     */
    public function validateFileFormat(string $filePath): bool;
}
