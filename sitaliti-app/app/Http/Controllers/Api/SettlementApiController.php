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
        $filters = $request->only(['unit_id', 'status']);
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
            'unit_kerja_id' => ['nullable', 'integer'],
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
            return $this->errorResponse('STORE_BUNDLE_ERROR', $e->getMessage(), null, 500);
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
            return $this->errorResponse('SHOW_BUNDLE_ERROR', $e->getMessage(), null, 500);
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
        } catch (InvalidArgumentException $e) {
            $statusCode = str_contains($e->getMessage(), 'tidak berwenang') ? 403 : 422;

            return $this->errorResponse('UNBUNDLE_FAILED', $e->getMessage(), null, $statusCode);
        } catch (Throwable $e) {
            return $this->errorResponse('UNBUNDLE_ERROR', $e->getMessage(), null, 500);
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
            return $this->errorResponse('FORBIDDEN', $e->getMessage(), null, 403);
        } catch (Throwable $e) {
            return $this->errorResponse('RECORD_SP2D_ERROR', $e->getMessage(), null, 500);
        }
    }
}
