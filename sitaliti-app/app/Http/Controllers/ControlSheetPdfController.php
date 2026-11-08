<?php

namespace App\Http\Controllers;

use App\Contracts\ControlSheetGeneratorServiceInterface;
use Illuminate\Http\Response;

class ControlSheetPdfController extends Controller
{
    public function __construct(
        protected ControlSheetGeneratorServiceInterface $controlSheetService
    ) {}

    /**
     * Mengalirkan file PDF Lembar Kendali resmi.
     */
    public function download(string|int $id): Response
    {
        $pdf = $this->controlSheetService->generatePdf($id);
        $cleanId = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $id);
        $filename = "Lembar-Kendali-{$cleanId}.pdf";

        return $pdf->download($filename);
    }
}

