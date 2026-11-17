<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Trait untuk standardisasi respon API JSON (Consistent Error & Success Semantics).
 * Sesuai prinsip api-and-interface-design.
 */
trait ApiResponseTrait
{
    /**
     * Respon sukses standar.
     */
    public function successResponse(mixed $data = null, string $message = 'Operasi berhasil', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Respon error terstruktur standar.
     * Format: { error: { code, message, details } }
     */
    public function errorResponse(string $code, string $message, mixed $details = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }

    /**
     * Melaporkan exception tak terduga dan mengembalikan HTTP 500 dengan pesan aman saat !app.debug.
     */
    public function serverErrorResponse(
        string $code,
        Throwable $e,
        string $fallbackMessage = 'Terjadi kesalahan internal pada server.'
    ): JsonResponse {
        report($e);

        $message = config('app.debug') ? $e->getMessage() : $fallbackMessage;

        return $this->errorResponse($code, $message, null, 500);
    }
}
