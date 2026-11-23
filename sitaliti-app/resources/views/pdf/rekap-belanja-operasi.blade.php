<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi Belanja Operasi</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 16px;
        }
        .header {
            border-bottom: 2px solid #1e3a5f;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header h1 {
            margin: 0;
            font-size: 14px;
            color: #0f172a;
            text-transform: uppercase;
        }
        .header p {
            margin: 3px 0 0;
            font-size: 10px;
            color: #475569;
        }
        .meta {
            margin-bottom: 10px;
            font-size: 9.5px;
            color: #334155;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 6px 7px;
        }
        th {
            background-color: #1e3a5f;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            font-size: 9.5px;
        }
        .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .center {
            text-align: center;
        }
        .mono {
            font-family: DejaVu Sans Mono, monospace;
        }
        tfoot td {
            background-color: #f1f5f9;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rekapitulasi Realisasi Belanja Operasi (5.1)</h1>
        <p>Kecamatan Tarogong Kidul — Sistem Informasi Tata Kelola Pertanggungjawaban Keuangan (SI-TALITI)</p>
    </div>

    <div class="meta">
        <strong>Periode:</strong> {{ $periodeLabel }} &nbsp;|&nbsp;
        <strong>Realisasi:</strong> SP2D Cair (SELESAI_SP2D, Bruto) &nbsp;|&nbsp;
        <strong>Dicetak:</strong> {{ $generatedAt }} oleh {{ $printedBy }}
    </div>

    <table>
        <thead>
            <tr>
                <th class="center" style="width: 32px;">No</th>
                <th style="width: 80px;">Kode Pos</th>
                <th>Nama Pos Rekap Belanja Operasi</th>
                <th class="center" style="width: 60px;">Rekening</th>
                <th class="num" style="width: 115px;">Pagu Tahunan (Rp)</th>
                <th class="num" style="width: 115px;">Target RAK (Rp)</th>
                <th class="num" style="width: 115px;">Realisasi SP2D (Rp)</th>
                <th class="num" style="width: 115px;">Sisa Anggaran (Rp)</th>
                <th class="num" style="width: 65px;">Serapan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $idx => $row)
                <tr>
                    <td class="center">{{ $idx + 1 }}</td>
                    <td class="mono">{{ $row['kode'] }}</td>
                    <td>{{ $row['nama'] }}</td>
                    <td class="center">{{ $row['jumlah_rekening'] }}</td>
                    <td class="num">{{ number_format($row['pagu_tahunan'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['target_rak_periode'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['realisasi_sp2d'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['sisa_anggaran'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['persentase_realisasi'], 2, ',', '.') }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="center">Tidak ada data Belanja Operasi untuk filter yang dipilih.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">TOTAL BELANJA OPERASI</td>
                <td class="center">{{ array_sum(array_column($rows, 'jumlah_rekening')) }}</td>
                <td class="num">{{ number_format($summary['total_pagu_tahunan'], 0, ',', '.') }}</td>
                <td class="num">{{ number_format($summary['total_target_rak_periode'], 0, ',', '.') }}</td>
                <td class="num">{{ number_format($summary['total_realisasi_sp2d'], 0, ',', '.') }}</td>
                <td class="num">{{ number_format($summary['total_sisa_anggaran'], 0, ',', '.') }}</td>
                <td class="num">{{ number_format($summary['persentase_realisasi'], 2, ',', '.') }}%</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
