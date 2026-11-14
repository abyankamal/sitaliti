<?php

namespace App\Http\Controllers;

use App\Contracts\ControlSheetGeneratorServiceInterface;
use App\Http\Resources\ApiResponseTrait;
use App\Models\Kuitansi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class ControlSheetPdfController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected ControlSheetGeneratorServiceInterface $controlSheetService
    ) {}

    /**
     * Mengembalikan data JSON Lembar Kendali untuk pratinjau UI (GET /api/v1/control-sheet/{id}).
     */
    public function show(Request $request, string|int $id): JsonResponse
    {
        $kuitansi = Kuitansi::where('id', $id)
            ->orWhere('nomor_kuitansi', (string) $id)
            ->first();

        if (! $kuitansi) {
            return $this->errorResponse('RECEIPT_NOT_FOUND', 'Kuitansi tidak ditemukan.', null, 404);
        }

        $user = $request->user();
        if ($user && ! $user->canAccessReceipt($kuitansi)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak berwenang mengakses Lembar Kendali ini.', null, 403);
        }

        try {
            $data = $this->controlSheetService->generateData((string) $kuitansi->id);

            return $this->successResponse($data, 'Data Lembar Kendali berhasil dimuat');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse('CONTROL_SHEET_UNAVAILABLE', $e->getMessage(), null, 422);
        }
    }

    /**
     * Mengalirkan file PDF Lembar Kendali resmi.
     */
    public function download(Request $request, string|int $id): Response
    {
        $kuitansi = Kuitansi::where('id', $id)
            ->orWhere('nomor_kuitansi', (string) $id)
            ->firstOrFail();

        $user = $request->user();
        if ($user && ! $user->canAccessReceipt($kuitansi)) {
            abort(403, 'Anda tidak berwenang mengunduh Lembar Kendali ini.');
        }

        $pdf = $this->controlSheetService->generatePdf($id);
        $cleanId = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $id);
        $filename = "Lembar-Kendali-{$cleanId}.pdf";

        return $pdf->download($filename);
    }
}
