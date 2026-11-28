<div class="doc-page">
    @include('pdf.sppd.kop-surat', ['assignment' => $assignment, 'logoDataUri' => $logoDataUri ?? null])

    <div class="doc-title">SURAT TUGAS</div>
    <div class="doc-subtitle">
        Nomor : {{ $assignment->nomor_surat_tugas ?: '        /Tarkid/SPPD/' . ($assignment->tanggal_berangkat?->format('m/Y') ?? date('m/Y')) }}
    </div>

    <p style="margin: 0 0 8px 0;">
        Yang bertanda tangan di bawah ini, {{ $assignment->pemberi_perintah_jabatan }} memberikan tugas kepada :
    </p>

    <table style="margin-bottom: 10px;">
        @foreach ($assignment->peserta as $idx => $peserta)
            @php
                $pegawai = $peserta->pegawai;
                $isNonAsn = ($pegawai?->status_kepegawaian ?? 'ASN') === 'NON_ASN' || empty($pegawai?->nip);
            @endphp
            <tr>
                <td style="width: 24px; vertical-align: top;">{{ $idx + 1 }}.</td>
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
                <td></td>
                <td>Pangkat / Golongan</td>
                <td>:</td>
                <td>{{ $isNonAsn ? '-' : ($pegawai?->golongan ?: '-') }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 6px;"></td>
                <td style="padding-bottom: 6px;">Jabatan</td>
                <td style="padding-bottom: 6px;">:</td>
                <td style="padding-bottom: 6px;">{{ $pegawai?->jabatan ?: '-' }}</td>
            </tr>
        @endforeach
    </table>

    <table style="margin-bottom: 8px;">
        <tr>
            <td style="width: 164px; vertical-align: top;">Untuk Melaksanakan Tugas</td>
            <td style="width: 12px; vertical-align: top;">:</td>
            <td>{{ $assignment->maksud_perjalanan }}</td>
        </tr>
    </table>

    <p style="margin: 4px 0 6px 0;">yang akan dilaksanakan pada :</p>

    <table style="margin-bottom: 12px;">
        <tr>
            <td style="width: 24px;"></td>
            <td style="width: 140px;">Hari</td>
            <td style="width: 12px;">:</td>
            <td>{{ $assignment->tanggal_berangkat?->locale('id')->translatedFormat('l') ?? '-' }}</td>
        </tr>
        <tr>
            <td></td>
            <td>Tanggal</td>
            <td>:</td>
            <td>
                {{ $assignment->tanggal_berangkat?->locale('id')->translatedFormat('d F Y') }}
                @if ($assignment->tanggal_kembali && ! $assignment->tanggal_kembali->isSameDay($assignment->tanggal_berangkat))
                    s/d {{ $assignment->tanggal_kembali->locale('id')->translatedFormat('d F Y') }}
                @endif
            </td>
        </tr>
        <tr>
            <td></td>
            <td>Tempat</td>
            <td>:</td>
            <td>{{ $assignment->tempat_tujuan }}</td>
        </tr>
    </table>

    <p style="margin: 0 0 18px 0;">
        Demikian surat tugas ini dibuat untuk dapat dilaksanakan sebagaimana mestinya.
    </p>

    <table class="ttd-block">
        <tr>
            <td style="width: 52%;"></td>
            <td style="width: 48%;">
                <table>
                    <tr>
                        <td style="width: 95px;">Ditetapkan di</td>
                        <td style="width: 10px;">:</td>
                        <td>{{ $assignment->dikeluarkan_di ?: 'Garut' }}</td>
                    </tr>
                    <tr>
                        <td>pada tanggal</td>
                        <td>:</td>
                        <td>{{ ($assignment->tanggal_penetapan ?? $assignment->tanggal_berangkat)?->locale('id')->translatedFormat('d F Y') }}</td>
                    </tr>
                </table>
                <div style="margin-top: 8px; font-weight: bold;">
                    {{ $assignment->pemberi_perintah_jabatan }},
                </div>
                <div style="height: 56px;"></div>
                <div class="font-bold underline">{{ $assignment->pemberi_perintah_nama }}</div>
                @if ($assignment->pemberi_perintah_nip)
                    <div>NIP. {{ $assignment->pemberi_perintah_nip }}</div>
                @endif
            </td>
        </tr>
    </table>
</div>
