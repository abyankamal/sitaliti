@php
    $firstPeserta = $assignment->peserta->first();
    $firstPegawai = $firstPeserta?->pegawai;
    $firstIsNonAsn = ($firstPegawai?->status_kepegawaian ?? 'ASN') === 'NON_ASN' || empty($firstPegawai?->nip);
@endphp
<div class="doc-page">
    @include('pdf.sppd.kop-surat', ['assignment' => $assignment, 'logoDataUri' => $logoDataUri ?? null])

    <div class="doc-title" style="margin-bottom: 12px;">LAPORAN PERJALANAN DINAS</div>

    <p style="margin: 0 0 6px 0;">Yang bertanda tangan di bawah ini :</p>

    <table style="margin-bottom: 10px;">
        @foreach ($assignment->peserta as $idx => $peserta)
            @php
                $pegawai = $peserta->pegawai;
                $isNonAsn = ($pegawai?->status_kepegawaian ?? 'ASN') === 'NON_ASN' || empty($pegawai?->nip);
            @endphp
            <tr>
                <td style="width: 24px; vertical-align: top;">{{ $assignment->peserta->count() > 1 ? ($idx + 1) . '.' : '' }}</td>
                <td style="width: 140px; vertical-align: top;">Nama</td>
                <td style="width: 12px; vertical-align: top;">:</td>
                <td class="font-bold">{{ $pegawai?->nama ?? '-' }}</td>
            </tr>
            <tr>
                <td></td>
                <td>NIP</td>
                <td>:</td>
                <td>{{ $isNonAsn ? '-' : $pegawai->nip }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 6px;"></td>
                <td style="padding-bottom: 6px;">Jabatan</td>
                <td style="padding-bottom: 6px;">:</td>
                <td style="padding-bottom: 6px;">{{ $pegawai?->jabatan ?: '-' }}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin: 0 0 6px 0;">Dengan ini melaporkan hasil Perjalanan Dinas :</p>

    <table style="margin-bottom: 14px;">
        <tr>
            <td style="width: 164px; vertical-align: top;">Dari</td>
            <td style="width: 12px; vertical-align: top;">:</td>
            <td>{{ $assignment->tempat_berangkat }}</td>
        </tr>
        <tr>
            <td style="vertical-align: top;">Tempat Tujuan</td>
            <td style="vertical-align: top;">:</td>
            <td>{{ $assignment->tempat_tujuan }}</td>
        </tr>
        <tr>
            <td style="vertical-align: top;">Tanggal berangkat</td>
            <td style="vertical-align: top;">:</td>
            <td>{{ $assignment->tanggal_berangkat?->locale('id')->translatedFormat('d F Y') ?? '-' }}</td>
        </tr>
        <tr>
            <td style="vertical-align: top;">Tanggal kembali</td>
            <td style="vertical-align: top;">:</td>
            <td>{{ $assignment->tanggal_kembali?->locale('id')->translatedFormat('d F Y') ?? '-' }}</td>
        </tr>
        <tr>
            <td style="vertical-align: top; padding-top: 6px;">Hasil Tugas / Kegiatan</td>
            <td style="vertical-align: top; padding-top: 6px;">:</td>
            <td style="padding-top: 6px; white-space: pre-line;">{{ $assignment->laporan_hasil ?: '-' }}</td>
        </tr>
    </table>

    <p style="margin: 0 0 20px 0;">
        Demikian laporan ini saya sampaikan, atas perhatiannya kami ucapkan terima kasih.
    </p>

    <table class="ttd-block">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%;">
                <div>{{ $assignment->dikeluarkan_di ?: 'Garut' }}, {{ ($assignment->tanggal_kembali ?? $assignment->tanggal_berangkat)?->locale('id')->translatedFormat('d F Y') }}</div>
                <div class="font-bold">Yang melaksanakan tugas,</div>
                <div style="height: 52px;"></div>
                <div class="font-bold underline">{{ $firstPegawai?->nama ?? '-' }}</div>
                <div>{{ $firstIsNonAsn ? '-' : 'NIP. ' . $firstPegawai->nip }}</div>
            </td>
        </tr>
    </table>
</div>
