<?php

namespace App\Http\Controllers;

use App\Contracts\ControlSheetGeneratorServiceInterface;
use App\Models\Kuitansi;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ControlSheetPdfController extends Controller
{
    public function __construct(
        protected ControlSheetGeneratorServiceInterface $controlSheetService
    ) {}

    /**
     * Mengalirkan file PDF Lembar Kendali resmi.
     */
    public function download(Request $request, string|int $id): Response
    {
        $kuitansi = Kuitansi::where('id', $id)
            ->orWhere('nomor_kuitansi', (string) $id)
            ->firstOrFail();

        $user = $request->user();
        if ($user && ($user->hasRole('OPERATOR') || $user->role === 'OPERATOR')) {
            if ($user->unit_kerja_id && $kuitansi->unit_kerja_id !== $user->unit_kerja_id) {
                abort(403, 'Anda tidak berwenang mengunduh Lembar Kendali dari unit kerja lain.');
            }
        }

        $pdf = $this->controlSheetService->generatePdf($id);
        $cleanId = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $id);
        $filename = "Lembar-Kendali-{$cleanId}.pdf";

        return $pdf->download($filename);
    }
}
