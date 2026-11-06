<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class BudgetApiController extends Controller
{
    use ApiResponseTrait;

    /**
     * Endpoint ringkasan anggaran untuk Dashboard.vue (budgetService.getDashboardSummary)
     */
    public function summary(): JsonResponse
    {
        // Akan dihubungkan ke implementasi BudgetReservationService
        return $this->successResponse([], 'Ringkasan RAK berhasil dimuat');
    }

    /**
     * Endpoint daftar 6 unit kerja (budgetService.getUnits)
     */
    public function units(): JsonResponse
    {
        return $this->successResponse([], 'Daftar unit kerja berhasil dimuat');
    }
}
