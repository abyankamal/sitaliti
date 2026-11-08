<?php

namespace App\Http\Controllers\Api;

use App\Contracts\BudgetReservationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\UnitKerja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetApiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected BudgetReservationServiceInterface $budgetService
    ) {}

    /**
     * Endpoint ringkasan anggaran untuk Dashboard.vue (budgetService.getDashboardSummary)
     */
    public function summary(): JsonResponse
    {
        $totalPagu = (float) AnggaranRekening::sum('pagu_perubahan');
        $totalTerkunci = (float) AnggaranKasBulanan::sum('terkunci_bulan');
        $totalRealisasi = (float) AnggaranKasBulanan::sum('realisasi_bulan');
        $sisaPagu = max(0.0, $totalPagu - $totalTerkunci - $totalRealisasi);

        return $this->successResponse([
            'total_pagu' => $totalPagu,
            'total_terkunci' => $totalTerkunci,
            'total_realisasi' => $totalRealisasi,
            'sisa_pagu' => $sisaPagu,
            'persentase_realisasi' => $totalPagu > 0 ? round(($totalRealisasi / $totalPagu) * 100, 2) : 0,
        ], 'Ringkasan RAK berhasil dimuat');
    }

    /**
     * Endpoint daftar 6 unit kerja (budgetService.getUnits)
     */
    public function units(): JsonResponse
    {
        $units = UnitKerja::where('is_active', true)->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'code' => $u->kode_unit,
                'name' => $u->nama_unit,
                'type' => $u->tipe,
            ];
        });

        return $this->successResponse($units, 'Daftar unit kerja berhasil dimuat');
    }

    /**
     * Endpoint daftar subkegiatan dan rekening belanja berdasarkan unit_id (budgetService.getSubActivitiesByUnit)
     */
    public function subActivities(Request $request): JsonResponse
    {
        $unitId = $request->input('unit_id');
        $query = AnggaranRekening::query();

        if ($unitId) {
            $query->where('unit_kerja_id', $unitId);
        }

        $records = $query->orderBy('kode_sub_kegiatan')
            ->orderBy('kode_rekening')
            ->get();

        // Group by subkegiatan agar sesuai struktur RAKSelectorModal (subkegiatan memiliki anak array rekening)
        $grouped = $records->groupBy('kode_sub_kegiatan')->map(function ($items) {
            $first = $items->first();

            return [
                'id' => $first->kode_sub_kegiatan, // ID subkegiatan untuk selector
                'unit_id' => $first->unit_kerja_id,
                'code' => $first->kode_sub_kegiatan,
                'name' => $first->nama_sub_kegiatan,
                'category' => $first->kategori_belanja ?? 'B.1',
                'rekening' => $items->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'code' => $r->kode_rekening,
                        'name' => $r->nama_rekening,
                        'kategori' => $r->kategori_belanja ?? 'B.1',
                        'pagu_murni' => (float) $r->pagu_murni,
                        'pagu_perubahan' => (float) $r->pagu_perubahan,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return $this->successResponse($grouped, 'Daftar sub kegiatan dan rekening berhasil dimuat');
    }

    /**
     * Endpoint alokasi RAK bulanan dan akumulasi carry-over (budgetService.getRakContext)
     */
    public function rakContext(Request $request, int|string $rekeningId): JsonResponse
    {
        $month = (int) ($request->input('month', date('n')));
        $data = $this->budgetService->getPopupAllocationData((string) $rekeningId, $month);

        return $this->successResponse($data, 'Data kuota RAK bulanan berhasil dimuat');
    }
}
