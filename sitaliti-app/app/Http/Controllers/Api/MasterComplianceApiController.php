<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use App\Models\MasterSbmItem;
use App\Models\MasterTarifPajak;
use App\Models\PengaturanKepatuhan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterComplianceApiController extends Controller
{
    use ApiResponseTrait;

    /**
     * Mengambil ringkasan pengaturan kepatuhan, tarif pajak, dan item SBM per tahun anggaran.
     */
    public function index(Request $request): JsonResponse
    {
        $tahun = (int) $request->input('tahun_anggaran', date('Y'));

        return $this->successResponse([
            'tahun_anggaran' => $tahun,
            'settings' => PengaturanKepatuhan::orderBy('key')->get(),
            'tax_rates' => MasterTarifPajak::where('tahun_anggaran', $tahun)
                ->orderBy('kode_pajak')
                ->get(),
            'sbm_items' => MasterSbmItem::where('tahun_anggaran', $tahun)
                ->orderBy('kategori_belanja')
                ->orderBy('kode_sbm')
                ->get(),
        ], 'Data kepatuhan berhasil dimuat.');
    }

    /**
     * Memperbarui toggle pengaturan kepatuhan (ntpn_gate_spp_gu, ntpn_gate_lpj_bpp, sbm_soft_warning_enabled).
     */
    public function updateSetting(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'in:ntpn_gate_spp_gu,ntpn_gate_lpj_bpp,sbm_soft_warning_enabled'],
            'value' => ['required', 'boolean'],
        ]);

        $setting = PengaturanKepatuhan::setValue(
            $validated['key'],
            (bool) $validated['value'],
            $request->user()?->id
        );

        return $this->successResponse($setting, 'Pengaturan kepatuhan berhasil diperbarui.');
    }

    /**
     * Menyimpan atau memperbarui tarif pajak untuk tahun anggaran tertentu.
     */
    public function upsertTaxRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tahun_anggaran' => ['required', 'integer', 'min:2020', 'max:2100'],
            'kode_pajak' => ['required', 'string', 'max:40'],
            'nama_pajak' => ['required', 'string', 'max:150'],
            'tarif' => ['required', 'numeric', 'min:0', 'max:1'],
            'ambang_batas' => ['nullable', 'numeric', 'min:0'],
            'nominal_tetap' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rate = MasterTarifPajak::updateOrCreate(
            [
                'tahun_anggaran' => (int) $validated['tahun_anggaran'],
                'kode_pajak' => strtoupper(trim($validated['kode_pajak'])),
            ],
            [
                'nama_pajak' => trim($validated['nama_pajak']),
                'tarif' => $validated['tarif'],
                'ambang_batas' => $validated['ambang_batas'] ?? null,
                'nominal_tetap' => $validated['nominal_tetap'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]
        );

        return $this->successResponse($rate, 'Tarif pajak berhasil disimpan.');
    }

    /**
     * Menyimpan atau memperbarui item Standar Biaya Masukan (SBM/SSH).
     */
    public function upsertSbmItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tahun_anggaran' => ['required', 'integer', 'min:2020', 'max:2100'],
            'kategori_belanja' => ['required', 'string', 'in:B1_ATK,B2_MAMIN,B3_HONOR,B4_PERJADIN,B5_PEMELIHARAAN,B6_MODAL'],
            'kode_sbm' => ['required', 'string', 'max:40'],
            'uraian_standar' => ['required', 'string', 'max:255'],
            'satuan' => ['required', 'string', 'max:40'],
            'harga_maksimal' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $item = MasterSbmItem::updateOrCreate(
            [
                'tahun_anggaran' => (int) $validated['tahun_anggaran'],
                'kode_sbm' => strtoupper(trim($validated['kode_sbm'])),
            ],
            [
                'kategori_belanja' => $validated['kategori_belanja'],
                'uraian_standar' => trim($validated['uraian_standar']),
                'satuan' => trim($validated['satuan']),
                'harga_maksimal' => $validated['harga_maksimal'],
                'is_active' => $validated['is_active'] ?? true,
            ]
        );

        return $this->successResponse($item, 'Item SBM/SSH berhasil disimpan.');
    }
}
