<?php

namespace App\Services;

use App\Models\Kuitansi;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptExportService
{
    public function __construct(
        protected ReceiptQueryService $queryService
    ) {}

    /**
     * Mengalirkan file CSV rekapitulasi data kuitansi dan perpajakan menggunakan database cursor.
     */
    public function streamExport(User $user, array $filters = []): StreamedResponse
    {
        $filename = 'rekapitulasi-kuitansi-'.date('Ymd-His').'.csv';

        $query = $this->queryService->buildFilteredQuery($user, $filters)
            ->with(['rekening.unitKerja', 'pajak', 'activeSpjItem.pengajuanSpj'])
            ->orderBy('tanggal_transaksi', 'asc')
            ->orderBy('id', 'asc');

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

            // Tulis Header Kolom Rekapitulasi
            fputcsv($handle, [
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
            ]);

            // Gunakan database cursor untuk mengalirkan data tanpa beban memori tinggi
            foreach ($query->cursor() as $kuitansi) {
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
                        'PBJT_10' => $pajakMap['pbjt'] += (float) $pajak->nominal,
                        'BEA_METERAI' => $pajakMap['meterai'] += (float) $pajak->nominal,
                        default => null,
                    };
                }

                // Jika tidak ada pemotongan pajak (misal ATK <= 2jt), DPP sama dengan Bruto
                if ($pajakMap['dpp'] == 0.00 && $kuitansi->nominal_bruto > 0) {
                    $pajakMap['dpp'] = (float) $kuitansi->nominal_bruto;
                }

                $nomorSp2d = $kuitansi->activeSpjItem?->pengajuanSpj?->nomor_sp2d_sipd ?? '-';

                fputcsv($handle, [
                    $kuitansi->nomor_kuitansi,
                    $kuitansi->tanggal_transaksi ? $kuitansi->tanggal_transaksi->format('Y-m-d') : '',
                    $kuitansi->rekening?->unitKerja?->nama_unit ?? '-',
                    $kuitansi->rekening?->kode_rekening ?? '-',
                    $kuitansi->rekening?->nama_rekening ?? '-',
                    $kuitansi->uraian,
                    number_format((float) $kuitansi->nominal_bruto, 2, '.', ''),
                    number_format($pajakMap['dpp'], 2, '.', ''),
                    number_format($pajakMap['ppn'], 2, '.', ''),
                    number_format($pajakMap['pph21'], 2, '.', ''),
                    number_format($pajakMap['pph22'], 2, '.', ''),
                    number_format($pajakMap['pph23'], 2, '.', ''),
                    number_format($pajakMap['pbjt'], 2, '.', ''),
                    number_format($pajakMap['meterai'], 2, '.', ''),
                    number_format((float) $kuitansi->nominal_neto, 2, '.', ''),
                    $kuitansi->nama_penerima ?? '-',
                    $kuitansi->npwp_penerima ?? '-',
                    $kuitansi->status,
                    $nomorSp2d,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
