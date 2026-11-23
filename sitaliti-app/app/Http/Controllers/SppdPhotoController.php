<?php

namespace App\Http\Controllers;

use App\Models\Kuitansi;
use App\Models\KuitansiSppdPeserta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SppdPhotoController extends Controller
{
    /**
     * Mengalirkan foto keberangkatan SPPD secara aman dari private storage.
     */
    public function preview(Request $request, int|string $kuitansiId, ?int $pesertaId = null): StreamedResponse
    {
        $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($kuitansiId);

        $user = $request->user();
        if ($user && ! $user->canAccessReceipt($kuitansi)) {
            abort(403, 'Anda tidak memiliki hak akses untuk foto keberangkatan kuitansi ini.');
        }

        if ($pesertaId !== null) {
            $peserta = KuitansiSppdPeserta::where('kuitansi_id', $kuitansi->id)
                ->where('id', $pesertaId)
                ->firstOrFail();

            $filePath = $peserta->foto_path ?: $kuitansi->sppd_foto_path;
            $fileName = $peserta->foto_path
                ? ($peserta->foto_name ?: basename($peserta->foto_path))
                : ($kuitansi->sppd_foto_name ?: 'foto-keberangkatan.jpg');
        } else {
            $filePath = $kuitansi->sppd_foto_path;
            $fileName = $kuitansi->sppd_foto_name ?: 'foto-keberangkatan.jpg';
        }

        if (! $filePath || ! Storage::disk('local')->exists($filePath)) {
            abort(404, 'Foto keberangkatan tidak ditemukan pada storage.');
        }

        $headers = [
            'Content-Disposition' => 'inline; filename="'.basename($fileName).'"',
        ];

        return Storage::disk('local')->response($filePath, $fileName, $headers);
    }
}
