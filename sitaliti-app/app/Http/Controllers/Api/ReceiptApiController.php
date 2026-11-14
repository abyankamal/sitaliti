<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PaguOverlimitException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Services\ReceiptExportService;
use App\Services\ReceiptQueryService;
use App\Services\ReceiptService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReceiptApiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected ReceiptService $receiptService,
        protected ReceiptQueryService $receiptQueryService,
        protected ReceiptExportService $receiptExportService
    ) {}

    /**
     * Endpoint ekspor rekapitulasi data kuitansi dan pajak streaming CSV (GET /api/v1/receipts/export).
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filters = $request->only([
            'status',
            'unit_kerja_id',
            'bulan_anggaran',
            'kategori_belanja',
            'search',
        ]);

        if ($user && $user->isKelurahanScoped()) {
            $filters['unit_kerja_id'] = $user->unit_kerja_id;
        }

        return $this->receiptExportService->streamExport($user, $filters);
    }

    /**
     * Endpoint daftar kuitansi terpaginasi dengan scoping wewenang dan filter (GET /api/v1/receipts).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only([
            'status',
            'unit_kerja_id',
            'bulan_anggaran',
            'kategori_belanja',
            'search',
        ]);

        if ($user && $user->isKelurahanScoped()) {
            $filters['unit_kerja_id'] = $user->unit_kerja_id;
        }

        $perPage = (int) $request->input('per_page', 15);

        $paginated = $this->receiptQueryService->getPaginatedReceipts($user, $filters, $perPage);
        $tabCounts = $this->receiptQueryService->getStatusCounts($user, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Daftar kuitansi berhasil diambil',
            'data' => $paginated,
            'tab_counts' => $tabCounts,
        ]);
    }

    /**
     * Endpoint simulasi validasi draft kuitansi & perhitungan pajak (POST /api/v1/receipts/validate-draft).
     */
    public function validateDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'anggaran_rekening_id' => ['required'],
            'bulan_anggaran' => ['nullable', 'integer', 'between:1,12'],
            'kategori_belanja' => ['nullable', 'string'],
            'has_npwp' => ['nullable', 'boolean'],
            'npwp_penerima' => ['nullable', 'string'],
            'golongan_asn' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nama_barang' => ['required', 'string'],
            'items.*.volume' => ['required', 'numeric', 'min:0.01'],
            'items.*.harga_satuan' => ['required', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $rekening = AnggaranRekening::find($validated['anggaran_rekening_id']);
        if ($user && $rekening && ! $user->canAccessRekening($rekening)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak berwenang memeriksa kuota RAK atau simulasi draft untuk rekening unit kerja lain.', null, 403);
        }

        $result = $this->receiptService->validateDraft($validated);

        return $this->successResponse($result, 'Simulasi validasi draft kuitansi berhasil');
    }

    /**
     * Endpoint simpan draft kuitansi baru dengan deteksi duplikasi SHA-256 instan (POST /api/v1/receipts).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'anggaran_rekening_id' => ['required', 'exists:anggaran_rekening,id'],
            'bulan_anggaran' => ['required', 'integer', 'between:1,12'],
            'nomor_kuitansi' => ['nullable', 'string', 'max:50', 'unique:kuitansi,nomor_kuitansi'],
            'nomor_bukti_toko' => ['nullable', 'string', 'max:100'],
            'tanggal_transaksi' => ['required', 'date'],
            'uraian_belanja' => ['nullable', 'string'],
            'uraian' => ['nullable', 'string'],
            'nama_penerima' => ['required', 'string', 'max:200'],
            'npwp_penerima' => ['nullable', 'string', 'max:30'],
            'metode_pembayaran' => ['nullable', 'string', 'in:NON_TUNAI_IBC,TUNAI_UP'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nama_barang' => ['required', 'string', 'max:255'],
            'items.*.volume' => ['required', 'numeric', 'min:0.01'],
            'items.*.satuan' => ['nullable', 'string', 'max:50'],
            'items.*.harga_satuan' => ['required', 'numeric', 'min:0'],
            'files.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], // Max 10MB
            'lampiran.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $user = $request->user();
        if ($user && ($user->hasRole('OPERATOR') || $user->role === 'OPERATOR')) {
            $rekening = AnggaranRekening::find($validated['anggaran_rekening_id']);
            if ($user->unit_kerja_id && $rekening && $rekening->unit_kerja_id !== $user->unit_kerja_id) {
                return $this->errorResponse('FORBIDDEN', 'Anda tidak berwenang membuat kuitansi dengan kode rekening dari unit kerja lain.', null, 403);
            }
        }

        // Kumpulkan berkas lampiran yang diunggah
        $files = $request->file('files') ?? $request->file('lampiran') ?? [];
        if (! is_array($files)) {
            $files = [$files];
        }

        try {
            $kuitansi = $this->receiptService->createDraft($validated, $files, $request->user());

            return $this->successResponse([
                'receipt_id' => $kuitansi->nomor_kuitansi,
                'id' => $kuitansi->id,
                'status' => $kuitansi->status,
                'nominal_bruto' => $kuitansi->nominal_bruto,
                'total_pajak' => $kuitansi->total_pajak,
                'nominal_neto' => $kuitansi->nominal_neto,
                'kuitansi' => $kuitansi,
            ], 'Draft kuitansi dan berkas pendukung berhasil disimpan', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'DUPLICATE_RECEIPT_ATTACHMENT',
                $e->getMessage(),
                $e->errors(),
                422
            );
        } catch (Throwable $e) {
            return $this->errorResponse(
                'STORE_RECEIPT_FAILED',
                $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Endpoint pengajuan kuitansi ke PPTK yang mengunci pagu anggaran (POST /api/v1/receipts/{id}/submit).
     */
    public function submit(Request $request, int|string $id): JsonResponse
    {
        $kuitansi = Kuitansi::where('id', $id)
            ->orWhere('nomor_kuitansi', $id)
            ->first();

        if (! $kuitansi) {
            return $this->errorResponse(
                'RECEIPT_NOT_FOUND',
                'Kuitansi tidak ditemukan.',
                null,
                404
            );
        }

        $user = $request->user();
        if ($user && ! $user->canAccessReceipt($kuitansi)) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Anda tidak memiliki hak akses untuk mengajukan kuitansi ini.',
                null,
                403
            );
        }

        if ($user && ($user->hasRole('OPERATOR') || $user->role === 'OPERATOR') && $kuitansi->user_id !== $user->id) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Draft kuitansi ini hanya dapat diajukan oleh pembuatnya.',
                null,
                403
            );
        }

        try {
            $updated = $this->receiptService->submitToPptk($kuitansi->id, $user);

            return $this->successResponse([
                'id' => $updated->id,
                'nomor_kuitansi' => $updated->nomor_kuitansi,
                'status' => $updated->status,
                'tanggal_submit' => $updated->tanggal_submit,
            ], 'Kuitansi berhasil diajukan ke PPTK dan pagu anggaran telah dikunci');
        } catch (PaguOverlimitException $e) {
            return $this->errorResponse(
                'PAGU_OVERLIMIT',
                $e->getMessage(),
                null,
                422
            );
        } catch (Throwable $e) {
            return $this->errorResponse(
                'SUBMIT_RECEIPT_FAILED',
                $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * Endpoint persetujuan kuitansi oleh PPTK (POST /api/v1/receipts/{id}/pptk-approve).
     */
    public function pptkApprove(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $kuitansi = Kuitansi::where('id', $id)->orWhere('nomor_kuitansi', $id)->first();
        if (! $kuitansi) {
            return $this->errorResponse(
                'RECEIPT_NOT_FOUND',
                'Kuitansi tidak ditemukan.',
                null,
                404
            );
        }

        $user = $request->user();
        if ($user && ! $user->canAccessReceipt($kuitansi)) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Anda tidak memiliki hak akses untuk memproses kuitansi ini.',
                null,
                403
            );
        }

        try {
            $updated = $this->receiptService->approveByPptk(
                $kuitansi->id,
                $validated['catatan'] ?? null,
                $user
            );

            $pesan = $updated->status === 'MENUNGGU_LURAH'
                ? 'Kuitansi disetujui oleh PPTK dan diteruskan ke Lurah untuk diotorisasi'
                : 'Kuitansi berhasil disetujui oleh PPTK dan diteruskan ke Tim Verifikator';

            return $this->successResponse([
                'id' => $updated->id,
                'nomor_kuitansi' => $updated->nomor_kuitansi,
                'status' => $updated->status,
                'pptk_user_id' => $updated->pptk_user_id,
                'tanggal_pptk_approve' => $updated->tanggal_pptk_approve,
                'catatan_pptk' => $updated->catatan_pptk,
            ], $pesan);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(
                'RECEIPT_NOT_FOUND',
                'Kuitansi tidak ditemukan.',
                null,
                404
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse(
                'INVALID_STATE',
                $e->getMessage(),
                null,
                422
            );
        } catch (Throwable $e) {
            return $this->errorResponse(
                'PPTK_APPROVE_FAILED',
                $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * Endpoint penolakan kuitansi oleh PPTK (POST /api/v1/receipts/{id}/pptk-reject).
     */
    public function pptkReject(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $kuitansi = Kuitansi::where('id', $id)->orWhere('nomor_kuitansi', $id)->first();
        if (! $kuitansi) {
            return $this->errorResponse(
                'RECEIPT_NOT_FOUND',
                'Kuitansi tidak ditemukan.',
                null,
                404
            );
        }

        $user = $request->user();
        if ($user && ! $user->canAccessReceipt($kuitansi)) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Anda tidak memiliki hak akses untuk memproses kuitansi ini.',
                null,
                403
            );
        }

        try {
            $updated = $this->receiptService->rejectByPptk(
                $kuitansi->id,
                $validated['alasan'],
                $user
            );

            return $this->successResponse([
                'id' => $updated->id,
                'nomor_kuitansi' => $updated->nomor_kuitansi,
                'status' => $updated->status,
                'catatan_pptk' => $updated->catatan_pptk,
            ], 'Kuitansi ditolak oleh PPTK dan pagu anggaran telah dipulihkan');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(
                'RECEIPT_NOT_FOUND',
                'Kuitansi tidak ditemukan.',
                null,
                404
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse(
                'INVALID_STATE',
                $e->getMessage(),
                null,
                422
            );
        } catch (Throwable $e) {
            return $this->errorResponse(
                'PPTK_REJECT_FAILED',
                $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * Endpoint otorisasi/persetujuan kuitansi oleh Lurah (POST /api/v1/receipts/{id}/lurah-approve).
     */
    public function lurahApprove(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $kuitansi = Kuitansi::where('id', $id)->orWhere('nomor_kuitansi', $id)->first();
        if (! $kuitansi) {
            return $this->errorResponse(
                'RECEIPT_NOT_FOUND',
                'Kuitansi tidak ditemukan.',
                null,
                404
            );
        }

        $user = $request->user();
        if (! $user || $user->role !== 'LURAH' || (int) $user->unit_kerja_id !== (int) $kuitansi->unit_kerja_id) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Hanya Lurah pada kelurahan bersangkutan yang berwenang mengotorisasi kuitansi ini.',
                null,
                403
            );
        }

        try {
            $updated = $this->receiptService->approveByLurah(
                $kuitansi->id,
                $validated['catatan'] ?? null,
                $user
            );

            return $this->successResponse([
                'id' => $updated->id,
                'nomor_kuitansi' => $updated->nomor_kuitansi,
                'status' => $updated->status,
                'lurah_user_id' => $updated->lurah_user_id,
                'tanggal_lurah_approve' => $updated->tanggal_lurah_approve,
                'catatan_lurah' => $updated->catatan_lurah,
            ], 'Kuitansi berhasil diotorisasi oleh Lurah dan diteruskan ke Tim Verifikator Kecamatan');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(
                'RECEIPT_NOT_FOUND',
                'Kuitansi tidak ditemukan.',
                null,
                404
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse(
                'INVALID_STATE',
                $e->getMessage(),
                null,
                422
            );
        } catch (Throwable $e) {
            return $this->errorResponse(
                'LURAH_APPROVE_FAILED',
                $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * Endpoint penolakan kuitansi oleh Lurah (POST /api/v1/receipts/{id}/lurah-reject).
     */
    public function lurahReject(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $kuitansi = Kuitansi::where('id', $id)->orWhere('nomor_kuitansi', $id)->first();
        if (! $kuitansi) {
            return $this->errorResponse(
                'RECEIPT_NOT_FOUND',
                'Kuitansi tidak ditemukan.',
                null,
                404
            );
        }

        $user = $request->user();
        if (! $user || $user->role !== 'LURAH' || (int) $user->unit_kerja_id !== (int) $kuitansi->unit_kerja_id) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Hanya Lurah pada kelurahan bersangkutan yang berwenang menolak kuitansi ini.',
                null,
                403
            );
        }

        try {
            $updated = $this->receiptService->rejectByLurah(
                $kuitansi->id,
                $validated['alasan'],
                $user
            );

            return $this->successResponse([
                'id' => $updated->id,
                'nomor_kuitansi' => $updated->nomor_kuitansi,
                'status' => $updated->status,
                'catatan_lurah' => $updated->catatan_lurah,
            ], 'Kuitansi dikembalikan oleh Lurah dan pagu anggaran telah dipulihkan');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(
                'RECEIPT_NOT_FOUND',
                'Kuitansi tidak ditemukan.',
                null,
                404
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse(
                'INVALID_STATE',
                $e->getMessage(),
                null,
                422
            );
        } catch (Throwable $e) {
            return $this->errorResponse(
                'LURAH_REJECT_FAILED',
                $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * Endpoint detail lengkap kuitansi (GET /api/v1/receipts/{id}).
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $kuitansi = Kuitansi::where('id', $id)
            ->orWhere('nomor_kuitansi', $id)
            ->with(['rekening.unitKerja', 'items', 'pajak', 'lampiran', 'pptkUser', 'creator', 'riwayat.user'])
            ->first();

        if (! $kuitansi) {
            return $this->errorResponse(
                'RECEIPT_NOT_FOUND',
                'Kuitansi tidak ditemukan.',
                null,
                404
            );
        }

        $user = $request->user();
        if ($user) {
            if (! $user->canAccessReceipt($kuitansi)) {
                return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki hak akses untuk kuitansi ini.', null, 403);
            }
            if (($user->hasRole('OPERATOR') || $user->role === 'OPERATOR') && $kuitansi->status === 'DRAFT' && $kuitansi->user_id !== $user->id) {
                return $this->errorResponse('FORBIDDEN', 'Draft kuitansi ini hanya dapat diakses oleh pembuatnya.', null, 403);
            }
        }

        return $this->successResponse($kuitansi, 'Detail kuitansi berhasil diambil');
    }

    /**
     * Endpoint riwayat aktivitas / audit trail kuitansi (GET /api/v1/receipts/{id}/history).
     */
    public function history(Request $request, int|string $id): JsonResponse
    {
        $kuitansi = Kuitansi::where('id', $id)
            ->orWhere('nomor_kuitansi', $id)
            ->first();

        if (! $kuitansi) {
            return $this->errorResponse(
                'RECEIPT_NOT_FOUND',
                'Kuitansi tidak ditemukan.',
                null,
                404
            );
        }

        $user = $request->user();
        if ($user && ! $user->canAccessReceipt($kuitansi)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki hak akses untuk riwayat kuitansi ini.', null, 403);
        }

        $riwayat = $kuitansi->riwayat()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'kuitansi_id' => $item->kuitansi_id,
                    'action' => $item->action,
                    'status_lama' => $item->status_lama,
                    'status_baru' => $item->status_baru,
                    'catatan' => $item->catatan,
                    'metadata' => $item->metadata,
                    'ip_address' => $item->ip_address,
                    'user_id' => $item->user_id,
                    'actor_name' => $item->user?->name ?? 'Sistem / Arsip Pegawai',
                    'actor_role' => $item->user?->role ?? null,
                    'created_at' => $item->created_at?->toIso8601String(),
                    'created_at_formatted' => $item->created_at?->format('d/m/Y H:i'),
                ];
            });

        return $this->successResponse($riwayat, 'Riwayat aktivitas kuitansi berhasil diambil');
    }
}
