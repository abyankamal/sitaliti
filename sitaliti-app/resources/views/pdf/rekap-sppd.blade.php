<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi Laporan Perjalanan Dinas (SPPD)</title>
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
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .mono {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 6.8pt;
        }
        .total-row td {
            background-color: #e2e8f0;
            font-weight: bold;
            color: #0f172a;
        }
        .footer-note {
            margin-top: 8px;
            font-size: 6.8pt;
            color: #64748b;
            text-align: right;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 55px; text-align: left; vertical-align: middle;">
                @php
                    $logoPath = public_path('Lambang_Kabupaten_Garut.jpg');
                @endphp
                @if (file_exists($logoPath))
                    <img src="{{ $logoPath }}" alt="Logo" style="width: 44px; height: auto;">
                @endif
            </td>
            <td class="header-title">
                <h1>Pemerintah Kabupaten Garut</h1>
                <h2>Kecamatan Tarogong Kidul</h2>
                <p>E - Verifikatur Kecamatan Tarogong Kidul • Rekapitulasi Perjalanan Dinas (SPPD)</p>
            </td>
            <td style="width: 55px;"></td>
        </tr>
    </table>
    <div class="double-line"></div>

    <div class="doc-title">
        <h3>Rekapitulasi Pelaksana &amp; Jadwal Perjalanan Dinas (SPPD)</h3>
        <p>Dicetak pada: {{ $printedAt }}</p>
    </div>

    <div class="filter-box">
        <strong>Filter Aktif:</strong> {{ $filterSummary }}
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 3%;">No</th>
                <th style="width: 11%;">Tanggal Berangkat – Pulang</th>
                <th style="width: 5%;">Durasi</th>
                <th style="width: 17%;">NIP &amp; Nama Pegawai</th>
                <th style="width: 11%;">Jabatan / Gol.</th>
                <th style="width: 10%;">Unit Kerja</th>
                <th style="width: 17%;">Sub Kegiatan</th>
                <th style="width: 10%;">Tujuan</th>
                <th style="width: 10%;">No. Kuitansi / Status</th>
                <th style="width: 6%;">Foto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="text-center">{{ $row[0] }}</td>
                    <td class="mono text-center">{{ $row[1] }}<br>s/d {{ $row[2] }}</td>
                    <td class="text-center">{{ $row[3] }} hr</td>
                    <td>
                        <strong>{{ $row[5] }}</strong><br>
                        <span class="mono">NIP: {{ $row[4] }}</span>
                    </td>
                    <td>{{ $row[6] }}<br><span style="color: #475569;">Gol: {{ $row[7] }}</span></td>
                    <td>{{ $row[8] }}</td>
                    <td>
                        <span class="mono">{{ $row[9] }}</span><br>
                        {{ $row[10] }}
                    </td>
                    <td>{{ $row[11] }}</td>
                    <td>
                        <span class="mono">{{ $row[12] }}</span><br>
                        <span style="color: #475569;">{{ $row[13] }}</span>
                    </td>
                    <td class="text-center">{{ $row[14] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="padding: 14px; color: #64748b;">
                        Tidak ada data perjalanan dinas (SPPD) untuk kriteria filter yang dipilih.
                    </td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="2" class="text-center">TOTAL</td>
                <td class="text-center">{{ $totalHariOrang }} hr</td>
                <td colspan="7">Total Perjalanan Peserta: {{ $totalPerjalanan }} perjalanan</td>
            </tr>
        </tbody>
    </table>

    <div class="footer-note">
        Dokumen rekapitulasi ini dihasilkan secara otomatis oleh sistem E - Verifikatur Kecamatan Tarogong Kidul.
    </div>
</body>
</html>
