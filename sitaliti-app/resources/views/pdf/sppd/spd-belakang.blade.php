@php
    $tglBerangkat = $assignment->tanggal_berangkat?->locale('id')->translatedFormat('d F Y') ?? '-';
    $tglKembali = $assignment->tanggal_kembali?->locale('id')->translatedFormat('d F Y') ?? '-';
    $paNama = $assignment->unitKerja?->pejabat_nama ?: $assignment->pemberi_perintah_nama;
    $paNip = $assignment->unitKerja?->pejabat_nip ?: $assignment->pemberi_perintah_nip;
@endphp
<div class="doc-page">
    <table class="bordered" style="font-size: 8.2pt; line-height: 1.2;">
        {{-- Blok I --}}
        <tr class="ttd-block">
            <td style="width: 50%; border-top: none; border-left: none;"></td>
            <td style="width: 50%;">
                <table>
                    <tr><td style="border: 0; padding: 0; width: 20px;">I.</td><td style="border: 0; padding: 0; width: 95px;">Berangkat dari</td><td style="border: 0; padding: 0; width: 8px;">:</td><td style="border: 0; padding: 0;">{{ $assignment->tempat_berangkat }}</td></tr>
                    <tr><td style="border: 0; padding: 0;"></td><td colspan="3" style="border: 0; padding: 0;">(tempat kedudukan)</td></tr>
                    <tr><td style="border: 0; padding: 0;"></td><td style="border: 0; padding: 0;">Ke</td><td style="border: 0; padding: 0;">:</td><td style="border: 0; padding: 0;">{{ $assignment->tempat_tujuan }}</td></tr>
                    <tr><td style="border: 0; padding: 0;"></td><td style="border: 0; padding: 0;">Pada Tanggal</td><td style="border: 0; padding: 0;">:</td><td style="border: 0; padding: 0;">{{ $tglBerangkat }}</td></tr>
                </table>
                <div style="margin-top: 4px;">Pengguna Anggaran,</div>
                <div style="height: 36px;"></div>
                <div class="font-bold underline">{{ $paNama }}</div>
                @if ($paNip)<div>NIP. {{ $paNip }}</div>@endif
            </td>
        </tr>

        {{-- Blok II --}}
        <tr class="ttd-block">
            <td>
                <table>
                    <tr><td style="border: 0; padding: 0; width: 22px;">II.</td><td style="border: 0; padding: 0; width: 85px;">Tiba di</td><td style="border: 0; padding: 0; width: 8px;">:</td><td style="border: 0; padding: 0;">{{ $assignment->tempat_tujuan }}</td></tr>
                    <tr><td style="border: 0; padding: 0;"></td><td style="border: 0; padding: 0;">Pada Tanggal</td><td style="border: 0; padding: 0;">:</td><td style="border: 0; padding: 0;">{{ $tglBerangkat }}</td></tr>
                </table>
                <div style="margin-top: 4px;">Kepala / Pejabat Setempat,</div>
                <div style="height: 38px;"></div>
                <div>(.......................................................)</div>
                <div>NIP.</div>
            </td>
            <td>
                <table>
                    <tr><td style="border: 0; padding: 0; width: 20px;"></td><td style="border: 0; padding: 0; width: 95px;">Berangkat dari</td><td style="border: 0; padding: 0; width: 8px;">:</td><td style="border: 0; padding: 0;">{{ $assignment->tempat_tujuan }}</td></tr>
                    <tr><td style="border: 0; padding: 0;"></td><td style="border: 0; padding: 0;">Ke</td><td style="border: 0; padding: 0;">:</td><td style="border: 0; padding: 0;">{{ $assignment->tempat_berangkat }}</td></tr>
                    <tr><td style="border: 0; padding: 0;"></td><td style="border: 0; padding: 0;">Pada Tanggal</td><td style="border: 0; padding: 0;">:</td><td style="border: 0; padding: 0;">{{ $tglKembali }}</td></tr>
                </table>
                <div style="margin-top: 4px;">Kepala / Pejabat Setempat,</div>
                <div style="height: 34px;"></div>
                <div>(.......................................................)</div>
                <div>NIP.</div>
            </td>
        </tr>

        {{-- Blok III - V --}}
        @foreach (['III.', 'IV.', 'V.'] as $roman)
            <tr class="ttd-block">
                <td>
                    <table>
                        <tr><td style="border: 0; padding: 0; width: 22px;">{{ $roman }}</td><td style="border: 0; padding: 0; width: 85px;">Tiba di</td><td style="border: 0; padding: 0; width: 8px;">:</td><td style="border: 0; padding: 0;"></td></tr>
                        <tr><td style="border: 0; padding: 0;"></td><td style="border: 0; padding: 0;">Pada Tanggal</td><td style="border: 0; padding: 0;">:</td><td style="border: 0; padding: 0;"></td></tr>
                    </table>
                    <div style="height: 44px;"></div>
                    <div>(.......................................................)</div>
                    <div>NIP.</div>
                </td>
                <td>
                    <table>
                        <tr><td style="border: 0; padding: 0; width: 20px;"></td><td style="border: 0; padding: 0; width: 95px;">Berangkat dari</td><td style="border: 0; padding: 0; width: 8px;">:</td><td style="border: 0; padding: 0;"></td></tr>
                        <tr><td style="border: 0; padding: 0;"></td><td style="border: 0; padding: 0;">Ke</td><td style="border: 0; padding: 0;">:</td><td style="border: 0; padding: 0;"></td></tr>
                        <tr><td style="border: 0; padding: 0;"></td><td style="border: 0; padding: 0;">Pada Tanggal</td><td style="border: 0; padding: 0;">:</td><td style="border: 0; padding: 0;"></td></tr>
                    </table>
                    <div style="height: 36px;"></div>
                    <div>(.......................................................)</div>
                    <div>NIP.</div>
                </td>
            </tr>
        @endforeach

        {{-- Blok VI --}}
        <tr class="ttd-block">
            <td>
                <table>
                    <tr><td style="border: 0; padding: 0; width: 22px;">VI.</td><td style="border: 0; padding: 0; width: 85px;">Tiba kembali di</td><td style="border: 0; padding: 0; width: 8px;">:</td><td style="border: 0; padding: 0;">{{ $assignment->tempat_berangkat }}</td></tr>
                    <tr><td style="border: 0; padding: 0;"></td><td style="border: 0; padding: 0;">Pada Tanggal</td><td style="border: 0; padding: 0;">:</td><td style="border: 0; padding: 0;">{{ $tglKembali }}</td></tr>
                </table>
                <div style="margin-top: 6px; font-size: 7.8pt;">
                    Telah diperiksa, dengan keterangan bahwa perjalanan tersebut atas perintahnya dan semata-mata untuk kepentingan jabatan dalam waktu yang sesingkat-singkatnya.
                </div>
            </td>
            <td>
                <div>Pengguna Anggaran,</div>
                <div style="height: 42px;"></div>
                <div class="font-bold underline">{{ $paNama }}</div>
                @if ($paNip)<div>NIP. {{ $paNip }}</div>@endif
            </td>
        </tr>
        <tr>
            <td colspan="2" style="font-size: 7.8pt;">
                <strong>VII. Catatan Lain-lain</strong><br>
                <strong>VIII. PERHATIAN :</strong> PPK yang menerbitkan SPD, pegawai yang melakukan perjalanan dinas, para pejabat yang mengesahkan tanggal berangkat/tiba, serta bendahara pengeluaran bertanggung jawab berdasarkan peraturan-peraturan Keuangan Negara apabila negara menderita rugi akibat kesalahan, kelalaian, dan kealpaannya.
            </td>
        </tr>
    </table>
</div>
