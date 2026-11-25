<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOperatorReceipt;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiPajak;
use App\Models\Pegawai;
use App\Services\ReceiptExportService;
use App\Services\ReceiptQueryService;
use App\Services\ReceiptService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ReceiptApiController extends Controller
{
    use ApiResponseTrait;
    use AuthorizesOperatorReceipt;

    public function __construct(
        protected ReceiptService $receiptService,
        protected ReceiptQueryService $receiptQueryService,
        protected ReceiptExportService $receiptExportService
    ) {}

    /**
     * Endpoint ekspor rekapitulasi data kuitansi dan pajak (Excel .xlsx default, PDF, atau CSV).
     */
    public function export(Request $request): Response
    {
        $this->validateReceiptFilters($request, withFormat: true);

        $user = $request->user();
        $filters = $request->only([
            'status',
            'unit_kerja_id',
            'bulan_anggaran',
            'kategori_belanja',
            'kode_sub_kegiatan',
            'kode_rekening',
            'tanggal_dari',
            'tanggal_sampai',
            'search',
        ]);

        if ($user && ! $user->isSkpdWide()) {
            $filters['unit_kerja_id'] = $user->unit_kerja_id;
        }

        $format = strtolower((string) $request->input('format', 'xlsx'));

        return match ($format) {
            'pdf' => $this->receiptExportService->downloadPdfExport($user, $filters),
            'csv' => $this->receiptExportService->streamCsvExport($user, $filters),
            default => $this->receiptExportService->streamExcelExport($user, $filters),
        };
    }

    /**
     * Endpoint daftar kuitansi terpaginasi dengan scoping wewenang dan filter (GET /api/v1/receipts).
     */
    public function index(Request $request): JsonResponse
    {
        $this->validateReceiptFilters($request);

        $user = $request->user();
        $filters = $request->only([
            'status',
            'unit_kerja_id',
            'bulan_anggaran',
            'kategori_belanja',
            'kode_sub_kegiatan',
            'kode_rekening',
            'tanggal_dari',
            'tanggal_sampai',
            'search',
        ]);

        if ($user && ! $user->isSkpdWide()) {
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
            'tahun_anggaran' => ['nullable', 'integer', 'between:2020,2100'],
            'tanggal_transaksi' => ['nullable', 'date'],
            'kategori_belanja' => ['nullable', 'string'],
            'has_npwp' => ['nullable', 'boolean'],
            'npwp_penerima' => ['nullable', 'string'],
            'golongan_asn' => ['nullable', 'string'],
            'is_sewa_kendaraan' => ['nullable', 'boolean'],
            'is_pkp' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nama_barang' => ['required', 'string'],
            'items.*.volume' => ['required', 'numeric', 'min:0.01'],
            'items.*.harga_satuan' => ['required', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $rekening = AnggaranRekening::find($validated['anggaran_rekening_id']);
        if ($user && $rekening && ! $user->canInputRekening($rekening)) {
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
            'has_npwp' => ['nullable', 'boolean'],
            'golongan_asn' => ['nullable', 'string', Rule::in(['GOLONGAN_I', 'GOLONGAN_II', 'GOLONGAN_I_II', 'GOLONGAN_III', 'GOLONGAN_IV', 'NON_ASN'])],
            'is_sewa_kendaraan' => ['nullable', 'boolean'],
            'is_pkp' => ['nullable', 'boolean'],
            'metode_pembayaran' => ['nullable', 'string', 'in:NON_TUNAI_IBC,TUNAI_UP'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nama_barang' => ['required', 'string', 'max:255'],
            'items.*.volume' => ['required', 'numeric', 'min:0.01'],
            'items.*.satuan' => ['nullable', 'string', 'max:50'],
            'items.*.harga_satuan' => ['required', 'numeric', 'min:0'],
            'files.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], // Max 10MB
            'lampiran.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'checklist_codes.*' => ['nullable', 'string', 'max:50'],
            'nama_dokumen.*' => ['nullable', 'string', 'max:255'],
            ...$this->sppdValidationRules(),
        ]);

        if ($inactiveError = $this->validateActiveNewPegawai($validated['sppd_peserta'] ?? [])) {
            return $inactiveError;
        }

        $validated['sppd_peserta_synced'] = $request->boolean('sppd_peserta_synced');
        $validated['sppd_foto_remove'] = $request->boolean('sppd_foto_remove');

        $user = $request->user();
        $rekening = AnggaranRekening::find($validated['anggaran_rekening_id']);
        if ($user && $rekening && ! $user->canInputRekening($rekening)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak berwenang membuat kuitansi dengan kode rekening dari unit kerja lain.', null, 403);
        }

        $attachments = $this->extractAttachmentsFromRequest($request);

        try {
            $kuitansi = $this->receiptService->createDraft($validated, $attachments, $request->user());

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
            $errorCode = array_key_exists('sppd_peserta', $e->errors())
                ? 'SPPD_SCHEDULE_CONFLICT'
                : 'DUPLICATE_RECEIPT_ATTACHMENT';

            return $this->errorResponse(
                $errorCode,
                $e->getMessage(),
                $e->errors(),
                422
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse('INVALID_TAX_CALCULATION', $e->getMessage(), null, 422);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('STORE_RECEIPT_FAILED', $e);
        }
    }

    /**
     * Endpoint detail lengkap kuitansi (GET /api/v1/receipts/{id}).
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $kuitansi = Kuitansi::resolveByIdOrNumber($id, [
            'rekening.unitKerja',
            'items',
            'pajak',
            'lampiran',
            'pptkUser',
            'creator',
            'riwayat.user',
            'sppdPeserta.pegawai',
        ]);

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
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki hak akses untuk kuitansi ini.', null, 403);
        }

        return $this->successResponse($kuitansi, 'Detail kuitansi berhasil diambil');
    }

    /**
     * Endpoint riwayat aktivitas / audit trail kuitansi (GET /api/v1/receipts/{id}/history).
     */
    public function history(Request $request, int|string $id): JsonResponse
    {
        $kuitansi = Kuitansi::resolveByIdOrNumber($id);

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

    /**
     * Endpoint pembaruan kuitansi DRAFT, REVISI_MINOR, atau REVISI_MAYOR (PUT /api/v1/receipts/{id} & POST /api/v1/receipts/{id}/update).
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $resolved = $this->findAuthorizedOperatorReceipt($request, $id);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        $kuitansi = $resolved;

        $validated = $request->validate([
            'anggaran_rekening_id' => ['nullable', 'exists:anggaran_rekening,id'],
            'bulan_anggaran' => ['nullable', 'integer', 'between:1,12'],
            'nomor_bukti_toko' => ['nullable', 'string', 'max:100'],
            'tanggal_transaksi' => ['nullable', 'date'],
            'uraian_belanja' => ['nullable', 'string'],
            'uraian' => ['nullable', 'string'],
            'nama_penerima' => ['nullable', 'string', 'max:200'],
            'npwp_penerima' => ['nullable', 'string', 'max:30'],
            'has_npwp' => ['nullable', 'boolean'],
            'golongan_asn' => ['nullable', 'string', Rule::in(['GOLONGAN_I', 'GOLONGAN_II', 'GOLONGAN_I_II', 'GOLONGAN_III', 'GOLONGAN_IV', 'NON_ASN'])],
            'is_sewa_kendaraan' => ['nullable', 'boolean'],
            'is_pkp' => ['nullable', 'boolean'],
            'metode_pembayaran' => ['nullable', 'string', 'in:NON_TUNAI_IBC,TUNAI_UP'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nama_barang' => ['required', 'string', 'max:255'],
            'items.*.volume' => ['required', 'numeric', 'min:0.01'],
            'items.*.satuan' => ['nullable', 'string', 'max:50'],
            'items.*.harga_satuan' => ['required', 'numeric', 'min:0'],
            'files.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'lampiran.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'checklist_codes.*' => ['nullable', 'string', 'max:50'],
            'nama_dokumen.*' => ['nullable', 'string', 'max:255'],
            ...$this->sppdValidationRules(),
        ]);

        if ($inactiveError = $this->validateActiveNewPegawai($validated['sppd_peserta'] ?? [], $kuitansi)) {
            return $inactiveError;
        }

        $validated['sppd_peserta_synced'] = $request->boolean('sppd_peserta_synced');
        $validated['sppd_foto_remove'] = $request->boolean('sppd_foto_remove');

        $user = $request->user();
        if (! empty($validated['anggaran_rekening_id'])) {
            $targetRekening = AnggaranRekening::find($validated['anggaran_rekening_id']);
            if ($user && $targetRekening && ! $user->canInputRekening($targetRekening)) {
                return $this->errorResponse('FORBIDDEN', 'Anda tidak berwenang memindahkan kuitansi ke rekening unit kerja lain.', null, 403);
            }
        }

        $attachments = $this->extractAttachmentsFromRequest($request);

        try {
            $updated = $this->receiptService->updateReceipt($kuitansi->id, $validated, $attachments, $user);

            return $this->successResponse([
                'receipt_id' => $updated->nomor_kuitansi,
                'id' => $updated->id,
                'status' => $updated->status,
                'nominal_bruto' => $updated->nominal_bruto,
                'total_pajak' => $updated->total_pajak,
                'nominal_neto' => $updated->nominal_neto,
                'kuitansi' => $updated,
            ], 'Kuitansi berhasil diperbarui');
        } catch (ValidationException $e) {
            $errorCode = array_key_exists('sppd_peserta', $e->errors())
                ? 'SPPD_SCHEDULE_CONFLICT'
                : 'VALIDATION_ERROR';

            return $this->errorResponse($errorCode, $e->getMessage(), $e->errors(), 422);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse('INVALID_RECEIPT_STATE', $e->getMessage(), null, 422);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('UPDATE_RECEIPT_FAILED', $e);
        }
    }

    /**
     * Endpoint hapus satu lampiran kuitansi (DELETE /api/v1/receipts/{id}/attachments/{attachmentId}).
     */
    public function deleteAttachment(Request $request, int|string $id, int $attachmentId): JsonResponse
    {
        $resolved = $this->findAuthorizedOperatorReceipt($request, $id);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        $kuitansi = $resolved;

        try {
            $this->receiptService->deleteAttachment($kuitansi->id, $attachmentId, $request->user());

            return $this->successResponse(null, 'Lampiran kuitansi berhasil dihapus');
        } catch (ModelNotFoundException) {
            return $this->errorResponse('ATTACHMENT_NOT_FOUND', 'Lampiran tidak ditemukan pada kuitansi ini.', null, 404);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse('INVALID_RECEIPT_STATE', $e->getMessage(), null, 422);
        } catch (Throwable $e) {
            return $this->serverErrorResponse('DELETE_ATTACHMENT_FAILED', $e);
        }
    }

    /**
     * Mengekstrak berkas lampiran beserta metadata checklist_code dan nama_dokumen dari request.
     *
     * @return array<int, array{file: UploadedFile, checklist_code: string, nama_dokumen: string}>
     */
    private function extractAttachmentsFromRequest(Request $request): array
    {
        $files = $request->file('files') ?? $request->file('lampiran') ?? [];
        if (! is_array($files)) {
            $files = [$files];
        }

        $checklistCodes = $request->input('checklist_codes', []);
        $namaDokumen = $request->input('nama_dokumen', []);

        $attachments = [];
        foreach (array_values($files) as $idx => $file) {
            if (! $file) {
                continue;
            }
            $attachments[] = [
                'file' => $file,
                'checklist_code' => is_array($checklistCodes) && ! empty($checklistCodes[$idx])
                    ? (string) $checklistCodes[$idx]
                    : 'B.1.4',
                'nama_dokumen' => is_array($namaDokumen) && ! empty($namaDokumen[$idx])
                    ? (string) $namaDokumen[$idx]
                    : $file->getClientOriginalName(),
            ];
        }

        return $attachments;
    }

    /**
     * Validasi parameter filter rentang tanggal dan format ekspor secara kondisional.
     */
    private function validateReceiptFilters(Request $request, bool $withFormat = false): void
    {
        $rules = [
            'tanggal_dari' => ['nullable', 'date'],
            'tanggal_sampai' => ['nullable', 'date'],
        ];

        if ($request->filled('tanggal_dari')) {
            $rules['tanggal_sampai'][] = 'after_or_equal:tanggal_dari';
        }

        if ($withFormat) {
            $rules['format'] = ['nullable', 'string', Rule::in(['xlsx', 'pdf', 'csv'])];
        }

        $request->validate($rules);
    }

    /**
     * Aturan validasi payload peserta dan foto keberangkatan SPPD.
     *
     * @return array<string, mixed>
     */
    private function sppdValidationRules(): array
    {
        return [
            'sppd_peserta_synced' => ['nullable', 'boolean'],
            'sppd_peserta' => ['nullable', 'array'],
            'sppd_peserta.*.pegawai_id' => ['required_with:sppd_peserta', 'integer', 'exists:pegawai,id'],
            'sppd_peserta.*.tanggal_berangkat' => ['required_with:sppd_peserta', 'date'],
            'sppd_peserta.*.tanggal_pulang' => ['required_with:sppd_peserta', 'date', 'after_or_equal:sppd_peserta.*.tanggal_berangkat'],
            'sppd_peserta.*.tujuan' => ['nullable', 'string', 'max:255'],
            'sppd_peserta.*.foto' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
            'sppd_peserta.*.remove_foto' => ['nullable', 'boolean'],
            'sppd_foto' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
            'sppd_foto_remove' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Memastikan pegawai baru yang ditambahkan ke SPPD berstatus aktif.
     *
     * @param  array<int, array<string, mixed>>  $participants
     */
    private function validateActiveNewPegawai(array $participants, ?Kuitansi $existingKuitansi = null): ?JsonResponse
    {
        if (empty($participants)) {
            return null;
        }

        $existingPegawaiIds = $existingKuitansi
            ? $existingKuitansi->sppdPeserta()->pluck('pegawai_id')->map(fn ($id) => (int) $id)->all()
            : [];

        $newPegawaiIds = collect($participants)
            ->pluck('pegawai_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => in_array($id, $existingPegawaiIds, true))
            ->unique()
            ->values()
            ->all();

        if (empty($newPegawaiIds)) {
            return null;
        }

        $inactiveNames = Pegawai::whereIn('id', $newPegawaiIds)
            ->where('is_active', false)
            ->pluck('nama')
            ->all();

        if (! empty($inactiveNames)) {
            $list = implode(', ', $inactiveNames);

            return $this->errorResponse(
                'INACTIVE_PEGAWAI',
                "Pegawai berstatus nonaktif tidak dapat ditambahkan ke SPPD baru: {$list}.",
                ['sppd_peserta' => ["Pegawai nonaktif: {$list}."]],
                422
            );
        }

        return null;
    }

    /**
     * Menyimpan Kode Billing, NTPN, tanggal setor, dan bukti setor per baris kuitansi_pajak.
     * Endpoint: POST /api/v1/receipts/{id}/tax-settlement
     */
    public function taxSettlement(Request $request, int|string $id): JsonResponse
    {
        $kuitansi = Kuitansi::resolveByIdOrNumber($id);
        if (! $kuitansi) {
            return $this->errorResponse('RECEIPT_NOT_FOUND', 'Kuitansi tidak ditemukan.', null, 404);
        }

        $user = $request->user();
        if (! $user || $user->isReadOnlyRole() || ! $user->canAccessReceipt($kuitansi)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak berwenang memperbarui setoran pajak kuitansi ini.', null, 403);
        }

        if ($kuitansi->status === 'SELESAI_SP2D') {
            return $this->errorResponse('RECEIPT_SETTLED_LOCKED', 'Kuitansi yang telah selesai SP2D tidak dapat diubah data setoran pajaknya.', null, 422);
        }

        $validated = $request->validate([
            'kuitansi_pajak_id' => ['required_without:settlements', 'integer'],
            'kode_billing' => ['nullable', 'string', 'max:30'],
            'ntpn' => ['required_without:settlements', 'string', 'max:30'],
            'tanggal_setor' => ['nullable', 'date'],
            'bukti_setor' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'settlements' => ['required_without:kuitansi_pajak_id', 'array', 'min:1'],
            'settlements.*.kuitansi_pajak_id' => ['required', 'integer'],
            'settlements.*.kode_billing' => ['nullable', 'string', 'max:30'],
            'settlements.*.ntpn' => ['required', 'string', 'max:30'],
            'settlements.*.tanggal_setor' => ['nullable', 'date'],
            'settlements.*.bukti_setor' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $items = $validated['settlements'] ?? [[
            'kuitansi_pajak_id' => $validated['kuitansi_pajak_id'],
            'kode_billing' => $validated['kode_billing'] ?? null,
            'ntpn' => $validated['ntpn'],
            'tanggal_setor' => $validated['tanggal_setor'] ?? null,
            'bukti_setor' => $request->file('bukti_setor'),
        ]];

        foreach ($items as $idx => $entry) {
            $pajak = KuitansiPajak::where('kuitansi_id', $kuitansi->id)
                ->where('id', $entry['kuitansi_pajak_id'])
                ->first();

            if (! $pajak) {
                return $this->errorResponse(
                    'TAX_ROW_NOT_FOUND',
                    "Baris pajak ID {$entry['kuitansi_pajak_id']} tidak ditemukan pada kuitansi ini.",
                    null,
                    422
                );
            }

            $file = $entry['bukti_setor'] ?? $request->file("settlements.{$idx}.bukti_setor");
            $buktiPath = $pajak->bukti_setor_path;

            if ($file instanceof UploadedFile) {
                $buktiPath = $file->store("tax-settlements/{$kuitansi->id}", 'local');
            }

            $pajak->update([
                'kode_billing' => $entry['kode_billing'] ?? null,
                'ntpn' => trim((string) $entry['ntpn']),
                'tanggal_setor' => $entry['tanggal_setor'] ?? now()->toDateString(),
                'bukti_setor_path' => $buktiPath,
                'ntpn_updated_by' => $user->id,
                'ntpn_updated_at' => now(),
            ]);
        }

        return $this->successResponse(
            $kuitansi->pajak()->orderBy('id')->get(),
            'Data setoran pajak (Kode Billing / NTPN) berhasil disimpan.'
        );
    }
}
