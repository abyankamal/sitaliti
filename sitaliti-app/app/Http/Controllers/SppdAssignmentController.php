<?php

namespace App\Http\Controllers;

use App\Models\SppdAssignment;
use App\Services\SppdPdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SppdAssignmentController extends Controller
{
    public function __construct(
        protected SppdPdfService $pdfService
    ) {}

    /**
     * Mengalirkan atau mengunduh PDF SPPD F4 siap tanda tangan basah.
     */
    public function pdf(Request $request, int $id): Response
    {
        $user = $request->user();
        $assignment = SppdAssignment::findOrFail($id);

        if (! $user || (! $user->isSkpdWide() && (int) $assignment->unit_kerja_id !== (int) $user->unit_kerja_id)) {
            abort(403, 'Anda tidak berwenang mengakses dokumen SPPD unit kerja lain.');
        }

        $scope = (string) $request->query('scope', 'all');
        if (! in_array($scope, ['pre', 'post', 'all'], true)) {
            $scope = 'all';
        }

        $pdf = $this->pdfService->generateBundle($assignment, $scope);
        $filename = "sppd-{$assignment->id}-{$scope}.pdf";

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }
}
