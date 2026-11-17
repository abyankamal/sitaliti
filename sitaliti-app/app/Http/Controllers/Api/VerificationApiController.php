<?php

namespace App\Http\Controllers\Api;

use App\Contracts\VerificationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use App\Models\Kuitansi;
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
        $filters = $request->only(['status', 'unit_id', 'limit']);

        $queue = $this->verificationService->getQueue($userId, $role, $filters);

        return $this->successResponse($queue, 'Daftar antrean verifikasi kuitansi berhasil dimuat');
    }

    /**
     * Endpoint detail kuitansi & dynamic checklist untuk split-screen workspace (GET /api/v1/verification/{id}).
     */
    public function getDetail(Request $request, string|int $id): JsonResponse
    {
        $kuitansi = Kuitansi::resolveByIdOrNumber($id);

        if (! $kuitansi) {
            return $this->errorResponse(
                'RECEIPT_NOT_FOUND',
                'Kuitansi tidak ditemukan.',
                null,
                404
            );
        }

        $user = $request->user();
        if ($user && ! $user->canAccessReceipt($kuitansi)) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Anda tidak memiliki hak akses untuk detail verifikasi kuitansi ini.',
                null,
                403
            );
        }

        try {
            $detail = $this->verificationService->getDetailForWorkspace((string) $kuitansi->id);

            return $this->successResponse($detail, 'Detail verifikasi kuitansi berhasil dimuat');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(
                'RECEIPT_NOT_FOUND',
                'Kuitansi tidak ditemukan.',
                null,
                404
            );
        } catch (Throwable $e) {
            return $this->serverErrorResponse('LOAD_DETAIL_FAILED', $e);
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

        $user = $request->user();
        if ($user && ($user->role !== 'VERIFIKATOR' || ! $user->can('verification.process_action'))) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Hanya Verifikator yang berwenang memproses keputusan verifikasi.',
                null,
                403
            );
        }

        $verifierUserId = (string) ($user?->id ?? 1);

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
            return $this->serverErrorResponse('VERIFICATION_ACTION_FAILED', $e);
        }
    }
}
