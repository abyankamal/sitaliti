<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lembar Kendali Verifikasi - {{ $nomor_kuitansi }}</title>
    <style>
        @page {
            margin: 15mm 15mm 15mm 15mm;
            size: A4 portrait;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            color: #1e293b;
            line-height: 1.35;
        }

        /* Pure Table Layout (CSS 2.1 Compliant for DomPDF) */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
        }

        .header-title {
            text-align: center;
        }

        .header-title h1 {
            font-size: 13pt;
            font-weight: bold;
            margin: 0;
            color: #0f172a;
            text-transform: uppercase;
        }

        .header-title h2 {
            font-size: 11pt;
            font-weight: bold;
            margin: 2px 0;
            color: #1e293b;
            text-transform: uppercase;
        }

        .header-title p {
            font-size: 8pt;
            margin: 0;
            color: #475569;
        }

        .double-line {
            border-top: 2px solid #0f172a;
            border-bottom: 1px solid #0f172a;
            height: 2px;
            margin: 8px 0 12px 0;
        }

        .doc-title {
            text-align: center;
            margin-bottom: 12px;
        }

        .doc-title h3 {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 2px 0;
            color: #0f172a;
            text-decoration: underline;
        }

        .doc-title .reg-number {
            font-size: 8.5pt;
            color: #475569;
            font-family: monospace;
        }

        /* Section Cards */
        .section-header {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 4px 8px;
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #0f172a;
            margin-top: 8px;
            margin-bottom: 4px;
        }

        .info-table td {
            padding: 3px 6px;
            font-size: 8.5pt;
            vertical-align: top;
        }

        .info-table td.label {
            width: 22%;
            color: #475569;
            font-weight: 600;
        }

        .info-table td.sep {
            width: 2%;
            text-align: center;
        }

        .info-table td.value {
            width: 76%;
            color: #0f172a;
        }

        /* Data Tables */
        .data-table {
            margin-top: 4px;
            margin-bottom: 8px;
            border: 1px solid #cbd5e1;
        }

        .data-table th {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #334155;
            text-align: left;
        }

        .data-table td {
            border: 1px solid #e2e8f0;
            padding: 4px 6px;
            font-size: 8pt;
            vertical-align: middle;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .badge-verified {
            display: inline-block;
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 7.5pt;
        }

        .badge-revision {
            display: inline-block;
            background-color: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 7.5pt;
        }

        /* Signature & QR Box */
        .sig-table {
            margin-top: 14px;
            page-break-inside: avoid;
        }

        .sig-box {
            border: 1px solid #cbd5e1;
            padding: 8px;
            background-color: #f8fafc;
            border-radius: 4px;
        }

        .qr-cell {
            width: 140px;
            text-align: center;
            vertical-align: middle;
        }

        .footer-note {
            margin-top: 12px;
            font-size: 7.5pt;
            color: #64748b;
            text-align: center;
            border-top: 1px dashed #cbd5e1;
            padding-top: 6px;
        }
    </style>
</head>
<body>

    <!-- KOP PEMERINTAH KABUPATEN GARUT -->
    <table class="header-table">
        <tr>
            <td style="width: 70px; text-align: center;">
                <!-- Logo Pemkab Garut Placeholder / Text Box -->
                <div style="width: 55px; height: 55px; border: 1.5px solid #0f172a; border-radius: 4px; text-align: center; line-height: 52px; font-weight: bold; font-size: 9pt; color: #0f172a; margin: 0 auto;">
                    GARUT
                </div>
            </td>
            <td class="header-title">
                <h1>Pemerintah Kabupaten Garut</h1>
                <h2>Kecamatan Tarogong Kidul</h2>
                <p>Jalan Simpang No. 1, Tarogong Kidul, Garut, Jawa Barat 44151</p>
                <p>Website: tarogongkidul.garutkab.go.id &bull; Surel: kec.tarogongkidul@garutkab.go.id</p>
            </td>
        </tr>
    </table>

    <div class="double-line"></div>

    <!-- JUDUL DOKUMEN -->
    <div class="doc-title">
        <h3>Lembar Kendali Hasil Verifikasi Akuntabilitas SPJ</h3>
        <span class="reg-number">Register Keabsahan: {{ $nomor_register }}</span>
    </div>

    <!-- BAGIAN 1: IDENTITAS TRANSAKSI & ANGGARAN -->
    <div class="section-header">I. Identitas Transaksi & Rekening Belanja</div>
    <table class="info-table">
        <tr>
            <td class="label">Nomor Kuitansi</td>
            <td class="sep">:</td>
            <td class="value"><strong>{{ $nomor_kuitansi }}</strong></td>
        </tr>
        <tr>
            <td class="label">Unit Kerja Pengaju</td>
            <td class="sep">:</td>
            <td class="value">{{ $unit_kerja->nama_unit ?? ($unit_kerja['nama_unit'] ?? '-') }} (Kode: {{ $unit_kerja->kode_unit ?? ($unit_kerja['kode_unit'] ?? '-') }})</td>
        </tr>
        <tr>
            <td class="label">Sub Kegiatan</td>
            <td class="sep">:</td>
            <td class="value">{{ $rekening->kode_sub_kegiatan ?? ($rekening['kode_sub_kegiatan'] ?? '-') }} &bull; {{ $rekening->nama_sub_kegiatan ?? ($rekening['nama_sub_kegiatan'] ?? '-') }}</td>
        </tr>
        <tr>
            <td class="label">Rekening Belanja</td>
            <td class="sep">:</td>
            <td class="value">{{ $rekening->kode_rekening ?? ($rekening['kode_rekening'] ?? '-') }} &bull; {{ $rekening->nama_rekening ?? ($rekening['nama_rekening'] ?? '-') }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Transaksi</td>
            <td class="sep">:</td>
            <td class="value">{{ $tanggal_transaksi ? date('d F Y', strtotime($tanggal_transaksi)) : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Uraian Pengeluaran</td>
            <td class="sep">:</td>
            <td class="value">{{ $uraian }}</td>
        </tr>
        <tr>
            <td class="label">Rekanan / Penerima</td>
            <td class="sep">:</td>
            <td class="value">{{ $nama_penerima }} @if(!empty($npwp_penerima)) &bull; NPWP: {{ $npwp_penerima }} @endif</td>
        </tr>
    </table>

    <!-- BAGIAN 2: RINGKASAN FINANSIAL & POTONGAN PAJAK -->
    <div class="section-header">II. Rincian Finansial & Pemotongan Pajak Daerah / Pusat</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25%;">Nominal Bruto</th>
                <th style="width: 35%;">Rincian Pajak Dipungut</th>
                <th style="width: 18%;" class="text-right">Total Pajak</th>
                <th style="width: 22%;" class="text-right">Nominal Neto (Dibayarkan)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="font-weight: bold; font-size: 9pt;">
                    Rp {{ number_format($nominal_bruto, 2, ',', '.') }}
                </td>
                <td style="font-size: 7.5pt;">
                    @if(count($pajak) > 0)
                        @foreach($pajak as $p)
                            &bull; {{ $p->nama_pajak ?? ($p['nama_pajak'] ?? 'Pajak') }}: Rp {{ number_format($p->nominal ?? ($p['nominal'] ?? 0), 2, ',', '.') }}<br>
                        @endforeach
                    @else
                        <em>Bebas pungut pajak (Bawah ambang batas PMK 59/2022)</em>
                    @endif
                </td>
                <td class="text-right" style="color: #b91c1c; font-weight: bold;">
                    Rp {{ number_format($total_pajak, 2, ',', '.') }}
                </td>
                <td class="text-right" style="color: #047857; font-weight: bold; font-size: 9pt;">
                    Rp {{ number_format($nominal_neto, 2, ',', '.') }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- BAGIAN 3: HASIL AUDIT CHECKLIST VERIFIKASI -->
    <div class="section-header">III. Hasil Pemeriksaan Standar Checklist Verifikasi Dokumen</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 6%;" class="text-center">No</th>
                <th style="width: 14%;">Kode Item</th>
                <th style="width: 48%;">Nama Dokumen Bukti Pertanggungjawaban</th>
                <th style="width: 14%;" class="text-center">Kesesuaian</th>
                <th style="width: 18%;">Catatan Penguji</th>
            </tr>
        </thead>
        <tbody>
            @forelse($checklist as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td style="font-family: monospace; font-weight: bold;">{{ $item['kode_item'] ?? '-' }}</td>
                    <td>{{ $item['nama_item'] ?? '-' }}</td>
                    <td class="text-center">
                        @if(($item['is_verified'] ?? false) || ($item['status_kesesuaian'] ?? '') === 'SESUAI')
                            <span class="badge-verified">SESUAI</span>
                        @else
                            <span class="badge-revision">{{ $item['status_kesesuaian'] ?? 'BELUM' }}</span>
                        @endif
                    </td>
                    <td style="font-size: 7.5pt; color: #475569;">{{ $item['catatan'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center" style="padding: 10px; color: #64748b;">
                        <em>Tidak ada butir checklist yang tersimpan untuk berkas ini.</em>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- BAGIAN 4: PENGESAHAN DOKUMEN & VALIDASI DIGITAL QR -->
    <table class="sig-table">
        <tr>
            <!-- Catatan & Keterangan Penguji -->
            <td style="width: 60%; vertical-align: top; padding-right: 12px;">
                <div class="sig-box">
                    <div style="font-weight: bold; font-size: 8pt; text-transform: uppercase; margin-bottom: 4px; color: #0f172a;">
                        Kesimpulan & Catatan Verifikator:
                    </div>
                    <p style="margin: 0; font-size: 8pt; color: #334155; font-style: italic;">
                        &ldquo;{{ $catatan_verifikasi ?: 'Berkas pertanggungjawaban telah diuji kelengkapan bukti transaksi, kepatuhan perpajakan PMK 59/2022, dan kesesuaian pagu kas bulanan (RAK).' }}&rdquo;
                    </p>
                    <div style="margin-top: 8px; font-size: 7.5pt; color: #64748b;">
                        Status Berkas: <strong>{{ str_replace('_', ' ', $status) }}</strong><br>
                        Waktu Verifikasi: {{ $tanggal_verifikasi ? date('d/m/Y H:i:s', strtotime($tanggal_verifikasi)) : date('d/m/Y H:i:s') }} WIB
                    </div>
                </div>
            </td>

            <!-- QR Code & Identitas Verifikator -->
            <td style="width: 40%; vertical-align: top;">
                <div class="sig-box" style="text-align: center;">
                    <div style="font-size: 7.5pt; color: #475569; margin-bottom: 4px;">
                        Tanda Tangan Digital Tersertifikasi:
                    </div>

                    @if(!empty($qr_code_svg))
                        <div style="margin: 4px auto;">
                            <img src="data:image/svg+xml;base64,{{ base64_encode($qr_code_svg) }}" width="105" height="105" alt="QR Code Lembar Kendali" />
                        </div>
                    @else
                        <div style="width: 105px; height: 105px; border: 1px dashed #94a3b8; margin: 4px auto; line-height: 105px; font-size: 8pt; color: #94a3b8;">
                            QR Validasi
                        </div>
                    @endif

                    <div style="font-size: 8.5pt; font-weight: bold; color: #0f172a; margin-top: 4px;">
                        {{ $verifikator['name'] ?? 'Kasubag Keuangan (PPK Kecamatan)' }}
                    </div>
                    <div style="font-size: 7.5pt; color: #64748b;">
                        NIP: {{ $verifikator['nip'] ?? '-' }}
                    </div>
                    <div style="font-size: 7pt; color: #94a3b8; margin-top: 2px;">
                        {{ $verifikator['jabatan'] ?? 'Kasubag Perencanaan & Keuangan / PPK SKPD' }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Dokumen Lembar Kendali ini diterbitkan secara elektronik oleh Sistem Informasi Tata Kelola Akuntabilitas & Verifikasi Kuitansi (SI-TALITI v2.0) Kecamatan Tarogong Kidul.<br>
        Keabsahan fisik dokumen dapat diuji melalui pemindaian QR Code di atas menggunakan aplikasi resmi Pemkab Garut tanpa memerlukan stempel basah.
    </div>

</body>
</html>

