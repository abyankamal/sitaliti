@php
    $unit = $assignment->unitKerja;
    $paNama = $unit?->pejabat_nama ?: $assignment->pemberi_perintah_nama;
    $paNip = $unit?->pejabat_nip ?: $assignment->pemberi_perintah_nip;
    $bendaharaNama = $unit?->bendahara_nama ?: '-';
    $bendaharaNip = $unit?->bendahara_nip;
    $grandTotal = (float) $assignment->peserta->sum(fn ($p) => $p->totalBiaya());
    $tglTransaksi = ($assignment->kuitansi?->tanggal_transaksi ?? $assignment->tanggal_kembali ?? $assignment->tanggal_berangkat)?->locale('id')->translatedFormat('d F Y') ?? '-';
@endphp
<div class="doc-page">
    @include('pdf.sppd.kop-surat', ['assignment' => $assignment, 'logoDataUri' => $logoDataUri ?? null])

    <div class="doc-title">DAFTAR NOMINATIF PENERIMAAN BIAYA PERJALANAN DINAS</div>
    <div class="doc-subtitle">
        Surat Tugas Nomor: {{ $assignment->nomor_surat_tugas ?: '-' }} | Tujuan: {{ $assignment->tempat_tujuan }}
    </div>

    <table class="bordered" style="font-size: 9pt; margin-bottom: 10px;">
        <thead>
            <tr style="background-color: #f3f4f6;">
                <th style="width: 26px;" class="text-center">No</th>
                <th>Nama / NIP</th>
                <th style="width: 130px;">Jabatan / Golongan</th>
                <th style="width: 110px;">Nomor SPD</th>
                <th style="width: 105px;" class="text-right">Jumlah Diterima (Rp)</th>
                <th style="width: 110px;" class="text-center">Tanda Tangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($assignment->peserta as $idx => $peserta)
                @php
                    $pegawai = $peserta->pegawai;
                    $isNonAsn = ($pegawai?->status_kepegawaian ?? 'ASN') === 'NON_ASN' || empty($pegawai?->nip);
                    $subtotal = $peserta->totalBiaya();
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <div class="font-bold">{{ $pegawai?->nama ?? '-' }}</div>
                        <div>{{ $isNonAsn ? '-' : 'NIP. ' . $pegawai->nip }}</div>
                    </td>
                    <td>
                        <div>{{ $pegawai?->jabatan ?: '-' }}</div>
                        <div>{{ $isNonAsn ? '-' : ($pegawai?->golongan ?: '-') }}</div>
                    </td>
                    <td>{{ $peserta->nomor_spd ?: '-' }}</td>
                    <td class="text-right">{{ number_format($subtotal, 0, ',', '.') }}</td>
                    <td style="height: 36px; vertical-align: middle;">{{ $idx + 1 }}. ....................</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="4" class="text-right font-bold">TOTAL DITERIMA</td>
                <td class="text-right font-bold">{{ number_format($grandTotal, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div style="font-size: 9.5pt; margin-bottom: 14px;">
        <strong>Terbilang :</strong> <em>{{ $terbilang->format($grandTotal) }}</em>
    </div>

    <table class="ttd-block" style="font-size: 9.5pt;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <div>Mengetahui/Menyetujui:</div>
                <div class="font-bold">Pengguna Anggaran,</div>
                <div style="height: 50px;"></div>
                <div class="font-bold underline">{{ $paNama }}</div>
                @if ($paNip)<div>NIP. {{ $paNip }}</div>@endif
            </td>
            <td style="width: 50%; vertical-align: top;">
                <div>{{ $assignment->dikeluarkan_di ?: 'Garut' }}, {{ $tglTransaksi }}</div>
                <div class="font-bold">Bendahara Pengeluaran,</div>
                <div style="height: 50px;"></div>
                <div class="font-bold underline">{{ $bendaharaNama }}</div>
                @if ($bendaharaNip)<div>NIP. {{ $bendaharaNip }}</div>@endif
            </td>
        </tr>
    </table>
</div>
