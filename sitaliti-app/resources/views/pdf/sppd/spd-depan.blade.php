@php
    $pegawai = $peserta->pegawai;
    $isNonAsn = ($pegawai?->status_kepegawaian ?? 'ASN') === 'NON_ASN' || empty($pegawai?->nip);
    $pengikut = is_array($peserta->pengikut) ? $peserta->pengikut : [];
    $rekening = $assignment->kuitansi?->rekening ?? $assignment->rekening;
    $instansiAnggaran = ($rekening && (int) $rekening->unit_kerja_id !== (int) $assignment->unit_kerja_id)
        ? ($rekening->unitKerja?->nama_unit ?? 'Kecamatan Tarogong Kidul') . ' (qq. ' . ($assignment->unitKerja?->nama_unit ?? '-') . ')'
        : ($assignment->unitKerja?->nama_unit ?? 'Kecamatan Tarogong Kidul');
@endphp
<div class="doc-page">
    @include('pdf.sppd.kop-surat', ['assignment' => $assignment, 'logoDataUri' => $logoDataUri ?? null])

    <table style="margin-bottom: 4px; font-size: 9pt;">
        <tr>
            <td style="width: 58%;"></td>
            <td style="width: 14%;">Lembar Ke</td>
            <td style="width: 3%;">:</td>
            <td>{{ $peserta->urutan ?? 1 }}</td>
        </tr>
        <tr>
            <td></td>
            <td>Kode No.</td>
            <td>:</td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td>Nomor</td>
            <td>:</td>
            <td>{{ $peserta->nomor_spd ?: '      /Tarkid/SPPD/' . ($assignment->tanggal_berangkat?->format('Y') ?? date('Y')) }}</td>
        </tr>
    </table>

    <div class="doc-title" style="margin-bottom: 8px;">SURAT PERJALANAN DINAS (SPD)</div>

    <table class="bordered" style="font-size: 9.5pt;">
        <tr>
            <td style="width: 24px;" class="text-center">1.</td>
            <td style="width: 38%;">Pejabat yang memberi perintah</td>
            <td colspan="2">{{ $assignment->pemberi_perintah_jabatan }}</td>
        </tr>
        <tr>
            <td class="text-center">2.</td>
            <td>Nama / NIP Pegawai yang melaksanakan perjalanan dinas</td>
            <td colspan="2">
                <div class="font-bold">{{ $pegawai?->nama ?? '-' }}</div>
                <div>{{ $isNonAsn ? '-' : 'NIP. ' . $pegawai->nip }}</div>
            </td>
        </tr>
        <tr>
            <td class="text-center" rowspan="3">3.</td>
            <td>a. Pangkat dan Golongan</td>
            <td style="width: 18px;" class="text-center">a.</td>
            <td>{{ $isNonAsn ? '-' : ($pegawai?->golongan ?: '-') }}</td>
        </tr>
        <tr>
            <td>b. Jabatan / Instansi</td>
            <td class="text-center">b.</td>
            <td>{{ $pegawai?->jabatan ?: '-' }}</td>
        </tr>
        <tr>
            <td>c. Tingkat Biaya Perjalanan Dinas</td>
            <td class="text-center">c.</td>
            <td>{{ $peserta->tingkat_biaya ?: '-' }}</td>
        </tr>
        <tr>
            <td class="text-center">4.</td>
            <td>Maksud Perjalanan Dinas</td>
            <td colspan="2">{{ $assignment->maksud_perjalanan }}</td>
        </tr>
        <tr>
            <td class="text-center">5.</td>
            <td>Alat Angkut yang dipergunakan</td>
            <td colspan="2">{{ $assignment->alat_angkut ?: 'Kendaraan Dinas / Umum' }}</td>
        </tr>
        <tr>
            <td class="text-center" rowspan="2">6.</td>
            <td>a. Tempat Berangkat</td>
            <td class="text-center">a.</td>
            <td>{{ $assignment->tempat_berangkat }}</td>
        </tr>
        <tr>
            <td>b. Tempat Tujuan</td>
            <td class="text-center">b.</td>
            <td>{{ $assignment->tempat_tujuan }}</td>
        </tr>
        <tr>
            <td class="text-center" rowspan="3">7.</td>
            <td>a. Lamanya Perjalanan Dinas</td>
            <td class="text-center">a.</td>
            <td>{{ $lamaHari }} ({{ $lamaHari === 1 ? 'satu' : $lamaHari }}) hari</td>
        </tr>
        <tr>
            <td>b. Tanggal Berangkat</td>
            <td class="text-center">b.</td>
            <td>{{ $assignment->tanggal_berangkat?->locale('id')->translatedFormat('d F Y') }}</td>
        </tr>
        <tr>
            <td>c. Tanggal harus kembali</td>
            <td class="text-center">c.</td>
            <td>{{ $assignment->tanggal_kembali?->locale('id')->translatedFormat('d F Y') }}</td>
        </tr>
        <tr>
            <td class="text-center">8.</td>
            <td>Pengikut : Nama</td>
            <td colspan="2" style="padding: 0;">
                <table>
                    <tr>
                        <th style="border-top: 0; border-left: 0; width: 45%;">Nama</th>
                        <th style="border-top: 0; width: 25%;">Tanggal Lahir</th>
                        <th style="border-top: 0; border-right: 0; width: 30%;">Keterangan</th>
                    </tr>
                    @forelse ($pengikut as $p)
                        <tr>
                            <td style="border-left: 0; border-bottom: 0;">{{ $p['nama'] ?? '-' }}</td>
                            <td style="border-bottom: 0;">{{ $p['tanggal_lahir'] ?? '-' }}</td>
                            <td style="border-right: 0; border-bottom: 0;">{{ $p['keterangan'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td style="border-left: 0; border-bottom: 0;">-</td>
                            <td style="border-bottom: 0;">-</td>
                            <td style="border-right: 0; border-bottom: 0;">-</td>
                        </tr>
                    @endforelse
                </table>
            </td>
        </tr>
        <tr>
            <td class="text-center" rowspan="2">9.</td>
            <td>Pembebanan Anggaran<br>a. Instansi</td>
            <td class="text-center">a.</td>
            <td>{{ $instansiAnggaran }}</td>
        </tr>
        <tr>
            <td>b. Mata Anggaran</td>
            <td class="text-center">b.</td>
            <td>{{ $rekening?->kode_rekening ?? '5.1.02.04.01.0003' }}</td>
        </tr>
        <tr>
            <td class="text-center">10.</td>
            <td>Keterangan Lain-lain</td>
            <td colspan="2">{{ $peserta->keterangan_lain ?: '-' }}</td>
        </tr>
    </table>

    <table class="ttd-block" style="margin-top: 14px;">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%;">
                <table>
                    <tr>
                        <td style="width: 90px;">Dikeluarkan di</td>
                        <td style="width: 10px;">:</td>
                        <td>{{ $assignment->dikeluarkan_di ?: 'Garut' }}</td>
                    </tr>
                    <tr>
                        <td>Tanggal</td>
                        <td>:</td>
                        <td>{{ ($assignment->tanggal_penetapan ?? $assignment->tanggal_berangkat)?->locale('id')->translatedFormat('d F Y') }}</td>
                    </tr>
                </table>
                <div style="margin-top: 6px; font-weight: bold;">
                    {{ $assignment->pemberi_perintah_jabatan }},
                </div>
                <div style="height: 52px;"></div>
                <div class="font-bold underline">{{ $assignment->pemberi_perintah_nama }}</div>
                @if ($assignment->pemberi_perintah_nip)
                    <div>NIP. {{ $assignment->pemberi_perintah_nip }}</div>
                @endif
            </td>
        </tr>
    </table>
</div>
