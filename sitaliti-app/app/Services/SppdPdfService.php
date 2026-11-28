<?php

namespace App\Services;

use App\Models\SppdAssignment;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

class SppdPdfService
{
    public function __construct(
        protected TerbilangService $terbilang
    ) {}

    /**
     * Menghasilkan bundel PDF SPPD ukuran F4 (215mm x 330mm) siap tanda tangan basah.
     */
    public function generateBundle(SppdAssignment $assignment, string $scope = 'all'): DomPdf
    {
        $normalizedScope = in_array($scope, ['pre', 'post', 'all'], true) ? $scope : 'all';

        $assignment->loadMissing([
            'unitKerja',
            'rekening.unitKerja',
            'peserta.pegawai',
            'peserta.biayaItems',
            'kuitansi.rekening.unitKerja',
        ]);

        $lamaHari = ($assignment->tanggal_berangkat && $assignment->tanggal_kembali)
            ? (int) $assignment->tanggal_berangkat->diffInDays($assignment->tanggal_kembali) + 1
            : 1;

        $logoDataUri = $this->resolveLogoDataUri();

        return Pdf::loadView('pdf.sppd.bundle', [
            'assignment' => $assignment,
            'scope' => $normalizedScope,
            'lamaHari' => $lamaHari,
            'terbilang' => $this->terbilang,
            'logoDataUri' => $logoDataUri,
        ])->setPaper([0, 0, 609.45, 935.43], 'portrait');
    }

    private function resolveLogoDataUri(): ?string
    {
        $candidates = [
            public_path('images/logo-garut.png'),
            public_path('images/garut-logo.png'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                $content = file_get_contents($path);
                if ($content !== false) {
                    return 'data:image/png;base64,'.base64_encode($content);
                }
            }
        }

        return null;
    }
}
