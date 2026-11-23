<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use App\Services\RekapBelanjaOperasiExportService;
use App\Services\RekapBelanjaOperasiReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class RekapBelanjaOperasiReportApiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected RekapBelanjaOperasiReportService $reportService,
        protected RekapBelanjaOperasiExportService $exportService
    ) {}

    /**
     * Menampilkan ringkasan KPI, tabel rekap per pos Belanja Operasi, dan opsi filter.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);
        $data = $this->reportService->getReport($request->user(), $filters);

        return $this->successResponse($data, 'Rekap belanja operasi berhasil diambil');
    }

    /**
     * Menampilkan rincian kode rekening dan kuitansi SP2D untuk satu pos rekap.
     */
    public function drilldown(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);
        $request->validate([
            'pos_key' => ['required', 'string', 'max:32'],
        ]);

        $data = $this->reportService->getDrilldown(
            $request->user(),
            $filters,
            (string) $request->input('pos_key')
        );

        return $this->successResponse($data, 'Rincian pos rekap belanja operasi berhasil diambil');
    }

    /**
     * Mengekspor Rekap Belanja Operasi ke Excel (.xlsx) atau PDF.
     */
    public function export(Request $request): Response
    {
        $filters = $this->validateFilters($request, withFormat: true);
        $format = strtolower((string) $request->input('format', 'xlsx'));

        if ($format === 'pdf') {
            return $this->exportService->downloadPdfExport($request->user(), $filters);
        }

        return $this->exportService->streamXlsxExport($request->user(), $filters);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFilters(Request $request, bool $withFormat = false): array
    {
        $rules = [
            'master_rekap_id' => ['nullable', 'string', 'max:32'],
            'unit_kerja_id' => ['nullable', 'integer', 'exists:master_unit_kerja,id'],
            'bulan_dari' => ['nullable', 'integer', 'min:1', 'max:12'],
            'bulan_sampai' => ['nullable', 'integer', 'min:1', 'max:12'],
            'search' => ['nullable', 'string', 'max:150'],
        ];

        if ($withFormat) {
            $rules['format'] = ['nullable', 'string', Rule::in(['xlsx', 'pdf'])];
        }

        $request->validate($rules);

        return $request->only([
            'master_rekap_id',
            'unit_kerja_id',
            'bulan_dari',
            'bulan_sampai',
            'search',
        ]);
    }
}
