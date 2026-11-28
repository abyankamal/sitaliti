@php
    $unit = $assignment->unitKerja;
    $pesertaCount = $assignment->peserta->count();
    $firstPeserta = $assignment->peserta->first();
    $firstPegawai = $firstPeserta?->pegawai;
    $firstIsNonAsn = ($firstPegawai?->status_kepegawaian ?? 'ASN') === 'NON_ASN' || empty($firstPegawai?->nip);

    $totalBiaya = (float) $assignment->peserta->sum(fn ($p) => $p->totalBiaya());
    if ($totalBiaya <= 0 && $assignment->kuitansi) {
        $totalBiaya = (float) $assignment->kuitansi->nominal_bruto;
    }

    $paNama = $unit?->pejabat_nama ?: $assignment->pemberi_perintah_nama;
    $paNip = $unit?->pejabat_nip ?: $assignment->pemberi_perintah_nip;
    $bendaharaNama = $unit?->bendahara_nama ?: '-';
    $bendaharaNip = $unit?->bendahara_nip;
    $tglTransaksi = ($assignment->kuitansi?->tanggal_transaksi ?? $assignment->tanggal_kembali ?? $assignment->tanggal_berangkat)?->locale('id')->translatedFormat('d F Y') ?? '-';
    $kodeRekening = $assignment->kuitansi?->rekening?->kode_rekening ?? $assignment->rekening?->kode_rekening ?? '5.1.02.04.01.0003';
@endphp
<div class="doc-page">
    <div class="doc-title" style="margin-bottom: 8px;">KWITANSI (TANDA PEMBAYARAN)</div>

    <table style="font-size: 9.5pt; margin-bottom: 8px;">
        <tr>
            <td style="width: 60%;"></td>
            <td style="width: 13%;">Tgl</td>
            <td style="width: 3%;">:</td>
            <td>{{ $tglTransaksi }}</td>
        </tr>
        <tr>
            <td></td>
            <td>No.</td>
            <td>:</td>
            <td>{{ $assignment->kuitansi?->nomor_kuitansi ?? '-' }}</td>
        </tr>
        <tr>
            <td></td>
            <td>No. Rek.</td>
            <td>:</td>
            <td>{{ $kodeRekening }}</td>
        </tr>
    </table>

    <table style="font-size: 10pt; margin-bottom: 12px;">
        <tr>
            <td style="width: 175px; padding: 4px 0; vertical-align: top; font-weight: bold;">SUDAH TERIMA DARI</td>
            <td style="width: 12px; padding: 4px 0; vertical-align: top;">:</td>
            <td style="padding: 4px 0;">Bendahara Pengeluaran {{ $unit?->nama_unit ?? 'Kecamatan Tarogong Kidul' }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; vertical-align: top; font-weight: bold;">BANYAKNYA UANG</td>
            <td style="padding: 4px 0; vertical-align: top;">:</td>
            <td style="padding: 4px 0; font-style: italic; font-weight: bold;">{{ $terbilang->format($totalBiaya) }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; vertical-align: top; font-weight: bold;">UANG SEJUMLAH</td>
            <td style="padding: 4px 0; vertical-align: top;">:</td>
            <td style="padding: 4px 0; font-weight: bold;">Rp {{ number_format($totalBiaya, 0, ',', '.') }},-</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; vertical-align: top; font-weight: bold;">YAITU UNTUK</td>
            <td style="padding: 4px 0; vertical-align: top;">:</td>
            <td style="padding: 4px 0;">
                Pembayaran biaya perjalanan dinas ke {{ $assignment->tempat_tujuan }} pada {{ $assignment->tanggal_berangkat?->locale('id')->translatedFormat('d F Y') }}
                ({{ $assignment->maksud_perjalanan }})
            </td>
        </tr>
    </table>

    <table class="ttd-block" style="font-size: 9.5pt; margin-top: 14px;">
        <tr>
            <td style="width: 34%; vertical-align: top;">
                <div>Mengetahui/Menyetujui:</div>
                <div class="font-bold">Pengguna Anggaran,</div>
                <div style="height: 52px;"></div>
                <div class="font-bold underline">{{ $paNama }}</div>
                @if ($paNip)<div>NIP. {{ $paNip }}</div>@endif
            </td>
            <td style="width: 33%; vertical-align: top;">
                <div>Lunas dibayar, {{ $tglTransaksi }}</div>
                <div class="font-bold">Bendahara Pengeluaran,</div>
                <div style="height: 52px;"></div>
                <div class="font-bold underline">{{ $bendaharaNama }}</div>
                @if ($bendaharaNip)<div>NIP. {{ $bendaharaNip }}</div>@endif
            </td>
            <td style="width: 33%; vertical-align: top;">
                <div>{{ $assignment->dikeluarkan_di ?: 'Garut' }}, {{ $tglTransaksi }}</div>
                <div class="font-bold">Yang menerima,</div>
                <div style="height: 52px;"></div>
                @if ($pesertaCount > 1)
                    <div class="font-bold underline">{{ $firstPegawai?->nama ?? '-' }} dkk.</div>
                    <div>(Daftar Nominatif Terlampir)</div>
                @else
                    <div class="font-bold underline">{{ $firstPegawai?->nama ?? '-' }}</div>
                    <div>{{ $firstIsNonAsn ? '-' : 'NIP. ' . $firstPegawai->nip }}</div>
                @endif
            </td>
        </tr>
    </table>
</div>
