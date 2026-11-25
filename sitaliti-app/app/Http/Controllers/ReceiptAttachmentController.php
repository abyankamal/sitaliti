<?php

namespace App\Http\Controllers;

use App\Models\Kuitansi;
use App\Models\KuitansiLampiran;
use App\Models\KuitansiPajak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptAttachmentController extends Controller
{
    /**
     * Mengalirkan file lampiran kuitansi secara aman dari local private storage.
     */
    public function preview(Request $request, string|int $kuitansiId, int $attachmentId): StreamedResponse
    {
        $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($kuitansiId);

        $user = $request->user();
        if ($user && ! $user->canAccessReceipt($kuitansi)) {
            abort(403, 'Anda tidak memiliki hak akses untuk lampiran kuitansi ini.');
        }

        $lampiran = KuitansiLampiran::where('kuitansi_id', $kuitansi->id)
            ->where('id', $attachmentId)
            ->firstOrFail();

        if (! Storage::disk('local')->exists($lampiran->file_path)) {
            abort(404, 'File lampiran tidak ditemukan pada storage.');
        }

        $headers = [
            'Content-Disposition' => 'inline; filename="'.basename($lampiran->file_name).'"',
        ];

        return Storage::disk('local')->response($lampiran->file_path, $lampiran->file_name, $headers);
    }

    /**
     * Mengalirkan file bukti setor pajak (Billing/NTPN) secara aman dari private storage.
     */
    public function previewTaxReceipt(Request $request, string|int $kuitansiId, int $pajakId): StreamedResponse
    {
        $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($kuitansiId);

        $user = $request->user();
        if ($user && ! $user->canAccessReceipt($kuitansi)) {
            abort(403, 'Anda tidak memiliki hak akses untuk bukti setor pajak kuitansi ini.');
        }

        $pajak = KuitansiPajak::where('kuitansi_id', $kuitansi->id)
            ->where('id', $pajakId)
            ->firstOrFail();

        if (! $pajak->bukti_setor_path || ! Storage::disk('local')->exists($pajak->bukti_setor_path)) {
            abort(404, 'File bukti setor pajak tidak ditemukan pada storage.');
        }

        $filename = basename($pajak->bukti_setor_path);

        return Storage::disk('local')->response($pajak->bukti_setor_path, $filename, [
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
