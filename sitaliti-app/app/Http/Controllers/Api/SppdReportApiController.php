<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use App\Services\SppdReportExportService;
use App\Services\SppdReportService;
use App\Services\SppdScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class SppdReportApiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected SppdScheduleService $sppdService,
        protected SppdReportService $reportService,
        protected SppdReportExportService $exportService
    ) {}

    /**
     * Mengambil data rekap laporan SPPD terpaginasi, ringkasan KPI, dan opsi sub kegiatan.
     */
    public function index(Request $request): JsonResponse
    {
        $this->validateReportFilters($request);

        $user = $request->user();
        $filters = $request->only([
            'tanggal_dari',
            'tanggal_sampai',
            'kode_sub_kegiatan',
            'pegawai_id',
            'unit_kerja_id',
            'status',
            'search',
        ]);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return $this->successResponse([
            'report' => $this->reportService->getPaginatedReport($user, $filters, $perPage),
            'kpi' => $this->reportService->getSummaryKpi($user, $filters),
            'sub_kegiatan_options' => $this->reportService->getSubKegiatanOptions($user),
        ], 'Rekap laporan SPPD berhasil diambil');
    }

    /**
     * Mengekspor rekap laporan SPPD dalam format Excel (.xlsx), CSV, atau PDF.
     */
    public function export(Request $request): Response
    {
        $this->validateReportFilters($request, withFormat: true);

        $user = $request->user();
        $filters = $request->only([
            'tanggal_dari',
            'tanggal_sampai',
            'kode_sub_kegiatan',
            'pegawai_id',
            'unit_kerja_id',
            'status',
            'search',
        ]);

        $format = strtolower((string) $request->input('format', 'xlsx'));

        try {
            return match ($format) {
                'csv' => $this->exportService->streamCsvExport($user, $filters),
                'pdf' => $this->exportService->downloadPdfExport($user, $filters),
                default => $this->exportService->streamExcelExport($user, $filters),
            };
        } catch (ValidationException $e) {
            return $this->errorResponse('EXPORT_LIMIT_EXCEEDED', $e->getMessage(), $e->errors(), 422);
        }
    }

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

    /**
     * Validasi parameter filter tanggal dan format ekspor secara kondisional.
     */
    private function validateReportFilters(Request $request, bool $withFormat = false): void
    {
        $rules = [
            'tanggal_dari' => ['nullable', 'date'],
            'tanggal_sampai' => ['nullable', 'date'],
            'pegawai_id' => ['nullable', 'integer'],
            'unit_kerja_id' => ['nullable', 'integer'],
        ];

        if ($request->filled('tanggal_dari')) {
            $rules['tanggal_sampai'][] = 'after_or_equal:tanggal_dari';
        }

        if ($withFormat) {
            $rules['format'] = ['nullable', 'string', Rule::in(['xlsx', 'csv', 'pdf'])];
        }

        $request->validate($rules);
    }
}
