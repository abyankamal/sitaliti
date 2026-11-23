<?php

namespace App\Services;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response as HttpResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RekapBelanjaOperasiExportService
{
    public const HEADINGS = [
        'No',
        'Kode Pos',
        'Nama Pos Rekap Belanja Operasi',
        'Jml Rekening',
        'Pagu Tahunan (Rp)',
        'Target RAK Periode (Rp)',
        'Realisasi SP2D (Rp)',
        'Sisa Anggaran (Rp)',
        'Serapan (%)',
    ];

    private const MONTH_NAMES = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function __construct(
        protected RekapBelanjaOperasiReportService $reportService
    ) {}

    /**
     * Ekspor Excel (.xlsx) rekap Belanja Operasi dengan tipe sel eksplisit & anti formula injection.
     *
     * @param  array<string, mixed>  $filters
     */
    public function streamXlsxExport(User $user, array $filters = []): StreamedResponse
    {
        $report = $this->reportService->getReport($user, $filters);
        $summary = $report['summary'];
        $rows = $report['rows'];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Belanja Operasi');

        $periodeLabel = $this->formatPeriodeLabel((int) $summary['bulan_dari'], (int) $summary['bulan_sampai']);

        $sheet->setCellValueExplicit('A1', 'REKAPITULASI BELANJA OPERASI (5.1)', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('A2', "Periode: {$periodeLabel}", DataType::TYPE_STRING);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A2')->getFont()->setSize(10);

        $headerRow = 4;
        foreach (self::HEADINGS as $idx => $heading) {
            $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->setCellValueExplicit("{$colLetter}{$headerRow}", $heading, DataType::TYPE_STRING);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count(self::HEADINGS));
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $rowIndex = $headerRow + 1;
        foreach ($rows as $idx => $row) {
            $sheet->setCellValueExplicit("A{$rowIndex}", (string) ($idx + 1), DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("B{$rowIndex}", $this->sanitizeSpreadsheetCell((string) $row['kode']), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$rowIndex}", $this->sanitizeSpreadsheetCell((string) $row['nama']), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("D{$rowIndex}", (string) $row['jumlah_rekening'], DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("E{$rowIndex}", (string) $row['pagu_tahunan'], DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("F{$rowIndex}", (string) $row['target_rak_periode'], DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("G{$rowIndex}", (string) $row['realisasi_sp2d'], DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("H{$rowIndex}", (string) $row['sisa_anggaran'], DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("I{$rowIndex}", (string) $row['persentase_realisasi'], DataType::TYPE_NUMERIC);
            $rowIndex++;
        }

        // Baris Total
        $sheet->setCellValueExplicit("A{$rowIndex}", '', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("B{$rowIndex}", 'TOTAL', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("C{$rowIndex}", 'Total Belanja Operasi', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("D{$rowIndex}", (string) array_sum(array_column($rows, 'jumlah_rekening')), DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit("E{$rowIndex}", (string) $summary['total_pagu_tahunan'], DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit("F{$rowIndex}", (string) $summary['total_target_rak_periode'], DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit("G{$rowIndex}", (string) $summary['total_realisasi_sp2d'], DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit("H{$rowIndex}", (string) $summary['total_sisa_anggaran'], DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit("I{$rowIndex}", (string) $summary['persentase_realisasi'], DataType::TYPE_NUMERIC);

        $sheet->getStyle("A{$rowIndex}:{$lastCol}{$rowIndex}")->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$rowIndex}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
            ],
        ]);

        $sheet->getStyle('E'.($headerRow + 1).":H{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('I'.($headerRow + 1).":I{$rowIndex}")->getNumberFormat()->setFormatCode('0.00"%"');

        foreach (range(1, count(self::HEADINGS)) as $colIdx) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = 'rekap-belanja-operasi-'.date('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Ekspor PDF A4 Landscape untuk Rekap Belanja Operasi.
     *
     * @param  array<string, mixed>  $filters
     */
    public function downloadPdfExport(User $user, array $filters = []): HttpResponse
    {
        $report = $this->reportService->getReport($user, $filters);
        $summary = $report['summary'];

        $pdf = Pdf::loadView('pdf.rekap-belanja-operasi', [
            'summary' => $summary,
            'rows' => $report['rows'],
            'periodeLabel' => $this->formatPeriodeLabel((int) $summary['bulan_dari'], (int) $summary['bulan_sampai']),
            'generatedAt' => now()->format('d/m/Y H:i'),
            'printedBy' => $user->name ?? $user->username ?? 'Pengguna',
        ])->setPaper('a4', 'landscape');

        return $pdf->download('rekap-belanja-operasi-'.date('Ymd-His').'.pdf');
    }

    private function sanitizeSpreadsheetCell(string $value): string
    {
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }

    private function formatPeriodeLabel(int $bulanDari, int $bulanSampai): string
    {
        $dari = self::MONTH_NAMES[$bulanDari] ?? (string) $bulanDari;
        $sampai = self::MONTH_NAMES[$bulanSampai] ?? (string) $bulanSampai;

        return $bulanDari === $bulanSampai ? $dari : "{$dari} s.d. {$sampai}";
    }
}
