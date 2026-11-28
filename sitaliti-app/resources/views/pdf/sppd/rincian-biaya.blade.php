@php
    $unit = $assignment->unitKerja;
    $pegawai = $peserta->pegawai;
    $isNonAsn = ($pegawai?->status_kepegawaian ?? 'ASN') === 'NON_ASN' || empty($pegawai?->nip);
    $totalPeserta = $peserta->totalBiaya();
    $dibayarSemula = (float) ($peserta->dibayar_semula ?? 0);
    $sisa = $totalPeserta - $dibayarSemula;

    $paNama = $unit?->pejabat_nama ?: $assignment->pemberi_perintah_nama;
    $paNip = $unit?->pejabat_nip ?: $assignment->pemberi_perintah_nip;
    $bendaharaNama = $unit?->bendahara_nama ?: '-';
    $bendaharaNip = $unit?->bendahara_nip;
    $tglTransaksi = ($assignment->kuitansi?->tanggal_transaksi ?? $assignment->tanggal_kembali ?? $assignment->tanggal_berangkat)?->locale('id')->translatedFormat('d F Y') ?? '-';
@endphp
<div class="doc-page">
    <div class="doc-title" style="margin-bottom: 8px;">RINCIAN BIAYA PERJALANAN DINAS</div>

    <table style="font-size: 9.5pt; margin-bottom: 8px;">
        <tr>
            <td style="width: 150px;">Lampiran SPD Nomor</td>
            <td style="width: 10px;">:</td>
            <td>{{ $peserta->nomor_spd ?: '-' }}</td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>:</td>
            <td>{{ $assignment->tanggal_berangkat?->locale('id')->translatedFormat('d F Y') ?? '-' }}</td>
        </tr>
    </table>

    <table class="bordered" style="font-size: 9.5pt; margin-bottom: 8px;">
        <thead>
            <tr style="background-color: #f3f4f6;">
                <th style="width: 30px;" class="text-center">No</th>
                <th>PERINCIAN BIAYA</th>
                <th style="width: 130px;" class="text-right">JUMLAH (Rp.)</th>
                <th style="width: 160px;">KETERANGAN</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($peserta->biayaItems as $idx => $biaya)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $biaya->uraian_biaya }}</td>
                    <td class="text-right">{{ number_format((float) $biaya->nominal, 0, ',', '.') }}</td>
                    <td>{{ $biaya->keterangan ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td class="text-center">1</td>
                    <td>Uang Makan harian</td>
                    <td class="text-right">-</td>
                    <td>-</td>
                </tr>
                <tr>
                    <td class="text-center">2</td>
                    <td>Uang Transpor Lokal</td>
                    <td class="text-right">-</td>
                    <td>-</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="2" class="text-right font-bold">JUMLAH</td>
                <td class="text-right font-bold">{{ number_format($totalPeserta, 0, ',', '.') }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="4">
                    <strong>Terbilang :</strong> <em>{{ $terbilang->format($totalPeserta) }}</em>
                </td>
            </tr>
        </tbody>
    </table>

    <table class="ttd-block" style="font-size: 9.5pt; margin-top: 10px; margin-bottom: 14px;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <div>Telah dibayar sejumlah</div>
                <div class="font-bold">Rp. {{ number_format($totalPeserta, 0, ',', '.') }},-</div>
                <div style="margin-top: 4px;" class="font-bold">Bendahara Pengeluaran,</div>
                <div style="height: 46px;"></div>
                <div class="font-bold underline">{{ $bendaharaNama }}</div>
                @if ($bendaharaNip)<div>NIP. {{ $bendaharaNip }}</div>@endif
            </td>
            <td style="width: 50%; vertical-align: top;">
                <div>{{ $assignment->dikeluarkan_di ?: 'Garut' }}, {{ $tglTransaksi }}</div>
                <div>Telah menerima jumlah uang sebesar</div>
                <div class="font-bold">Rp. {{ number_format($totalPeserta, 0, ',', '.') }},-</div>
                <div style="margin-top: 4px;" class="font-bold">Yang Menerima,</div>
                <div style="height: 46px;"></div>
                <div class="font-bold underline">{{ $pegawai?->nama ?? '-' }}</div>
                <div>{{ $isNonAsn ? '-' : 'NIP. ' . $pegawai->nip }}</div>
            </td>
        </tr>
    </table>

    <div style="border-top: 1px solid #000000; padding-top: 8px;" class="ttd-block">
        <div class="font-bold underline" style="text-align: center; margin-bottom: 8px;">PERHITUNGAN SPD RAMPUNG</div>
        <table style="font-size: 9.5pt; margin-bottom: 10px;">
            <tr>
                <td style="width: 210px;">Ditetapkan sejumlah</td>
                <td style="width: 25px;">: Rp.</td>
                <td style="width: 120px;" class="text-right font-bold">{{ number_format($totalPeserta, 0, ',', '.') }}</td>
                <td></td>
            </tr>
            <tr>
                <td>Yang telah dibayar semula</td>
                <td>: Rp.</td>
                <td class="text-right">{{ $dibayarSemula > 0 ? number_format($dibayarSemula, 0, ',', '.') : '-' }}</td>
                <td></td>
            </tr>
            <tr>
                <td>Sisa kurang / lebih</td>
                <td>: Rp.</td>
                <td class="text-right font-bold">{{ $sisa !== 0.0 ? number_format($sisa, 0, ',', '.') : '-' }}</td>
                <td></td>
            </tr>
        </table>

        <table>
            <tr>
                <td style="width: 55%;"></td>
                <td style="width: 45%;">
                    <div class="font-bold">Pengguna Anggaran,</div>
                    <div style="height: 46px;"></div>
                    <div class="font-bold underline">{{ $paNama }}</div>
                    @if ($paNip)<div>NIP. {{ $paNip }}</div>@endif
                </td>
            </tr>
        </table>
    </div>
</div>
