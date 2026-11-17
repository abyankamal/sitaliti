<?php

namespace App\Http\Controllers\Api;

use App\Contracts\SettlementServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class SettlementApiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected SettlementServiceInterface $settlementService
    ) {}

    /**
     * Endpoint daftar seluruh bundel SPJ (GET /api/v1/bundles).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['unit_id', 'status', 'tahun_anggaran', 'limit']);
        $bundles = $this->settlementService->getBundles($filters, $request->user());

        return $this->successResponse($bundles, 'Daftar bundel SPJ berhasil dimuat');
    }

    /**
     * Endpoint pembuatan bundel SPJ baru (POST /api/v1/bundles).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kuitansi_ids' => ['required', 'array', 'min:1'],
            'kuitansi_ids.*' => ['required', 'integer'],
            'jenis_bundel' => ['nullable', 'string', 'in:SPP_GU,SPP_TU,SPP_LS,LPJ_BPP'],
            'tahun_anggaran' => ['nullable', 'integer', 'min:2020'],
            'bulan_periode' => ['nullable', 'integer', 'between:1,12'],
            'unit_kerja_id' => ['nullable', 'integer', 'exists:master_unit_kerja,id'],
            'nomor_bundel' => ['nullable', 'string', 'max:60'],
            'nomor_spp_sipd' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        if ($user && $user->role === 'LURAH') {
            return $this->errorResponse('FORBIDDEN', 'Lurah hanya memiliki akses baca (read-only) terhadap bundel SPJ.', null, 403);
        }

        try {
            $bundle = $this->settlementService->createBundle($validated, $validated['kuitansi_ids'], $user);

            return $this->successResponse($bundle, 'Bundel pengajuan SPJ berhasil dibuat', 201);
        } catch (InvalidArgumentException $e) {
            $statusCode = str_contains($e->getMessage(), 'hanya berwenang') || str_contains($e->getMessage(), 'hanya dapat membundel')
                ? 403
                : 422;

            return $this->errorResponse('BUNDLE_CREATION_FAILED', $e->getMessage(), null, $statusCode);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('STORE_BUNDLE_ERROR', $e);
        }
    }

    /**
     * Endpoint detail bundel SPJ (GET /api/v1/bundles/{id}).
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        try {
            $detail = $this->settlementService->getBundleDetail($id, $request->user());

            return $this->successResponse($detail, 'Detail bundel SPJ berhasil dimuat');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('BUNDLE_NOT_FOUND', 'Bundel SPJ tidak ditemukan.', null, 404);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse('FORBIDDEN', $e->getMessage(), null, 403);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('SHOW_BUNDLE_ERROR', $e);
        }
    }

    /**
     * Endpoint unbundle / keluarkan kuitansi bermasalah (POST /api/v1/bundles/{id}/unbundle/{kuitansiId}).
     */
    public function unbundle(Request $request, int|string $id, int|string $kuitansiId): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->role === 'LURAH') {
            return $this->errorResponse('FORBIDDEN', 'Lurah hanya memiliki akses baca (read-only) dan tidak berwenang mengeluarkan kuitansi dari bundel.', null, 403);
        }

        try {
            $reason = $request->input('reason');
            $this->settlementService->unbundleReceipt($id, $kuitansiId, $reason, $user);

            return $this->successResponse(null, 'Kuitansi berhasil dikeluarkan dari bundel SPJ');
        } catch (ModelNotFoundException) {
            return $this->errorResponse('BUNDLE_NOT_FOUND', 'Bundel SPJ tidak ditemukan.', null, 404);
        } catch (InvalidArgumentException $e) {
            $statusCode = str_contains($e->getMessage(), 'tidak berwenang') ? 403 : 422;

            return $this->errorResponse('UNBUNDLE_FAILED', $e->getMessage(), null, $statusCode);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('UNBUNDLE_ERROR', $e);
        }
    }

    /**
     * Endpoint pengajuan bundel SPJ ke PPK (POST /api/v1/bundles/{id}/submit-ppk).
     */
    public function submitPpk(Request $request, int|string $id): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->role === 'LURAH') {
            return $this->errorResponse('FORBIDDEN', 'Lurah hanya memiliki akses baca (read-only) terhadap bundel SPJ.', null, 403);
        }

        try {
            $bundle = $this->settlementService->submitForPpk($id, $user);

            return $this->successResponse($bundle, 'Bundel SPJ berhasil diajukan ke PPK untuk pemeriksaan Bagian A');
        } catch (ModelNotFoundException) {
            return $this->errorResponse('BUNDLE_NOT_FOUND', 'Bundel SPJ tidak ditemukan.', null, 404);
        } catch (InvalidArgumentException $e) {
            $statusCode = str_contains($e->getMessage(), 'tidak berwenang') ? 403 : 422;

            return $this->errorResponse('SUBMIT_PPK_FAILED', $e->getMessage(), null, $statusCode);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('SUBMIT_PPK_ERROR', $e);
        }
    }

    /**
     * Endpoint persetujuan PPK atas kelengkapan 9 dokumen Bagian A (POST /api/v1/bundles/{id}/approve-ppk).
     */
    public function approvePpk(Request $request, int|string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user || ($user->role !== 'VERIFIKATOR' && ! $user->hasRole('VERIFIKATOR'))) {
            return $this->errorResponse('FORBIDDEN', 'Hanya Verifikator (PPK) yang berwenang menyetujui bundel SPJ.', null, 403);
        }

        $validated = $request->validate([
            'checklist_bagian_a' => ['nullable', 'array'],
            'checklist_bagian_a.*.id' => ['required_with:checklist_bagian_a', 'string'],
            'checklist_bagian_a.*.checked' => ['required_with:checklist_bagian_a', 'boolean'],
        ]);

        try {
            $bundle = $this->settlementService->approveByPpk($id, $validated['checklist_bagian_a'] ?? [], $user);

            return $this->successResponse($bundle, 'Bundel SPJ telah disetujui PPK dan siap dicatat SP2D');
        } catch (ModelNotFoundException) {
            return $this->errorResponse('BUNDLE_NOT_FOUND', 'Bundel SPJ tidak ditemukan.', null, 404);
        } catch (InvalidArgumentException $e) {
            $statusCode = str_contains($e->getMessage(), 'tidak berwenang') ? 403 : 422;

            return $this->errorResponse('APPROVE_PPK_FAILED', $e->getMessage(), null, $statusCode);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('APPROVE_PPK_ERROR', $e);
        }
    }

    /**
     * Endpoint pencatatan SP2D SIPD-RI (POST /api/v1/bundles/{id}/sp2d).
     * Memicu settleBudget() untuk mengubah seluruh pagu terkunci menjadi realisasi definitif.
     */
    public function recordSp2d(Request $request, int|string $id): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->isKelurahanScoped()) {
            return $this->errorResponse('FORBIDDEN', 'Pengguna kelurahan tidak berwenang mencatat nomor SP2D SIPD-RI.', null, 403);
        }

        $validated = $request->validate([
            'nomor_sp2d' => ['required', 'string', 'max:100'],
            'nomor_spp' => ['nullable', 'string', 'max:100'],
            'tanggal_sp2d' => ['nullable', 'date'],
        ]);

        try {
            $bundle = $this->settlementService->recordSipdSp2d(
                $id,
                $validated['nomor_sp2d'],
                $validated['nomor_spp'] ?? null,
                $validated['tanggal_sp2d'] ?? null,
                $user
            );

            return $this->successResponse($bundle, 'Nomor SP2D SIPD-RI berhasil dicatat dan anggaran definitif telah dibukukan');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('BUNDLE_NOT_FOUND', 'Bundel SPJ tidak ditemukan.', null, 404);
        } catch (InvalidArgumentException $e) {
            $statusCode = str_contains($e->getMessage(), 'tidak berwenang') ? 403 : 422;
            $errorCode = $statusCode === 403 ? 'FORBIDDEN' : 'INVALID_BUNDLE_STATE';

            return $this->errorResponse($errorCode, $e->getMessage(), null, $statusCode);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('RECORD_SP2D_ERROR', $e);
        }
    }
}
