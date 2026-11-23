<?php

namespace App\Services;

use App\Models\KuitansiSppdPeserta;
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

class SppdReportExportService
{
    public const HEADINGS = [
        'No',
        'Tanggal Berangkat',
        'Tanggal Pulang',
        'Durasi (Hari)',
        'NIP / NIK',
        'Nama Pegawai',
        'Jabatan',
        'Golongan',
        'Unit Kerja',
        'Kode Sub Kegiatan',
        'Nama Sub Kegiatan',
        'Tujuan',
        'Nomor Kuitansi',
        'Status Kuitansi',
        'Foto Keberangkatan',
    ];

    /**
     * Indeks kolom numerik (0-based) pada HEADINGS.
     *
     * @var list<int>
     */
    private const NUMERIC_COLUMN_INDICES = [0, 3];

    private const PDF_MAX_ROWS = 1000;

    public function __construct(
        protected SppdReportService $reportService
    ) {}

    /**
     * Ekspor CSV rekapitulasi SPPD dengan UTF-8 BOM dan proteksi formula injection.
     *
     * @param  array<string, mixed>  $filters
     */
    public function streamCsvExport(User $user, array $filters = []): StreamedResponse
    {
        $filename = 'rekap-sppd-'.date('Ymd-His').'.csv';
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
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, self::HEADINGS);

            $rowNumber = 1;
            foreach ($query->lazy(500) as $item) {
                $row = $this->extractReportRow($item, $rowNumber++);
                $csvRow = [];

                foreach ($row as $idx => $value) {
                    if (in_array($idx, self::NUMERIC_COLUMN_INDICES, true)) {
                        $csvRow[] = (int) $value;
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
     * Ekspor Excel (.xlsx) rekapitulasi SPPD dengan proteksi formula injection dan ringkasan total.
     *
     * @param  array<string, mixed>  $filters
     */
    public function streamExcelExport(User $user, array $filters = []): StreamedResponse
    {
        $filename = 'rekap-sppd-'.date('Ymd-His').'.xlsx';
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
            $sheet->setTitle('Rekap Laporan SPPD');

            $lastColLetter = Coordinate::stringFromColumnIndex(count(self::HEADINGS));

            $sheet->setCellValue('A1', 'E - Verifikatur Kecamatan Tarogong Kidul — Rekapitulasi Laporan Perjalanan Dinas (SPPD)');
            $sheet->mergeCells("A1:{$lastColLetter}1");
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

            $sheet->setCellValue('A2', $this->formatFilterSummary($filters).' | Dicetak: '.now()->format('d/m/Y H:i'));
            $sheet->mergeCells("A2:{$lastColLetter}2");
            $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setARGB('FF475569');

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

            $currentRow = $headerRow + 1;
            $rowNumber = 1;
            $totalHariOrang = 0;

            foreach ($query->lazy(500) as $item) {
                $row = $this->extractReportRow($item, $rowNumber++);
                $totalHariOrang += (int) $row[3];

                foreach ($row as $idx => $value) {
                    $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
                    $cell = "{$colLetter}{$currentRow}";

                    if (in_array($idx, self::NUMERIC_COLUMN_INDICES, true)) {
                        $sheet->setCellValueExplicit($cell, (int) $value, DataType::TYPE_NUMERIC);
                        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
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
            $totalPerjalanan = $rowNumber - 1;
            $sheet->setCellValueExplicit("A{$currentRow}", "TOTAL ({$totalPerjalanan} Perjalanan)", DataType::TYPE_STRING);
            $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
            $sheet->setCellValueExplicit("D{$currentRow}", $totalHariOrang, DataType::TYPE_NUMERIC);
            $sheet->getStyle("D{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');

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
     * Mengunduh laporan rekapitulasi SPPD berformat PDF A4 Landscape (batas aman 1.000 baris).
     *
     * @param  array<string, mixed>  $filters
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
        $rowNumber = 1;
        $totalHariOrang = 0;

        foreach ($query->lazy(500) as $item) {
            $row = $this->extractReportRow($item, $rowNumber++);
            $totalHariOrang += (int) $row[3];
            $rows[] = $row;
        }

        $pdf = Pdf::loadView('pdf.rekap-sppd', [
            'rows' => $rows,
            'totalPerjalanan' => count($rows),
            'totalHariOrang' => $totalHariOrang,
            'filterSummary' => $this->formatFilterSummary($filters),
            'printedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'rekap-sppd-'.date('Ymd-His').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildExportQuery(User $user, array $filters): Builder
    {
        return $this->reportService->buildFilteredQuery($user, $filters)
            ->orderBy('tanggal_berangkat', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * Mengekstrak 15 kolom baris laporan SPPD.
     *
     * @return list<string|int>
     */
    public function extractReportRow(KuitansiSppdPeserta $item, int $no): array
    {
        $durasi = ($item->tanggal_berangkat && $item->tanggal_pulang)
            ? (int) $item->tanggal_berangkat->diffInDays($item->tanggal_pulang) + 1
            : 1;

        $fotoStatus = filled($item->foto_path)
            ? 'Foto Peserta'
            : (filled($item->kuitansi?->sppd_foto_path) ? 'Foto Rombongan' : 'Belum Ada');

        return [
            $no,
            $item->tanggal_berangkat?->format('Y-m-d') ?? '-',
            $item->tanggal_pulang?->format('Y-m-d') ?? '-',
            $durasi,
            (string) ($item->pegawai?->nip ?? '-'),
            (string) ($item->pegawai?->nama ?? '-'),
            (string) ($item->pegawai?->jabatan ?: '-'),
            (string) ($item->pegawai?->golongan ?: '-'),
            (string) ($item->kuitansi?->unitKerja?->nama_unit ?? '-'),
            (string) ($item->kuitansi?->rekening?->kode_sub_kegiatan ?? '-'),
            (string) ($item->kuitansi?->rekening?->nama_sub_kegiatan ?? '-'),
            (string) ($item->tujuan ?: '-'),
            (string) ($item->kuitansi?->nomor_kuitansi ?? '-'),
            (string) ($item->kuitansi?->status ?? '-'),
            $fotoStatus,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function formatFilterSummary(array $filters): string
    {
        $parts = [];
        $parts[] = 'Sub Kegiatan: '.(! empty($filters['kode_sub_kegiatan']) ? $filters['kode_sub_kegiatan'] : 'Semua');

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

    private function sanitizeSpreadsheetCell(mixed $value): string
    {
        $str = (string) ($value ?? '');
        if ($str !== '' && $str !== '-' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$str;
        }

        return $str;
    }
}
