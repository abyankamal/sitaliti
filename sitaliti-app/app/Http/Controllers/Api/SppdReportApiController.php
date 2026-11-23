<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use App\Services\SppdScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SppdReportApiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected SppdScheduleService $sppdService
    ) {}

    /**
     * Memeriksa potensi bentrok jadwal SPPD secara real-time untuk form input/edit kuitansi.
     */
    public function checkConflict(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exclude_kuitansi_id' => ['nullable', 'integer'],
            'sppd_peserta' => ['nullable', 'array'],
            'sppd_peserta.*.pegawai_id' => ['required_with:sppd_peserta', 'integer', 'exists:pegawai,id'],
            'sppd_peserta.*.tanggal_berangkat' => ['required_with:sppd_peserta', 'date'],
            'sppd_peserta.*.tanggal_pulang' => ['required_with:sppd_peserta', 'date', 'after_or_equal:sppd_peserta.*.tanggal_berangkat'],
        ]);

        $conflicts = $this->sppdService->findConflicts(
            $validated['sppd_peserta'] ?? [],
            isset($validated['exclude_kuitansi_id']) ? (int) $validated['exclude_kuitansi_id'] : null
        );

        return $this->successResponse([
            'has_conflict' => ! empty($conflicts),
            'conflicts' => $conflicts,
        ], 'Pemeriksaan jadwal SPPD selesai');
    }
}
