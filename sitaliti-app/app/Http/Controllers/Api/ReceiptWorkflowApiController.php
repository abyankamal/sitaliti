<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PaguOverlimitException;
use App\Http\Controllers\Api\Concerns\AuthorizesOperatorReceipt;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use App\Models\Kuitansi;
use App\Services\ReceiptWorkflowService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class ReceiptWorkflowApiController extends Controller
{
    use ApiResponseTrait;
    use AuthorizesOperatorReceipt;

    public function __construct(
        protected ReceiptWorkflowService $workflowService
    ) {}

    /**
     * Endpoint kirim ulang kuitansi REVISI_MINOR ke Verifikator (POST /api/v1/receipts/{id}/resubmit-revision).
     */
    public function resubmitRevision(Request $request, int|string $id): JsonResponse
    {
        $resolved = $this->findAuthorizedOperatorReceipt($request, $id);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        $kuitansi = $resolved;

        try {
            $updated = $this->workflowService->resubmitMinorRevision($kuitansi->id, $request->user());

            return $this->successResponse([
                'id' => $updated->id,
                'nomor_kuitansi' => $updated->nomor_kuitansi,
                'status' => $updated->status,
            ], 'Kuitansi revisi minor berhasil dikirim ulang ke Verifikator');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse('INVALID_RECEIPT_STATE', $e->getMessage(), null, 422);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('RESUBMIT_REVISION_FAILED', $e);
        }
    }

    /**
     * Endpoint penarikan kuitansi kembali ke DRAFT oleh Operator pembuatnya (POST /api/v1/receipts/{id}/recall).
     */
    public function recall(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'alasan' => ['nullable', 'string', 'max:1000'],
        ]);

        $resolved = $this->findAuthorizedOperatorReceipt($request, $id);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        $kuitansi = $resolved;

        try {
            $updated = $this->workflowService->recallByOperator(
                $kuitansi->id,
                $validated['alasan'] ?? null,
                $request->user()
            );

            return $this->successResponse([
                'id' => $updated->id,
                'nomor_kuitansi' => $updated->nomor_kuitansi,
                'status' => $updated->status,
            ], 'Kuitansi berhasil ditarik kembali ke DRAFT dan pagu anggaran telah dipulihkan');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse('INVALID_RECEIPT_STATE', $e->getMessage(), null, 422);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('RECALL_RECEIPT_FAILED', $e);
        }
    }

    /**
     * Endpoint pengajuan kuitansi ke PPTK yang mengunci pagu anggaran (POST /api/v1/receipts/{id}/submit).
     */
    public function submit(Request $request, int|string $id): JsonResponse
    {
        $kuitansi = Kuitansi::resolveByIdOrNumber($id);

        if (! $kuitansi) {
            return $this->errorResponse('RECEIPT_NOT_FOUND', 'Kuitansi tidak ditemukan.', null, 404);
        }

        $user = $request->user();
        if ($user && ! $user->canAccessReceipt($kuitansi)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki hak akses untuk mengajukan kuitansi ini.', null, 403);
        }

        if ($user && ($user->hasRole('OPERATOR') || $user->role === 'OPERATOR') && $kuitansi->user_id !== $user->id) {
            return $this->errorResponse('FORBIDDEN', 'Draft kuitansi ini hanya dapat diajukan oleh pembuatnya.', null, 403);
        }

        try {
            $updated = $this->workflowService->submitToPptk($kuitansi->id, $user);

            return $this->successResponse([
                'id' => $updated->id,
                'nomor_kuitansi' => $updated->nomor_kuitansi,
                'status' => $updated->status,
                'tanggal_submit' => $updated->tanggal_submit,
            ], 'Kuitansi berhasil diajukan ke PPTK dan pagu anggaran telah dikunci');
        } catch (PaguOverlimitException $e) {
            return $this->errorResponse('PAGU_OVERLIMIT', $e->getMessage(), null, 422);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse('INVALID_RECEIPT_STATE', $e->getMessage(), null, 422);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('SUBMIT_RECEIPT_FAILED', $e);
        }
    }

    /**
     * Endpoint persetujuan kuitansi oleh PPTK (POST /api/v1/receipts/{id}/pptk-approve).
     */
    public function pptkApprove(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $kuitansi = Kuitansi::resolveByIdOrNumber($id);
        if (! $kuitansi) {
            return $this->errorResponse('RECEIPT_NOT_FOUND', 'Kuitansi tidak ditemukan.', null, 404);
        }

        $user = $request->user();
        if ($user && ! $user->canAccessReceipt($kuitansi)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki hak akses untuk memproses kuitansi ini.', null, 403);
        }

        try {
            $updated = $this->workflowService->approveByPptk(
                $kuitansi->id,
                $validated['catatan'] ?? null,
                $user
            );

            $pesan = $updated->status === 'MENUNGGU_LURAH'
                ? 'Kuitansi disetujui oleh PPTK dan diteruskan ke Lurah untuk diotorisasi'
                : 'Kuitansi berhasil disetujui oleh PPTK dan diteruskan ke Tim Verifikator';

            return $this->successResponse([
                'id' => $updated->id,
                'nomor_kuitansi' => $updated->nomor_kuitansi,
                'status' => $updated->status,
                'pptk_user_id' => $updated->pptk_user_id,
                'tanggal_pptk_approve' => $updated->tanggal_pptk_approve,
                'catatan_pptk' => $updated->catatan_pptk,
            ], $pesan);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('RECEIPT_NOT_FOUND', 'Kuitansi tidak ditemukan.', null, 404);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse('INVALID_STATE', $e->getMessage(), null, 422);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('PPTK_APPROVE_FAILED', $e);
        }
    }

    /**
     * Endpoint penolakan kuitansi oleh PPTK (POST /api/v1/receipts/{id}/pptk-reject).
     */
    public function pptkReject(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $kuitansi = Kuitansi::resolveByIdOrNumber($id);
        if (! $kuitansi) {
            return $this->errorResponse('RECEIPT_NOT_FOUND', 'Kuitansi tidak ditemukan.', null, 404);
        }

        $user = $request->user();
        if ($user && ! $user->canAccessReceipt($kuitansi)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki hak akses untuk memproses kuitansi ini.', null, 403);
        }

        try {
            $updated = $this->workflowService->rejectByPptk(
                $kuitansi->id,
                $validated['alasan'],
                $user
            );

            return $this->successResponse([
                'id' => $updated->id,
                'nomor_kuitansi' => $updated->nomor_kuitansi,
                'status' => $updated->status,
                'catatan_pptk' => $updated->catatan_pptk,
            ], 'Kuitansi ditolak oleh PPTK dan pagu anggaran telah dipulihkan');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('RECEIPT_NOT_FOUND', 'Kuitansi tidak ditemukan.', null, 404);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse('INVALID_STATE', $e->getMessage(), null, 422);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('PPTK_REJECT_FAILED', $e);
        }
    }

    /**
     * Endpoint otorisasi/persetujuan kuitansi oleh Lurah (POST /api/v1/receipts/{id}/lurah-approve).
     */
    public function lurahApprove(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $kuitansi = Kuitansi::resolveByIdOrNumber($id);
        if (! $kuitansi) {
            return $this->errorResponse('RECEIPT_NOT_FOUND', 'Kuitansi tidak ditemukan.', null, 404);
        }

        $user = $request->user();
        if (! $user || $user->role !== 'LURAH' || (int) $user->unit_kerja_id !== (int) $kuitansi->unit_kerja_id) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Hanya Lurah pada kelurahan bersangkutan yang berwenang mengotorisasi kuitansi ini.',
                null,
                403
            );
        }

        try {
            $updated = $this->workflowService->approveByLurah(
                $kuitansi->id,
                $validated['catatan'] ?? null,
                $user
            );

            return $this->successResponse([
                'id' => $updated->id,
                'nomor_kuitansi' => $updated->nomor_kuitansi,
                'status' => $updated->status,
                'lurah_user_id' => $updated->lurah_user_id,
                'tanggal_lurah_approve' => $updated->tanggal_lurah_approve,
                'catatan_lurah' => $updated->catatan_lurah,
            ], 'Kuitansi berhasil diotorisasi oleh Lurah dan diteruskan ke Tim Verifikator Kecamatan');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('RECEIPT_NOT_FOUND', 'Kuitansi tidak ditemukan.', null, 404);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse('INVALID_STATE', $e->getMessage(), null, 422);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('LURAH_APPROVE_FAILED', $e);
        }
    }

    /**
     * Endpoint penolakan kuitansi oleh Lurah (POST /api/v1/receipts/{id}/lurah-reject).
     */
    public function lurahReject(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $kuitansi = Kuitansi::resolveByIdOrNumber($id);
        if (! $kuitansi) {
            return $this->errorResponse('RECEIPT_NOT_FOUND', 'Kuitansi tidak ditemukan.', null, 404);
        }

        $user = $request->user();
        if (! $user || $user->role !== 'LURAH' || (int) $user->unit_kerja_id !== (int) $kuitansi->unit_kerja_id) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Hanya Lurah pada kelurahan bersangkutan yang berwenang menolak kuitansi ini.',
                null,
                403
            );
        }

        try {
            $updated = $this->workflowService->rejectByLurah(
                $kuitansi->id,
                $validated['alasan'],
                $user
            );

            return $this->successResponse([
                'id' => $updated->id,
                'nomor_kuitansi' => $updated->nomor_kuitansi,
                'status' => $updated->status,
                'catatan_lurah' => $updated->catatan_lurah,
            ], 'Kuitansi dikembalikan oleh Lurah dan pagu anggaran telah dipulihkan');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('RECEIPT_NOT_FOUND', 'Kuitansi tidak ditemukan.', null, 404);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse('INVALID_STATE', $e->getMessage(), null, 422);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('LURAH_REJECT_FAILED', $e);
        }
    }
}
