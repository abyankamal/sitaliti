<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Kuitansi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait AuthorizesOperatorReceipt
{
    /**
     * Mencari kuitansi dan memvalidasi otorisasi pembuat/unit kerja untuk operasi operator.
     */
    protected function findAuthorizedOperatorReceipt(Request $request, int|string $id): Kuitansi|JsonResponse
    {
        $kuitansi = Kuitansi::resolveByIdOrNumber($id);

        if (! $kuitansi) {
            return $this->errorResponse('RECEIPT_NOT_FOUND', 'Kuitansi tidak ditemukan.', null, 404);
        }

        $user = $request->user();
        if ($user && ! $user->canAccessReceipt($kuitansi)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki hak akses untuk mengubah kuitansi ini.', null, 403);
        }

        if ($user && ($user->hasRole('OPERATOR') || $user->role === 'OPERATOR') && (int) $kuitansi->user_id !== (int) $user->id) {
            return $this->errorResponse('FORBIDDEN', 'Kuitansi ini hanya dapat diubah oleh operator pembuatnya.', null, 403);
        }

        return $kuitansi;
    }
}
