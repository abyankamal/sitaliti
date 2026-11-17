<?php

namespace App\Services;

use App\Contracts\BudgetReservationServiceInterface;
use App\Contracts\TaxCalculationServiceInterface;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiItem;
use App\Models\KuitansiLampiran;
use App\Models\KuitansiPajak;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ReceiptService
{
    protected AuditTrailService $auditTrailService;

    protected ReceiptWorkflowService $workflowService;

    public function __construct(
        protected TaxCalculationServiceInterface $taxService,
        protected BudgetReservationServiceInterface $budgetService,
        ?AuditTrailService $auditTrailService = null,
        ?ReceiptWorkflowService $workflowService = null
    ) {
        $this->auditTrailService = $auditTrailService ?? app(AuditTrailService::class);
        $this->workflowService = $workflowService ?? app(ReceiptWorkflowService::class);
    }

    /**
     * Simulasi validasi draft kuitansi & perhitungan pajak (tanpa persistensi).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validateDraft(array $data): array
    {
        $rekeningId = (string) ($data['anggaran_rekening_id'] ?? '');
        $bulan = (int) ($data['bulan_anggaran'] ?? (int) date('n'));
        $kategori = (string) ($data['kategori_belanja'] ?? 'B1_ATK');
        $hasNpwp = (bool) ($data['has_npwp'] ?? ! empty($data['npwp_penerima']));
        $golonganAsn = $data['golongan_asn'] ?? null;

        // Hitung total belanja dari rincian barang
        $items = $data['items'] ?? [];
        $totalBruto = 0.0;
        foreach ($items as $item) {
            $volume = (float) ($item['volume'] ?? 0);
            $harga = (float) ($item['harga_satuan'] ?? 0);
            $totalBruto += round($volume * $harga, 2);
        }

        // Kalkulasi Pajak
        $taxResult = $this->taxService->calculateTax($kategori, $totalBruto, [
            'has_npwp' => $hasNpwp,
            'golongan_asn' => $golonganAsn,
            'is_sewa_kendaraan' => (bool) ($data['is_sewa_kendaraan'] ?? false),
            'is_pkp' => (bool) ($data['is_pkp'] ?? true),
        ]);

        // Cek Pagu Anggaran
        $budgetData = $this->budgetService->getPopupAllocationData($rekeningId, $bulan);
        $sisaPagu = $budgetData['sisa_dapat_digunakan'] ?? 0.0;
        $isExceedingBudget = $totalBruto > $sisaPagu;

        return [
            'success' => ! $isExceedingBudget,
            'total_belanja' => $totalBruto,
            'sisa_pagu_tersedia' => $sisaPagu,
            'is_exceeding_budget' => $isExceedingBudget,
            'tax_calculation' => $taxResult,
            'budget_context' => $budgetData,
        ];
    }

    /**
     * Menyimpan draft kuitansi baru beserta item, pajak, dan berkas fisik (dengan deteksi SHA-256 instan).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array{file: UploadedFile, checklist_code?: string, nama_dokumen?: string}>|array<int, UploadedFile>  $attachments
     */
    public function createDraft(array $data, array $attachments = [], ?User $user = null): Kuitansi
    {
        $rekening = AnggaranRekening::findOrFail($data['anggaran_rekening_id']);
        $bulan = (int) ($data['bulan_anggaran'] ?? (int) date('n'));
        $userId = $user?->id ?? (int) ($data['user_id'] ?? 1);

        $processedAttachments = $this->prepareAttachments($attachments, $rekening->id);
        $taxOptions = $this->resolveTaxOptions($data);
        [$itemsToSave, $totalBruto, $taxCalc, $totalPajak, $nominalNeto] = $this->calculateItemsAndTaxes(
            $rekening,
            array_merge($data, $taxOptions)
        );

        $tahun = date('Y');
        $bulanStr = str_pad((string) $bulan, 2, '0', STR_PAD_LEFT);
        $prefix = "KW-{$tahun}-{$bulanStr}-";

        return DB::transaction(function () use (
            $prefix,
            $rekening,
            $userId,
            $user,
            $bulan,
            $tahun,
            $bulanStr,
            $data,
            $taxOptions,
            $totalBruto,
            $totalPajak,
            $nominalNeto,
            $itemsToSave,
            $taxCalc,
            $processedAttachments
        ) {
            $nomorKuitansi = $data['nomor_kuitansi'] ?? $this->generateNextNomorKuitansi($prefix);

            $kuitansi = Kuitansi::create([
                'nomor_kuitansi' => $nomorKuitansi,
                'unit_kerja_id' => $rekening->unit_kerja_id,
                'anggaran_rekening_id' => $rekening->id,
                'user_id' => $userId,
                'bulan_anggaran' => $bulan,
                'nomor_bukti_toko' => $data['nomor_bukti_toko'] ?? null,
                'tanggal_transaksi' => $data['tanggal_transaksi'] ?? date('Y-m-d'),
                'uraian' => $data['uraian_belanja'] ?? $data['uraian'] ?? 'Belanja Kuitansi',
                'nama_penerima' => $data['nama_penerima'],
                'npwp_penerima' => $data['npwp_penerima'] ?? null,
                'metode_pembayaran' => $data['metode_pembayaran'] ?? 'NON_TUNAI_IBC',
                'golongan_asn' => $taxOptions['golongan_asn'],
                'is_sewa_kendaraan' => $taxOptions['is_sewa_kendaraan'],
                'is_pkp' => $taxOptions['is_pkp'],
                'nominal_bruto' => $totalBruto,
                'total_pajak' => $totalPajak,
                'nominal_neto' => $nominalNeto,
                'status' => 'DRAFT',
            ]);

            $this->syncReceiptItemsAndTaxes($kuitansi, $itemsToSave, $taxCalc);
            $this->storeAttachments($kuitansi, $rekening->id, $processedAttachments, $tahun, $bulanStr);

            $this->auditTrailService->log(
                $kuitansi,
                'DRAFT_CREATED',
                null,
                'DRAFT',
                'Pembuatan draft kuitansi belanja',
                $user,
                [
                    'nominal_bruto' => (float) $kuitansi->nominal_bruto,
                    'nominal_neto' => (float) $kuitansi->nominal_neto,
                    'bulan_anggaran' => $kuitansi->bulan_anggaran,
                ]
            );

            return $kuitansi->load(['items', 'pajak', 'lampiran', 'rekening', 'unitKerja']);
        });
    }

    /**
     * Memperbarui kuitansi pada status DRAFT, REVISI_MINOR, atau REVISI_MAYOR.
     * Pada REVISI_MINOR, rekening anggaran, bulan anggaran, dan nominal bruto dikunci agar pagu terkunci tetap konsisten.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array{file: UploadedFile, checklist_code?: string, nama_dokumen?: string}>|array<int, UploadedFile>  $attachments
     */
    public function updateReceipt(int|string $kuitansiId, array $data, array $attachments = [], ?User $actor = null): Kuitansi
    {
        return DB::transaction(function () use ($kuitansiId, $data, $attachments, $actor) {
            $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($kuitansiId, lockForUpdate: true);

            if (! in_array($kuitansi->status, ['DRAFT', 'REVISI_MINOR', 'REVISI_MAYOR'], true)) {
                throw new InvalidArgumentException("Kuitansi dengan status '{$kuitansi->status}' tidak dapat diedit.");
            }

            $isMinorRevision = $kuitansi->status === 'REVISI_MINOR';
            $targetRekeningId = $data['anggaran_rekening_id'] ?? $kuitansi->anggaran_rekening_id;
            $targetBulan = (int) ($data['bulan_anggaran'] ?? $kuitansi->bulan_anggaran);

            if ($isMinorRevision) {
                if ((int) $targetRekeningId !== (int) $kuitansi->anggaran_rekening_id) {
                    throw ValidationException::withMessages([
                        'anggaran_rekening_id' => ['Rekening anggaran tidak dapat diubah saat status REVISI_MINOR.'],
                    ]);
                }

                if ($targetBulan !== (int) $kuitansi->bulan_anggaran) {
                    throw ValidationException::withMessages([
                        'bulan_anggaran' => ['Bulan anggaran tidak dapat diubah saat status REVISI_MINOR.'],
                    ]);
                }
            }

            $rekening = AnggaranRekening::findOrFail($targetRekeningId);
            $taxOptions = $this->resolveTaxOptions($data, $kuitansi);
            $calcPayload = array_merge($data, $taxOptions, [
                'npwp_penerima' => array_key_exists('npwp_penerima', $data) ? $data['npwp_penerima'] : $kuitansi->npwp_penerima,
            ]);
            [$itemsToSave, $totalBruto, $taxCalc, $totalPajak, $nominalNeto] = $this->calculateItemsAndTaxes($rekening, $calcPayload);

            if ($isMinorRevision && round($totalBruto, 2) !== round((float) $kuitansi->nominal_bruto, 2)) {
                throw ValidationException::withMessages([
                    'nominal_bruto' => ['Nominal bruto tidak dapat diubah saat status REVISI_MINOR karena pagu anggaran sudah terkunci.'],
                ]);
            }

            $processedAttachments = $this->prepareAttachments($attachments, $rekening->id, $kuitansi->id);
            $statusLama = $kuitansi->status;

            if ((int) $rekening->id !== (int) $kuitansi->anggaran_rekening_id) {
                $existingHashes = KuitansiLampiran::where('kuitansi_id', $kuitansi->id)->pluck('file_hash');
                if ($existingHashes->isNotEmpty()) {
                    $collision = KuitansiLampiran::where('anggaran_rekening_id', $rekening->id)
                        ->whereIn('file_hash', $existingHashes)
                        ->exists();

                    if ($collision) {
                        throw ValidationException::withMessages([
                            'anggaran_rekening_id' => ['Lampiran kuitansi ini bentrok dengan bukti identik yang sudah ada pada rekening tujuan.'],
                        ]);
                    }

                    KuitansiLampiran::where('kuitansi_id', $kuitansi->id)
                        ->update(['anggaran_rekening_id' => $rekening->id]);
                }
            }

            $kuitansi->update([
                'unit_kerja_id' => $rekening->unit_kerja_id,
                'anggaran_rekening_id' => $rekening->id,
                'bulan_anggaran' => $targetBulan,
                'nomor_bukti_toko' => array_key_exists('nomor_bukti_toko', $data) ? $data['nomor_bukti_toko'] : $kuitansi->nomor_bukti_toko,
                'tanggal_transaksi' => $data['tanggal_transaksi'] ?? $kuitansi->tanggal_transaksi,
                'uraian' => $data['uraian_belanja'] ?? $data['uraian'] ?? $kuitansi->uraian,
                'nama_penerima' => $data['nama_penerima'] ?? $kuitansi->nama_penerima,
                'npwp_penerima' => array_key_exists('npwp_penerima', $data) ? $data['npwp_penerima'] : $kuitansi->npwp_penerima,
                'metode_pembayaran' => $data['metode_pembayaran'] ?? $kuitansi->metode_pembayaran,
                'golongan_asn' => $taxOptions['golongan_asn'],
                'is_sewa_kendaraan' => $taxOptions['is_sewa_kendaraan'],
                'is_pkp' => $taxOptions['is_pkp'],
                'nominal_bruto' => $totalBruto,
                'total_pajak' => $totalPajak,
                'nominal_neto' => $nominalNeto,
            ]);

            $this->syncReceiptItemsAndTaxes($kuitansi, $itemsToSave, $taxCalc);

            $tahun = date('Y');
            $bulanStr = str_pad((string) $targetBulan, 2, '0', STR_PAD_LEFT);
            $this->storeAttachments($kuitansi, $rekening->id, $processedAttachments, $tahun, $bulanStr);

            $this->auditTrailService->log(
                $kuitansi,
                'RECEIPT_UPDATED',
                $statusLama,
                $kuitansi->status,
                'Pembaruan data dan/atau lampiran kuitansi',
                $actor,
                [
                    'nominal_bruto' => (float) $kuitansi->nominal_bruto,
                    'nominal_neto' => (float) $kuitansi->nominal_neto,
                    'bulan_anggaran' => $kuitansi->bulan_anggaran,
                ]
            );

            return $kuitansi->load(['items', 'pajak', 'lampiran', 'rekening', 'unitKerja']);
        });
    }

    /**
     * Menghapus satu berkas lampiran kuitansi pada status DRAFT, REVISI_MINOR, atau REVISI_MAYOR.
     */
    public function deleteAttachment(int|string $kuitansiId, int $attachmentId, ?User $actor = null): void
    {
        DB::transaction(function () use ($kuitansiId, $attachmentId, $actor) {
            $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($kuitansiId, lockForUpdate: true);

            if (! in_array($kuitansi->status, ['DRAFT', 'REVISI_MINOR', 'REVISI_MAYOR'], true)) {
                throw new InvalidArgumentException("Lampiran kuitansi dengan status '{$kuitansi->status}' tidak dapat dihapus.");
            }

            $lampiran = KuitansiLampiran::where('kuitansi_id', $kuitansi->id)
                ->where('id', $attachmentId)
                ->firstOrFail();

            $filePath = $lampiran->file_path;
            $fileName = $lampiran->file_name;
            $lampiran->delete();

            $this->auditTrailService->log(
                $kuitansi,
                'ATTACHMENT_DELETED',
                $kuitansi->status,
                $kuitansi->status,
                "Menghapus lampiran '{$fileName}'",
                $actor
            );

            // Hapus file fisik hanya setelah transaksi DB commit agar rollback tidak menghilangkan bukti asli.
            DB::afterCommit(function () use ($filePath) {
                if ($filePath && Storage::disk('local')->exists($filePath)) {
                    Storage::disk('local')->delete($filePath);
                }
            });
        });
    }

    public function resubmitMinorRevision(int|string $kuitansiId, ?User $actor = null): Kuitansi
    {
        return $this->workflowService->resubmitMinorRevision($kuitansiId, $actor);
    }

    public function submitToPptk(int|string $kuitansiId, ?User $actor = null): Kuitansi
    {
        return $this->workflowService->submitToPptk($kuitansiId, $actor);
    }

    public function approveByPptk(int|string $kuitansiId, ?string $catatan = null, ?User $actor = null): Kuitansi
    {
        return $this->workflowService->approveByPptk($kuitansiId, $catatan, $actor);
    }

    public function rejectByPptk(int|string $kuitansiId, string $alasan, ?User $actor = null): Kuitansi
    {
        return $this->workflowService->rejectByPptk($kuitansiId, $alasan, $actor);
    }

    public function approveByLurah(int|string $kuitansiId, ?string $catatan = null, ?User $actor = null): Kuitansi
    {
        return $this->workflowService->approveByLurah($kuitansiId, $catatan, $actor);
    }

    public function rejectByLurah(int|string $kuitansiId, string $alasan, ?User $actor = null): Kuitansi
    {
        return $this->workflowService->rejectByLurah($kuitansiId, $alasan, $actor);
    }

    /**
     * Menghasilkan nomor kuitansi berikutnya yang unik secara berurutan di dalam transaksi.
     */
    private function generateNextNomorKuitansi(string $prefix): string
    {
        $lastNomor = Kuitansi::query()
            ->where('nomor_kuitansi', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('nomor_kuitansi')
            ->value('nomor_kuitansi');

        $nextSeq = 1;
        if (is_string($lastNomor) && str_starts_with($lastNomor, $prefix)) {
            $suffix = substr($lastNomor, strlen($prefix));
            if (ctype_digit($suffix)) {
                $nextSeq = (int) $suffix + 1;
            }
        }

        do {
            $candidate = $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
            $exists = Kuitansi::query()->where('nomor_kuitansi', $candidate)->exists();
            $nextSeq++;
        } while ($exists);

        return $candidate;
    }

    /**
     * Memvalidasi duplikasi SHA-256 dan menormalisasi metadata lampiran.
     *
     * @param  array<int, array{file: UploadedFile, checklist_code?: string, nama_dokumen?: string}>|array<int, UploadedFile>  $attachments
     * @return array<int, array{file: UploadedFile, hash: string, checklist_code: string, nama_dokumen: string}>
     */
    private function prepareAttachments(array $attachments, int $rekeningId, ?int $ignoreKuitansiId = null): array
    {
        $processed = [];
        $seenHashes = [];

        foreach ($attachments as $att) {
            $file = ($att instanceof UploadedFile) ? $att : ($att['file'] ?? null);
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $fileHash = hash_file('sha256', $file->getRealPath());

            if (isset($seenHashes[$fileHash])) {
                throw ValidationException::withMessages([
                    'lampiran' => [
                        sprintf(
                            "Berkas '%s' duplikat dengan berkas lain dalam unggahan yang sama.",
                            $file->getClientOriginalName()
                        ),
                    ],
                ]);
            }

            $exists = KuitansiLampiran::where('anggaran_rekening_id', $rekeningId)
                ->where('file_hash', $fileHash)
                ->when($ignoreKuitansiId !== null, fn ($q) => $q->where('kuitansi_id', '!=', $ignoreKuitansiId))
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'lampiran' => [
                        sprintf(
                            "Nota atau bukti fisik identik ('%s') telah diunggah sebelumnya pada rekening anggaran ini. Sistem menolak duplikasi bukti transaksi.",
                            $file->getClientOriginalName()
                        ),
                    ],
                ]);
            }

            $seenHashes[$fileHash] = true;

            $processed[] = [
                'file' => $file,
                'hash' => $fileHash,
                'checklist_code' => is_array($att) ? ($att['checklist_code'] ?? 'B.1.4') : 'B.1.4',
                'nama_dokumen' => is_array($att) ? ($att['nama_dokumen'] ?? $file->getClientOriginalName()) : $file->getClientOriginalName(),
            ];
        }

        return $processed;
    }

    /**
     * Menghitung rincian item, total bruto, dan potongan pajak sesuai kategori rekening.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<int, array<string, mixed>>, 1: float, 2: array<string, mixed>, 3: float, 4: float}
     */
    private function calculateItemsAndTaxes(AnggaranRekening $rekening, array $data): array
    {
        $itemsData = $data['items'] ?? [];
        if (empty($itemsData)) {
            throw new InvalidArgumentException('Kuitansi wajib memiliki minimal 1 rincian barang/jasa.');
        }

        $totalBruto = 0.0;
        $itemsToSave = [];
        foreach ($itemsData as $item) {
            $volume = (float) ($item['volume'] ?? 0);
            $harga = (float) ($item['harga_satuan'] ?? 0);
            $totalHarga = round($volume * $harga, 2);
            $totalBruto += $totalHarga;

            $itemsToSave[] = [
                'nama_barang' => $item['nama_barang'],
                'volume' => $volume,
                'satuan' => $item['satuan'] ?? 'Unit',
                'harga_satuan' => $harga,
                'total_harga' => $totalHarga,
            ];
        }

        $kategori = $rekening->kategori_belanja ?? 'B1_ATK';
        $hasNpwp = (bool) ($data['has_npwp'] ?? ! empty($data['npwp_penerima']));
        $golonganAsn = $data['golongan_asn'] ?? null;

        $taxCalc = $this->taxService->calculateTax($kategori, $totalBruto, [
            'has_npwp' => $hasNpwp,
            'golongan_asn' => $golonganAsn,
            'is_sewa_kendaraan' => (bool) ($data['is_sewa_kendaraan'] ?? false),
            'is_pkp' => (bool) ($data['is_pkp'] ?? true),
        ]);

        $totalPajak = (float) $taxCalc['total_pajak'];
        $nominalNeto = $totalBruto - $totalPajak;

        return [$itemsToSave, $totalBruto, $taxCalc, $totalPajak, $nominalNeto];
    }

    /**
     * Menentukan opsi pajak yang persisten dengan mempertahankan nilai lama pada pembaruan parsial.
     *
     * @param  array<string, mixed>  $data
     * @return array{golongan_asn: string, is_sewa_kendaraan: bool, is_pkp: bool}
     */
    private function resolveTaxOptions(array $data, ?Kuitansi $existing = null): array
    {
        return [
            'golongan_asn' => isset($data['golongan_asn']) && $data['golongan_asn'] !== ''
                ? (string) $data['golongan_asn']
                : ($existing?->golongan_asn ?? 'GOLONGAN_III'),
            'is_sewa_kendaraan' => array_key_exists('is_sewa_kendaraan', $data) && $data['is_sewa_kendaraan'] !== null
                ? (bool) $data['is_sewa_kendaraan']
                : (bool) ($existing?->is_sewa_kendaraan ?? false),
            'is_pkp' => array_key_exists('is_pkp', $data) && $data['is_pkp'] !== null
                ? (bool) $data['is_pkp']
                : (bool) ($existing?->is_pkp ?? true),
        ];
    }

    /**
     * Menyinkronkan ulang rincian barang dan potongan pajak kuitansi.
     *
     * @param  array<int, array<string, mixed>>  $itemsToSave
     * @param  array<string, mixed>  $taxCalc
     */
    private function syncReceiptItemsAndTaxes(Kuitansi $kuitansi, array $itemsToSave, array $taxCalc): void
    {
        $kuitansi->items()->delete();
        foreach ($itemsToSave as $item) {
            $item['kuitansi_id'] = $kuitansi->id;
            KuitansiItem::create($item);
        }

        $kuitansi->pajak()->delete();
        foreach ($taxCalc['rincian_pajak'] as $rp) {
            KuitansiPajak::create([
                'kuitansi_id' => $kuitansi->id,
                'kode_pajak' => $rp['kode_pajak'],
                'nama_pajak' => $rp['nama_pajak'],
                'tarif' => $rp['tarif'],
                'tipe_tarif' => $rp['tipe_tarif'],
                'dpp' => $taxCalc['dpp'],
                'nominal' => $rp['nominal'],
            ]);
        }
    }

    /**
     * Menyimpan berkas lampiran ke private storage dan mencatat metadata KuitansiLampiran.
     *
     * @param  array<int, array{file: UploadedFile, hash: string, checklist_code: string, nama_dokumen: string}>  $processedAttachments
     */
    private function storeAttachments(Kuitansi $kuitansi, int $rekeningId, array $processedAttachments, string $tahun, string $bulanStr): void
    {
        foreach ($processedAttachments as $att) {
            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $att['file'];
            $path = $uploadedFile->store("receipts/{$tahun}/{$bulanStr}", 'local');

            KuitansiLampiran::create([
                'kuitansi_id' => $kuitansi->id,
                'anggaran_rekening_id' => $rekeningId,
                'checklist_code' => $att['checklist_code'],
                'nama_dokumen' => $att['nama_dokumen'],
                'file_path' => $path,
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_size' => $uploadedFile->getSize(),
                'mime_type' => $uploadedFile->getMimeType() ?? 'application/octet-stream',
                'file_hash' => $att['hash'],
            ]);
        }
    }
}
