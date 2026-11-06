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

        $subActivities = $query->select(
            'id',
            'unit_kerja_id',
            'kode_sub_kegiatan',
            'nama_sub_kegiatan',
            'kode_rekening',
            'nama_rekening',
            'pagu_perubahan',
            'kategori_belanja'
        )
            ->orderBy('kode_sub_kegiatan')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'unit_id' => $r->unit_kerja_id,
                    'code' => $r->kode_sub_kegiatan,
                    'name' => $r->nama_sub_kegiatan,
                    'kode_rekening' => $r->kode_rekening,
                    'nama_rekening' => $r->nama_rekening,
                    'pagu' => (float) $r->pagu_perubahan,
                    'category' => $r->kategori_belanja,
                ];
            });

        return $this->successResponse($subActivities, 'Daftar sub kegiatan dan rekening berhasil dimuat');
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
