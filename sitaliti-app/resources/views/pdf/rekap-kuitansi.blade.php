<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi Data Kuitansi &amp; Perpajakan</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm 12mm 14mm 12mm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 7.5pt;
            color: #1e293b;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .header-title {
            text-align: center;
        }
        .header-title h1 {
            font-size: 11pt;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #0f172a;
        }
        .header-title h2 {
            font-size: 12.5pt;
            margin: 2px 0;
            text-transform: uppercase;
            color: #0f172a;
        }
        .header-title p {
            font-size: 7.5pt;
            margin: 0;
            color: #475569;
        }
        .double-line {
            border-top: 2.5px solid #0f172a;
            border-bottom: 1px solid #0f172a;
            height: 2px;
            margin-bottom: 8px;
        }
        .doc-title {
            text-align: center;
            margin-bottom: 8px;
        }
        .doc-title h3 {
            font-size: 9.5pt;
            margin: 0;
            text-transform: uppercase;
            color: #0f172a;
        }
        .doc-title p {
            font-size: 7.5pt;
            margin: 2px 0 0 0;
            color: #475569;
        }
        .filter-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            margin-bottom: 8px;
            font-size: 7pt;
            color: #334155;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .data-table thead {
            display: table-header-group;
        }
        .data-table tr {
            page-break-inside: avoid;
        }
        .data-table th,
        .data-table td {
            border: 1px solid #cbd5e1;
            padding: 3.5px 4px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .data-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
            text-align: center;
            font-size: 7pt;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .muted {
            color: #64748b;
            font-size: 6.5pt;
        }
        .total-row td {
            background-color: #e2e8f0;
            font-weight: bold;
            color: #0f172a;
            border-top: 1.5px solid #64748b;
        }
        .empty-state {
            text-align: center;
            padding: 16px;
            color: #64748b;
            font-style: italic;
        }
        .footer-note {
            margin-top: 8px;
            font-size: 6.8pt;
            color: #64748b;
            text-align: center;
            border-top: 1px dashed #cbd5e1;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <!-- KOP PEMERINTAH KABUPATEN GARUT -->
    <table class="header-table">
        <tr>
            <td style="width: 65px; text-align: center;">
                <img src="{{ public_path('Lambang_Kabupaten_Garut.jpg') }}" alt="Lambang Kabupaten Garut" style="width: 48px; height: 48px; object-fit: contain;" />
            </td>
            <td class="header-title">
                <h1>Pemerintah Kabupaten Garut</h1>
                <h2>Kecamatan Tarogong Kidul</h2>
                <p>Jalan Simpang No. 1, Tarogong Kidul, Garut, Jawa Barat 44151</p>
                <p>Website: tarogongkidul.garutkab.go.id &bull; Surel: kec.tarogongkidul@garutkab.go.id</p>
            </td>
            <td style="width: 65px;"></td>
        </tr>
    </table>

    <div class="double-line"></div>

    <div class="doc-title">
        <h3>Rekapitulasi Data Kuitansi &amp; Perpajakan</h3>
        <p>E - Verifikatur Kecamatan Tarogong Kidul</p>
    </div>

    <div class="filter-box">
        <strong>Filter Aktif:</strong> {{ $filterSummary }} &bull;
        <strong>Total Data:</strong> {{ count($rows) }} kuitansi &bull;
        <strong>Dicetak:</strong> {{ $printedAt }}
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 11%;">No. Kuitansi / Tgl</th>
                <th style="width: 11%;">Unit / Status / SP2D</th>
                <th style="width: 13%;">Kode &amp; Nama Rekening</th>
                <th style="width: 15%;">Uraian &amp; Rekanan (NPWP)</th>
                <th style="width: 7%;">Bruto (Rp)</th>
                <th style="width: 7%;">DPP (Rp)</th>
                <th style="width: 6%;">PPN (Rp)</th>
                <th style="width: 7%;">PPh 21/22/23</th>
                <th style="width: 6%;">PBJT (Rp)</th>
                <th style="width: 5%;">Meterai</th>
                <th style="width: 8%;">Neto (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $row[0] }}</strong><br>
                        <span class="muted">{{ $row[1] }}</span>
                    </td>
                    <td>
                        {{ $row[2] }}<br>
                        <span class="muted">{{ $row[17] }}</span><br>
                        <span class="muted">SP2D: {{ $row[18] }}</span>
                    </td>
                    <td>
                        <strong>{{ $row[3] }}</strong><br>
                        <span class="muted">{{ $row[4] }}</span>
                    </td>
                    <td>
                        {{ $row[5] }}<br>
                        <span class="muted">{{ $row[15] }} ({{ $row[16] }})</span>
                    </td>
                    <td class="text-right">{{ number_format((float) $row[6], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $row[7], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $row[8], 2, ',', '.') }}</td>
                    <td class="text-right">
                        {{ number_format((float) $row[9] + (float) $row[10] + (float) $row[11], 2, ',', '.') }}
                        @if ((float) $row[9] > 0 || (float) $row[10] > 0 || (float) $row[11] > 0)
                            <br><span class="muted">
                                21:{{ number_format((float) $row[9], 0, ',', '.') }}
                                22:{{ number_format((float) $row[10], 0, ',', '.') }}
                                23:{{ number_format((float) $row[11], 0, ',', '.') }}
                            </span>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format((float) $row[12], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $row[13], 2, ',', '.') }}</td>
                    <td class="text-right"><strong>{{ number_format((float) $row[14], 2, ',', '.') }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="empty-state">
                        Tidak ada data kuitansi yang sesuai dengan filter yang dipilih.
                    </td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="5" class="text-right">TOTAL KESELURUHAN</td>
                <td class="text-right">{{ number_format($totals[6], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totals[7], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totals[8], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totals[9] + $totals[10] + $totals[11], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totals[12], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totals[13], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totals[14], 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer-note">
        Dokumen rekapitulasi ini diterbitkan secara elektronik oleh E - Verifikatur Kecamatan Tarogong Kidul.
    </div>
</body>
</html>
