@php
    $unit = $assignment->unitKerja;
    $isKelurahan = ($unit?->tipe ?? '') === 'KELURAHAN';
@endphp
<table class="kop-table">
    <tr>
        @if (!empty($logoDataUri))
            <td style="width: 65px; text-align: center; vertical-align: middle;">
                <img src="{{ $logoDataUri }}" alt="Logo Garut" style="width: 55px; height: auto;">
            </td>
        @endif
        <td style="text-align: center; vertical-align: middle;">
            <div class="kop-title-1">PEMERINTAH KABUPATEN GARUT</div>
            <div class="kop-title-2">KECAMATAN TAROGONG KIDUL</div>
            @if ($isKelurahan && $unit?->nama_unit)
                <div class="kop-title-2">{{ strtoupper($unit->nama_unit) }}</div>
            @endif
            <div class="kop-sub">
                {{ $unit?->kop_alamat ?: 'Jalan RSU dr. Slamet No.8' }}
                @if ($unit?->kop_telepon_email)
                    , {{ $unit->kop_telepon_email }}
                @endif
                @if ($unit?->kop_kode_pos)
                    — Kode Pos: {{ $unit->kop_kode_pos }}
                @endif
            </div>
        </td>
    </tr>
</table>
