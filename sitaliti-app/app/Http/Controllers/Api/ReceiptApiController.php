<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PaguOverlimitException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReceiptApiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected ReceiptService $receiptService
    ) {}

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
            'files.*' => ['nullable', 'file', 'max:10240'], // Max 10MB
            'lampiran.*' => ['nullable', 'file', 'max:10240'],
        ]);

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
    public function submit(int|string $id): JsonResponse
    {
        try {
            $kuitansi = $this->receiptService->submitToPptk($id);

            return $this->successResponse([
                'id' => $kuitansi->id,
                'nomor_kuitansi' => $kuitansi->nomor_kuitansi,
                'status' => $kuitansi->status,
                'tanggal_submit' => $kuitansi->tanggal_submit,
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
}
