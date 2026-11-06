<?php

namespace App\Http\Controllers\Public;

use App\Contracts\ControlSheetGeneratorServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QrVerificationPublicController extends Controller
{
    public function __construct(
        protected ControlSheetGeneratorServiceInterface $controlSheetService
    ) {}

    /**
     * Halaman verifikasi publik saat QR Code di Lembar Kendali fisik di-scan.
     * Bebas login, namun data sensitif vendor (PII) diredaksi untuk privasi.
     * Mendukung format HTML (Inertia) dan JSON API.
     */
    public function show(Request $request, string $qrToken): Response|JsonResponse
    {
        try {
            $verification = $this->controlSheetService->getPublicVerificationData($qrToken);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $verification,
                ], 200);
            }

            return Inertia::render('Public/VerifyReceipt', [
                'verification' => $verification,
            ]);
        } catch (ModelNotFoundException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TOKEN_NOT_FOUND',
                        'message' => 'Dokumen Lembar Kendali tidak ditemukan atau token QR tidak valid.',
                    ],
                ], 404);
            }

            return Inertia::render('Public/VerifyReceipt', [
                'verification' => null,
                'error' => 'Dokumen Lembar Kendali tidak ditemukan atau token QR tidak valid.',
            ]);
        }
    }
}
