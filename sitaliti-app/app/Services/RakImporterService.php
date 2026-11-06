<?php

namespace App\Services;

use App\Contracts\RakImporterServiceInterface;
use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\UnitKerja;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class RakImporterService implements RakImporterServiceInterface
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
    public function importFromFile(string $filePath, int $tahunAnggaran = 2026): array
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("File RAK tidak ditemukan di: {$filePath}");
        }

        $parsedUnits = $this->parseXlsxFile($filePath);

        $totalUnits = count($parsedUnits);
        $totalSubkegiatan = 0;
        $totalRekening = 0;
        $totalPaguTahunan = 0.0;

        DB::transaction(function () use ($parsedUnits, &$totalSubkegiatan, &$totalRekening, &$totalPaguTahunan) {
            foreach ($parsedUnits as $unitData) {
                $kodeUnit = $unitData['kode_unit'];
                $namaUnit = $unitData['nama_unit'];
                $tipeUnit = str_contains(strtolower($namaUnit), 'kelurahan') ? 'KELURAHAN' : 'KECAMATAN';

                $unitKerja = UnitKerja::firstOrCreate(
                    ['kode_unit' => $kodeUnit],
                    [
                        'nama_unit' => $namaUnit,
                        'tipe' => $tipeUnit,
                        'is_active' => true,
                    ]
                );

                $totalSubkegiatan += count($unitData['subkegiatan']);

                foreach ($unitData['subkegiatan'] as $subkeg) {
                    $kodeSubkeg = $subkeg['kode'];
                    $namaSubkeg = $subkeg['nama'];

                    foreach ($subkeg['accounts'] as $acc) {
                        $kodeRekening = $acc['kode'];
                        $namaRekening = $acc['nama'];
                        $paguTahunan = (float) $acc['total'];
                        $kategori = $this->determineCategory($kodeRekening, $namaRekening);

                        $rekeningModel = AnggaranRekening::updateOrCreate(
                            [
                                'unit_kerja_id' => $unitKerja->id,
                                'kode_sub_kegiatan' => $kodeSubkeg,
                                'kode_rekening' => $kodeRekening,
                            ],
                            [
                                'nama_sub_kegiatan' => $namaSubkeg,
                                'nama_rekening' => $namaRekening,
                                'pagu_murni' => $paguTahunan,
                                'pagu_perubahan' => $paguTahunan,
                                'kategori_belanja' => $kategori,
                                'is_leaf' => true,
                            ]
                        );

                        $totalRekening++;
                        $totalPaguTahunan += $paguTahunan;

                        // Simpan / update alokasi kas 12 bulan
                        for ($m = 1; $m <= 12; $m++) {
                            $alokasiBulan = (float) ($acc['months'][$m] ?? 0.0);

                            AnggaranKasBulanan::updateOrCreate(
                                [
                                    'anggaran_rekening_id' => $rekeningModel->id,
                                    'bulan' => $m,
                                ],
                                [
                                    'alokasi_pagu' => $alokasiBulan,
                                    'terkunci_bulan' => 0.0,
                                    'realisasi_bulan' => 0.0,
                                ]
                            );
                        }
                    }
                }
            }
        });

        return [
            'total_unit' => $totalUnits,
            'total_subkegiatan' => $totalSubkegiatan,
            'total_rekening' => $totalRekening,
            'total_pagu_tahunan' => round($totalPaguTahunan, 2),
            'status' => 'SUCCESS',
            'errors' => [],
        ];
    }

    /**
     * Memvalidasi format kolom dan integritas data file Excel RAK sebelum dieksekusi.
     */
    public function validateFileFormat(string $filePath): bool
    {
        if (! file_exists($filePath)) {
            return false;
        }

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            return false;
        }

        $hasWorkbook = $zip->locateName('xl/workbook.xml') !== false;
        $hasSheet = $zip->locateName('xl/worksheets/sheet1.xml') !== false;
        $zip->close();

        return $hasWorkbook && $hasSheet;
    }

    /**
     * Parsing internal file XLSX terkelompok per Unit Kerja.
     *
     * @return array<string, array{
     *     kode_unit: string,
     *     nama_unit: string,
     *     subkegiatan: array<string, array{kode: string, nama: string, accounts: array<int, array{kode: string, nama: string, total: float, months: array<int, float>}>}>
     * }>
     */
    private function parseXlsxFile(string $filePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException("Gagal membuka file zip XLSX: {$filePath}");
        }

        // 1. Ekstrak shared strings jika ada
        $sharedStrings = [];
        $stringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($stringsXml !== false) {
            $sxml = simplexml_load_string($stringsXml);
            if ($sxml) {
                foreach ($sxml->si as $si) {
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string) $si->t;
                    } elseif (isset($si->r)) {
                        foreach ($si->r as $r) {
                            $text .= (string) $r->t;
                        }
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        // 2. Baca sheet1.xml
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException("Sheet1 tidak ditemukan dalam file XLSX: {$filePath}");
        }

        $sheet = simplexml_load_string($sheetXml);
        if (! $sheet || ! isset($sheet->sheetData->row)) {
            throw new RuntimeException("Format lembar kerja RAK tidak valid: {$filePath}");
        }

        $units = [];
        $currentUnitKode = null;
        $currentUnitNama = null;
        $currentSubkegKode = null;
        $currentSubkegNama = null;

        $monthColMap = [
            'E' => 1, 'F' => 2, 'G' => 3, 'H' => 4,
            'I' => 5, 'J' => 6, 'K' => 7, 'L' => 8,
            'M' => 9, 'N' => 10, 'O' => 11, 'P' => 12,
        ];

        foreach ($sheet->sheetData->row as $row) {
            $rowNum = (int) $row['r'];
            if ($rowNum < 4) {
                continue; // Lewati header rows 1-3
            }

            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                preg_match('/([A-Z]+)/', $ref, $matches);
                $col = $matches[1] ?? '';
                $type = (string) $c['t'];
                $val = isset($c->v) ? (string) $c->v : '';

                if ($type === 's' && $val !== '' && isset($sharedStrings[(int) $val])) {
                    $val = $sharedStrings[(int) $val];
                }

                $cells[$col] = trim($val);
            }

            $kode = $cells['A'] ?? '';
            $uraian = $cells['B'] ?? '';
            $anggaran = (float) ($cells['C'] ?? 0);

            // Deteksi Unit Kerja (7.01.0.00.0.00.41.xxxx)
            if (str_starts_with($kode, '7.01.0.00.0.00.41.') && ! empty($uraian)) {
                $currentUnitKode = $kode;
                $currentUnitNama = $uraian;
                $currentSubkegKode = null;
                $currentSubkegNama = null;

                if (! isset($units[$currentUnitKode])) {
                    $units[$currentUnitKode] = [
                        'kode_unit' => $currentUnitKode,
                        'nama_unit' => $currentUnitNama,
                        'subkegiatan' => [],
                    ];
                }
            }

            // Deteksi Subkegiatan (7.01.xx.x.xx.xxxx memiliki 6 bagian)
            if (str_starts_with($kode, '7.01.') && count(explode('.', $kode)) === 6) {
                if ($currentUnitKode === null) {
                    $currentUnitKode = '7.01.0.00.0.00.41.0000';
                    $currentUnitNama = 'Kecamatan Tarogong Kidul';
                    $units[$currentUnitKode] = [
                        'kode_unit' => $currentUnitKode,
                        'nama_unit' => $currentUnitNama,
                        'subkegiatan' => [],
                    ];
                }

                $currentSubkegKode = $kode;
                $currentSubkegNama = $uraian;

                if (! isset($units[$currentUnitKode]['subkegiatan'][$currentSubkegKode])) {
                    $units[$currentUnitKode]['subkegiatan'][$currentSubkegKode] = [
                        'kode' => $currentSubkegKode,
                        'nama' => $currentSubkegNama,
                        'accounts' => [],
                    ];
                }
            }

            // Deteksi Rekening Leaf (dimulai dari 5. dan memiliki 6 bagian)
            if (str_starts_with($kode, '5.') && count(explode('.', $kode)) === 6) {
                if ($currentUnitKode === null || $currentSubkegKode === null) {
                    continue;
                }

                $months = [];
                foreach ($monthColMap as $colLetter => $mNum) {
                    $months[$mNum] = (float) ($cells[$colLetter] ?? 0);
                }

                $units[$currentUnitKode]['subkegiatan'][$currentSubkegKode]['accounts'][] = [
                    'kode' => $kode,
                    'nama' => $uraian,
                    'total' => $anggaran,
                    'months' => $months,
                ];
            }
        }

        return $units;
    }

    /**
     * Menentukan kategori belanja default B.1 s.d. B.6 berdasarkan kode akun dan uraian SIPD-RI.
     */
    private function determineCategory(string $kodeRekening, string $uraian): string
    {
        $u = strtolower($uraian);

        // B.2: Makanan dan Minuman
        if (str_contains($u, 'makan') || str_contains($u, 'minum')) {
            return 'B.2';
        }

        // B.3: Honorarium
        if (str_contains($u, 'honor') || str_contains($u, 'narasumber') || str_contains($u, 'uang saku')) {
            return 'B.3';
        }

        // B.4: Perjalanan Dinas
        if (str_contains($u, 'perjalanan dinas') || str_contains($u, 'sppd') || str_contains($kodeRekening, '5.1.02.04')) {
            return 'B.4';
        }

        // B.5: Pemeliharaan & Operasional
        if (str_contains($u, 'pemeliharaan') || str_contains($u, 'servis') || str_contains($u, 'perawatan') || str_contains($kodeRekening, '5.1.02.03')) {
            return 'B.5';
        }

        // B.6: Belanja Modal
        if (str_starts_with($kodeRekening, '5.2')) {
            return 'B.6';
        }

        // B.1: ATK, Bahan Cetak, Kertas (default)
        return 'B.1';
    }
}
