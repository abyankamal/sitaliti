<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SPPD - {{ $assignment->nomor_surat_tugas ?: 'Penugasan #' . $assignment->id }}</title>
    <style>
        @page {
            size: 215mm 330mm;
            margin: 12mm 14mm;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 10pt;
            color: #000000;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .doc-page {
            page-break-after: always;
        }

        .doc-page:last-child {
            page-break-after: auto;
        }

        .ttd-block {
            page-break-inside: avoid;
        }

        .kop-table {
            border-bottom: 3px double #000000;
            margin-bottom: 10px;
            padding-bottom: 6px;
        }

        .kop-title-1 {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }

        .kop-title-2 {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 1px 0;
        }

        .kop-sub {
            font-size: 9pt;
            margin: 1px 0;
        }

        .doc-title {
            text-align: center;
            font-size: 11.5pt;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
            margin: 6px 0 2px 0;
        }

        .doc-subtitle {
            text-align: center;
            font-size: 10pt;
            margin: 0 0 10px 0;
        }

        .bordered th,
        .bordered td {
            border: 1px solid #000000;
            padding: 4px 6px;
            vertical-align: top;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .underline { text-decoration: underline; }
    </style>
</head>
<body>
    @if (in_array($scope, ['pre', 'all'], true))
        @include('pdf.sppd.surat-tugas', ['assignment' => $assignment, 'logoDataUri' => $logoDataUri])

        @foreach ($assignment->peserta as $peserta)
            @include('pdf.sppd.spd-depan', [
                'assignment' => $assignment,
                'peserta' => $peserta,
                'lamaHari' => $lamaHari,
                'logoDataUri' => $logoDataUri,
            ])

            @include('pdf.sppd.spd-belakang', [
                'assignment' => $assignment,
                'peserta' => $peserta,
            ])
        @endforeach
    @endif

    @if (in_array($scope, ['post', 'all'], true))
        @includeIf('pdf.sppd.kwitansi', [
            'assignment' => $assignment,
            'terbilang' => $terbilang,
        ])

        @if ($assignment->peserta->count() > 1)
            @includeIf('pdf.sppd.daftar-nominatif', [
                'assignment' => $assignment,
                'terbilang' => $terbilang,
            ])
        @endif

        @foreach ($assignment->peserta as $peserta)
            @includeIf('pdf.sppd.rincian-biaya', [
                'assignment' => $assignment,
                'peserta' => $peserta,
                'terbilang' => $terbilang,
            ])
        @endforeach

        @includeIf('pdf.sppd.laporan-hasil', [
            'assignment' => $assignment,
            'logoDataUri' => $logoDataUri,
        ])
    @endif
</body>
</html>
