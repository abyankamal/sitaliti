<?php

namespace App\Http\Controllers\Api;

use App\Contracts\VerificationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class VerificationApiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected VerificationServiceInterface $verificationService
    ) {}

    /**
     * Endpoint antrean kuitansi untuk verifikator (GET /api/v1/verification/queue).
     */
    public function getQueue(Request $request): JsonResponse
    {
        $userId = (string) ($request->user()?->id ?? 1);
        $role = (string) ($request->user()?->role ?? 'VERIFIKATOR');
        $filters = $request->only(['status', 'unit_id']);

        $queue = $this->verificationService->getQueue($userId, $role, $filters);

        return $this->successResponse($queue, 'Daftar antrean verifikasi kuitansi berhasil dimuat');
    }

    /**
     * Endpoint detail kuitansi & dynamic checklist untuk split-screen workspace (GET /api/v1/verification/{id}).
     */
    public function getDetail(string|int $id): JsonResponse
    {
        try {
            $detail = $this->verificationService->getDetailForWorkspace((string) $id);

            return $this->successResponse($detail, 'Detail verifikasi kuitansi berhasil dimuat');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(
                'RECEIPT_NOT_FOUND',
                'Kuitansi tidak ditemukan.',
                null,
                404
            );
        } catch (Throwable $e) {
            return $this->errorResponse(
                'LOAD_DETAIL_FAILED',
                $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Endpoint eksekusi aksi verifikasi (POST /api/v1/verification/{id}/action).
     * Mendukung VERIFIED (Lolos), REVISION_MINOR (Revisi Minor + SLA 2 hari), REVISION_MAJOR (Revisi Mayor + Release Budget).
     */
    public function processAction(Request $request, string|int $id): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:VERIFIED,LOLOS_VERIFIKASI,REVISION_MINOR,REVISI_MINOR,REVISION_MAJOR,REVISI_MAYOR,DITOLAK'],
            'notes' => ['nullable', 'string'],
            'checklist_results' => ['nullable', 'array'],
            'checklist_results.*.item_id' => ['required', 'integer'],
            'checklist_results.*.status' => ['required', 'string', 'in:SESUAI,TIDAK_SESUAI,BELUM_DIPERIKSA'],
            'checklist_results.*.notes' => ['nullable', 'string'],
        ]);

        $verifierUserId = (string) ($request->user()?->id ?? 1);

        try {
            $result = $this->verificationService->processAction(
                (string) $id,
                $validated['action'],
                $validated,
                $verifierUserId
            );

            return $this->successResponse($result, $result['message']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(
                'RECEIPT_NOT_FOUND',
                'Kuitansi tidak ditemukan.',
                null,
                404
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse(
                'INVALID_ACTION',
                $e->getMessage(),
                null,
                422
            );
        } catch (Throwable $e) {
            return $this->errorResponse(
                'VERIFICATION_ACTION_FAILED',
                $e->getMessage(),
                null,
                500
            );
        }
    }
}

