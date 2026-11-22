<?php

namespace App\Services;

use App\Models\Kuitansi;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptExportService
{
    public const HEADINGS = [
        'Nomor Kuitansi',
        'Tanggal Transaksi',
        'Unit Kerja',
        'Kode Rekening',
        'Nama Rekening',
        'Uraian Belanja',
        'Nominal Bruto',
        'DPP',
        'PPN',
        'PPh 21',
        'PPh 22',
        'PPh 23',
        'PBJT',
        'Bea Meterai',
        'Nominal Neto',
        'Nama Rekanan',
        'NPWP Rekanan',
        'Status',
        'Nomor SP2D',
    ];

    /**
     * Indeks kolom numerik (0-based) pada HEADINGS.
     *
     * @var list<int>
     */
    private const NUMERIC_COLUMN_INDICES = [6, 7, 8, 9, 10, 11, 12, 13, 14];

    private const PDF_MAX_ROWS = 1000;

    public function __construct(
        protected ReceiptQueryService $queryService
    ) {}

    /**
     * Alias kompatibilitas untuk ekspor CSV.
     */
    public function streamExport(User $user, array $filters = []): StreamedResponse
    {
        return $this->streamCsvExport($user, $filters);
    }

    /**
     * Mengalirkan file CSV rekapitulasi data kuitansi dan perpajakan menggunakan lazy collection.
     */
    public function streamCsvExport(User $user, array $filters = []): StreamedResponse
    {
        $filename = 'rekapitulasi-kuitansi-'.date('Ymd-His').'.csv';
        $query = $this->buildExportQuery($user, $filters);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Tulis UTF-8 Byte Order Mark (BOM) agar Excel mengenali encoding secara presisi
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, self::HEADINGS);

            foreach ($query->lazy(500) as $kuitansi) {
                $row = $this->extractReceiptRow($kuitansi);
                $csvRow = [];

                foreach ($row as $idx => $value) {
                    if (in_array($idx, self::NUMERIC_COLUMN_INDICES, true)) {
                        $csvRow[] = number_format((float) $value, 2, '.', '');
                    } else {
                        $csvRow[] = $this->sanitizeSpreadsheetCell($value);
                    }
                }

                fputcsv($handle, $csvRow);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Mengalirkan file Excel (.xlsx) asli dengan styling, proteksi formula injection, dan baris TOTAL.
     */
    public function streamExcelExport(User $user, array $filters = []): StreamedResponse
    {
        $filename = 'rekapitulasi-kuitansi-'.date('Ymd-His').'.xlsx';
        $query = $this->buildExportQuery($user, $filters);

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query, $filters) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Rekapitulasi Kuitansi');

            $lastColLetter = Coordinate::stringFromColumnIndex(count(self::HEADINGS));

            // Baris 1-2: Judul & Ringkasan Filter Aktif
            $sheet->setCellValue('A1', 'E - Verifikatur Kecamatan Tarogong Kidul — Rekapitulasi Data Kuitansi & Perpajakan');
            $sheet->mergeCells("A1:{$lastColLetter}1");
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

            $sheet->setCellValue('A2', $this->formatFilterSummary($filters).' | Dicetak: '.now()->format('d/m/Y H:i'));
            $sheet->mergeCells("A2:{$lastColLetter}2");
            $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setARGB('FF475569');

            // Baris 4: Header Tabel
            $headerRow = 4;
            foreach (self::HEADINGS as $idx => $heading) {
                $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
                $sheet->setCellValueExplicit("{$colLetter}{$headerRow}", $heading, DataType::TYPE_STRING);
            }

            $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFF1F5F9'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $totals = array_fill_keys(self::NUMERIC_COLUMN_INDICES, 0.0);
            $currentRow = $headerRow + 1;

            foreach ($query->lazy(500) as $kuitansi) {
                $row = $this->extractReceiptRow($kuitansi);

                foreach ($row as $idx => $value) {
                    $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
                    $cell = "{$colLetter}{$currentRow}";

                    if (in_array($idx, self::NUMERIC_COLUMN_INDICES, true)) {
                        $numericVal = round((float) $value, 2);
                        $totals[$idx] = round($totals[$idx] + $numericVal, 2);
                        $sheet->setCellValueExplicit($cell, $numericVal, DataType::TYPE_NUMERIC);
                        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
                    } else {
                        $sheet->setCellValueExplicit(
                            $cell,
                            $this->sanitizeSpreadsheetCell($value),
                            DataType::TYPE_STRING
                        );
                    }
                }

                $currentRow++;
            }

            // Baris TOTAL (aman untuk dataset 0 baris)
            $sheet->setCellValueExplicit("A{$currentRow}", 'TOTAL', DataType::TYPE_STRING);
            $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
            foreach (self::NUMERIC_COLUMN_INDICES as $idx) {
                $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
                $cell = "{$colLetter}{$currentRow}";
                $sheet->setCellValueExplicit($cell, $totals[$idx], DataType::TYPE_NUMERIC);
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
            }

            $sheet->getStyle("A{$currentRow}:{$lastColLetter}{$currentRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE2E8F0'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF94A3B8']],
                ],
            ]);

            foreach (range(1, count(self::HEADINGS)) as $colIdx) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIdx))->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 200, $headers);
    }

    /**
     * Mengunduh laporan rekapitulasi kuitansi berformat PDF A4 Landscape (batas aman 1.000 baris).
     */
    public function downloadPdfExport(User $user, array $filters = []): Response
    {
        $query = $this->buildExportQuery($user, $filters);
        $totalCount = (clone $query)->count();

        if ($totalCount > self::PDF_MAX_ROWS) {
            throw ValidationException::withMessages([
                'format' => 'Data melebihi batas aman ekspor PDF ('.self::PDF_MAX_ROWS.' baris). Mohon persempit filter atau gunakan format Excel (.xlsx).',
            ]);
        }

        $rows = [];
        $totals = array_fill_keys(self::NUMERIC_COLUMN_INDICES, 0.0);

        foreach ($query->lazy(500) as $kuitansi) {
            $row = $this->extractReceiptRow($kuitansi);
            foreach (self::NUMERIC_COLUMN_INDICES as $idx) {
                $totals[$idx] = round($totals[$idx] + (float) $row[$idx], 2);
            }
            $rows[] = $row;
        }

        $pdf = Pdf::loadView('pdf.rekap-kuitansi', [
            'rows' => $rows,
            'totals' => $totals,
            'filterSummary' => $this->formatFilterSummary($filters),
            'printedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'rekapitulasi-kuitansi-'.date('Ymd-His').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Membangun query dasar ekspor dengan eager loading lengkap.
     */
    private function buildExportQuery(User $user, array $filters): Builder
    {
        return $this->queryService->buildFilteredQuery($user, $filters)
            ->with(['rekening.unitKerja', 'pajak', 'activeSpjItem.pengajuanSpj'])
            ->orderBy('tanggal_transaksi', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * Mengekstrak 19 kolom data mentah & nilai numerik kuitansi (dipakai bersama oleh CSV, Excel, dan PDF).
     *
     * @return list<string|float>
     */
    public function extractReceiptRow(Kuitansi $kuitansi): array
    {
        $pajakMap = [
            'dpp' => 0.00,
            'ppn' => 0.00,
            'pph21' => 0.00,
            'pph22' => 0.00,
            'pph23' => 0.00,
            'pbjt' => 0.00,
            'meterai' => 0.00,
        ];

        foreach ($kuitansi->pajak as $pajak) {
            if ((float) $pajak->dpp > $pajakMap['dpp']) {
                $pajakMap['dpp'] = (float) $pajak->dpp;
            }

            match ($pajak->kode_pajak) {
                'PPN_11' => $pajakMap['ppn'] += (float) $pajak->nominal,
                'PPH_21' => $pajakMap['pph21'] += (float) $pajak->nominal,
                'PPH_22' => $pajakMap['pph22'] += (float) $pajak->nominal,
                'PPH_23' => $pajakMap['pph23'] += (float) $pajak->nominal,
                'PBJT_10', 'PBJT_MAMIN_10' => $pajakMap['pbjt'] += (float) $pajak->nominal,
                'BEA_METERAI' => $pajakMap['meterai'] += (float) $pajak->nominal,
                default => null,
            };
        }

        // Jika tidak ada pemotongan pajak (misal ATK <= 2jt), DPP sama dengan Bruto
        if ($pajakMap['dpp'] == 0.00 && $kuitansi->nominal_bruto > 0) {
            $pajakMap['dpp'] = (float) $kuitansi->nominal_bruto;
        }

        $nomorSp2d = $kuitansi->activeSpjItem?->pengajuanSpj?->nomor_sp2d_sipd ?? '-';

        return [
            (string) $kuitansi->nomor_kuitansi,
            $kuitansi->tanggal_transaksi ? $kuitansi->tanggal_transaksi->format('Y-m-d') : '',
            (string) ($kuitansi->rekening?->unitKerja?->nama_unit ?? '-'),
            (string) ($kuitansi->rekening?->kode_rekening ?? '-'),
            (string) ($kuitansi->rekening?->nama_rekening ?? '-'),
            (string) $kuitansi->uraian,
            round((float) $kuitansi->nominal_bruto, 2),
            round($pajakMap['dpp'], 2),
            round($pajakMap['ppn'], 2),
            round($pajakMap['pph21'], 2),
            round($pajakMap['pph22'], 2),
            round($pajakMap['pph23'], 2),
            round($pajakMap['pbjt'], 2),
            round($pajakMap['meterai'], 2),
            round((float) $kuitansi->nominal_neto, 2),
            (string) ($kuitansi->nama_penerima ?? '-'),
            (string) ($kuitansi->npwp_penerima ?? '-'),
            (string) $kuitansi->status,
            (string) $nomorSp2d,
        ];
    }

    /**
     * Merangkum filter aktif menjadi teks informatif untuk header laporan.
     */
    private function formatFilterSummary(array $filters): string
    {
        $parts = [];
        $parts[] = 'Sub Kegiatan: '.(! empty($filters['kode_sub_kegiatan']) ? $filters['kode_sub_kegiatan'] : 'Semua');
        $parts[] = 'Kode Rekening: '.(! empty($filters['kode_rekening']) ? $filters['kode_rekening'] : 'Semua');

        $dari = $filters['tanggal_dari'] ?? null;
        $sampai = $filters['tanggal_sampai'] ?? null;
        if ($dari && $sampai) {
            $parts[] = "Periode: {$dari} s.d. {$sampai}";
        } elseif ($dari) {
            $parts[] = "Periode: >= {$dari}";
        } elseif ($sampai) {
            $parts[] = "Periode: <= {$sampai}";
        } else {
            $parts[] = 'Periode: Semua';
        }

        $parts[] = 'Status: '.(! empty($filters['status']) ? $filters['status'] : 'Semua');

        return implode(' | ', $parts);
    }

    /**
     * Mencegah Formula Injection di Excel/CSV dengan tetap mempertahankan placeholder '-'.
     */
    private function sanitizeSpreadsheetCell(mixed $value): string
    {
        $str = (string) ($value ?? '');
        if ($str !== '' && $str !== '-' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$str;
        }

        return $str;
    }
}
