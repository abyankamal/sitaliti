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

    public function __construct(
        protected TaxCalculationServiceInterface $taxService,
        protected BudgetReservationServiceInterface $budgetService,
        ?AuditTrailService $auditTrailService = null
    ) {
        $this->auditTrailService = $auditTrailService ?? app(AuditTrailService::class);
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

        // 1. Synchronous SHA-256 Anti-Duplication Check:
        // Tolak seketika (sub-50ms) jika ada berkas yang memiliki hash identik pada rekening belanja terkait.
        $processedAttachments = [];
        foreach ($attachments as $att) {
            $file = ($att instanceof UploadedFile) ? $att : ($att['file'] ?? null);
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $fileHash = hash_file('sha256', $file->getRealPath());

            // Periksa apakah hash sudah pernah diunggah pada rekening ini
            $exists = KuitansiLampiran::where('anggaran_rekening_id', $rekening->id)
                ->where('file_hash', $fileHash)
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

            $processedAttachments[] = [
                'file' => $file,
                'hash' => $fileHash,
                'checklist_code' => is_array($att) ? ($att['checklist_code'] ?? 'B.1.4') : 'B.1.4',
                'nama_dokumen' => is_array($att) ? ($att['nama_dokumen'] ?? $file->getClientOriginalName()) : $file->getClientOriginalName(),
            ];
        }

        // 2. Hitung Total Bruto dan Rincian Barang
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

        // 3. Kalkulasi Pajak Sesuai Regulasi
        $kategori = $rekening->kategori_belanja ?? 'B1_ATK';
        $hasNpwp = (bool) ($data['has_npwp'] ?? ! empty($data['npwp_penerima']));
        $golonganAsn = $data['golongan_asn'] ?? null;

        $taxCalc = $this->taxService->calculateTax($kategori, $totalBruto, [
            'has_npwp' => $hasNpwp,
            'golongan_asn' => $golonganAsn,
        ]);

        $totalPajak = $taxCalc['total_pajak'];
        $nominalNeto = $totalBruto - $totalPajak;

        // 4. Generate Nomor Kuitansi Berurutan
        $tahun = date('Y');
        $bulanStr = str_pad((string) $bulan, 2, '0', STR_PAD_LEFT);
        $randomSeq = str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $nomorKuitansi = $data['nomor_kuitansi'] ?? "KW-{$tahun}-{$bulanStr}-{$randomSeq}";

        return DB::transaction(function () use (
            $nomorKuitansi,
            $rekening,
            $userId,
            $user,
            $bulan,
            $tahun,
            $bulanStr,
            $data,
            $totalBruto,
            $totalPajak,
            $nominalNeto,
            $itemsToSave,
            $taxCalc,
            $processedAttachments
        ) {
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
                'nominal_bruto' => $totalBruto,
                'total_pajak' => $totalPajak,
                'nominal_neto' => $nominalNeto,
                'status' => 'DRAFT',
            ]);

            // Simpan Rincian Barang
            foreach ($itemsToSave as $item) {
                $item['kuitansi_id'] = $kuitansi->id;
                KuitansiItem::create($item);
            }

            // Simpan Potongan Pajak
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

            // Simpan Berkas Lampiran ke Private Storage
            foreach ($processedAttachments as $att) {
                /** @var UploadedFile $uploadedFile */
                $uploadedFile = $att['file'];
                $path = $uploadedFile->store("receipts/{$tahun}/{$bulanStr}", 'local');

                KuitansiLampiran::create([
                    'kuitansi_id' => $kuitansi->id,
                    'anggaran_rekening_id' => $rekening->id,
                    'checklist_code' => $att['checklist_code'],
                    'nama_dokumen' => $att['nama_dokumen'],
                    'file_path' => $path,
                    'file_name' => $uploadedFile->getClientOriginalName(),
                    'file_size' => $uploadedFile->getSize(),
                    'mime_type' => $uploadedFile->getMimeType() ?? 'application/octet-stream',
                    'file_hash' => $att['hash'],
                ]);
            }

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
     * Mengajukan kuitansi ke PPTK: memanggil reserveBudget() secara atomic dan mengubah status.
     */
    public function submitToPptk(int|string $kuitansiId, ?User $actor = null): Kuitansi
    {
        return DB::transaction(function () use ($kuitansiId, $actor) {
            $kuitansi = Kuitansi::where('id', $kuitansiId)->lockForUpdate()->firstOrFail();

            if (! in_array($kuitansi->status, ['DRAFT', 'REVISI_MAYOR'])) {
                throw new InvalidArgumentException('Hanya kuitansi berstatus DRAFT atau REVISI_MAYOR yang dapat diajukan ke PPTK.');
            }

            $statusLama = $kuitansi->status;

            // Kunci pagu anggaran via BudgetReservationService
            $this->budgetService->reserveBudget(
                (string) $kuitansi->anggaran_rekening_id,
                $kuitansi->bulan_anggaran,
                (float) $kuitansi->nominal_bruto
            );

            $kuitansi->update([
                'status' => 'MENUNGGU_PPTK',
                'tanggal_submit' => now(),
            ]);

            $this->auditTrailService->log(
                $kuitansi,
                'SUBMITTED_TO_PPTK',
                $statusLama,
                'MENUNGGU_PPTK',
                'Pengajuan kuitansi ke PPTK dan penguncian pagu kas',
                $actor,
                [
                    'pagu_dikunci' => (float) $kuitansi->nominal_bruto,
                    'bulan_anggaran' => $kuitansi->bulan_anggaran,
                ]
            );

            return $kuitansi;
        });
    }

    /**
     * Persetujuan PPTK: Memvalidasi kuitansi berstatus MENUNGGU_PPTK dan memindahkan ke MENUNGGU_VERIFIKASI.
     * Menggunakan pessimistic lock dan mencegah mutasi pada kuitansi yang telah masuk bundel SPJ.
     * Tidak mengunci ulang pagu karena pagu telah dikunci saat submit oleh operator (Zero-Double-Lock).
     */
    public function approveByPptk(int|string $kuitansiId, ?string $catatan = null, ?User $actor = null): Kuitansi
    {
        return DB::transaction(function () use ($kuitansiId, $catatan, $actor) {
            $kuitansi = Kuitansi::where('id', $kuitansiId)->lockForUpdate()->firstOrFail();

            if ($kuitansi->status !== 'MENUNGGU_PPTK') {
                throw new InvalidArgumentException('Hanya kuitansi berstatus MENUNGGU_PPTK yang dapat disetujui oleh PPTK.');
            }

            if ($actor !== null && ! $actor->canAccessReceipt($kuitansi)) {
                throw new InvalidArgumentException('PPTK tidak memiliki hak akses untuk menyetujui kuitansi ini.');
            }

            if ($kuitansi->activeSpjItem()->exists()) {
                throw new InvalidArgumentException('Kuitansi yang telah masuk ke dalam bundel pengajuan SPJ aktif tidak dapat disetujui ulang.');
            }

            $statusLama = $kuitansi->status;
            $kuitansi->loadMissing('unitKerja');
            $statusBaru = ($kuitansi->unitKerja?->tipe === 'KELURAHAN') ? 'MENUNGGU_LURAH' : 'MENUNGGU_VERIFIKASI';

            $kuitansi->update([
                'status' => $statusBaru,
                'pptk_user_id' => $actor?->id,
                'tanggal_pptk_approve' => now(),
                'catatan_pptk' => $catatan,
            ]);

            $this->auditTrailService->log(
                $kuitansi,
                'PPTK_APPROVED',
                $statusLama,
                $statusBaru,
                $catatan ?? 'Disetujui oleh PPTK',
                $actor
            );

            return $kuitansi;
        });
    }

    /**
     * Penolakan PPTK: Mengembalikan kuitansi ke DRAFT dan memulihkan pagu kas via releaseBudget.
     * Menggunakan pessimistic lock dan menolak penolakan jika kuitansi sudah masuk bundel SPJ.
     */
    public function rejectByPptk(int|string $kuitansiId, string $alasan, ?User $actor = null): Kuitansi
    {
        return DB::transaction(function () use ($kuitansiId, $alasan, $actor) {
            $kuitansi = Kuitansi::where('id', $kuitansiId)->lockForUpdate()->firstOrFail();

            if ($kuitansi->status !== 'MENUNGGU_PPTK') {
                throw new InvalidArgumentException('Hanya kuitansi berstatus MENUNGGU_PPTK yang dapat ditolak oleh PPTK.');
            }

            if ($actor !== null && ! $actor->canAccessReceipt($kuitansi)) {
                throw new InvalidArgumentException('PPTK tidak memiliki hak akses untuk menolak kuitansi ini.');
            }

            if ($kuitansi->activeSpjItem()->exists()) {
                throw new InvalidArgumentException('Kuitansi yang telah masuk ke dalam bundel pengajuan SPJ aktif tidak dapat ditolak.');
            }

            $statusLama = $kuitansi->status;

            // Pulihkan pagu anggaran yang terkunci
            $this->budgetService->releaseBudget(
                (string) $kuitansi->anggaran_rekening_id,
                $kuitansi->bulan_anggaran,
                (float) $kuitansi->nominal_bruto
            );

            $kuitansi->update([
                'status' => 'DRAFT',
                'pptk_user_id' => $actor?->id,
                'catatan_pptk' => $alasan,
            ]);

            $this->auditTrailService->log(
                $kuitansi,
                'PPTK_REJECTED',
                $statusLama,
                'DRAFT',
                $alasan,
                $actor,
                [
                    'pagu_dipulihkan' => (float) $kuitansi->nominal_bruto,
                    'bulan_anggaran' => $kuitansi->bulan_anggaran,
                ]
            );

            return $kuitansi;
        });
    }

    /**
     * Persetujuan Lurah (KPA Kelurahan): Memindahkan kuitansi dari MENUNGGU_LURAH ke MENUNGGU_VERIFIKASI.
     * Menggunakan pessimistic lock dan menolak jika kuitansi tidak berstatus MENUNGGU_LURAH atau bukan di unit kelurahannya.
     */
    public function approveByLurah(int|string $kuitansiId, ?string $catatan = null, ?User $actor = null): Kuitansi
    {
        return DB::transaction(function () use ($kuitansiId, $catatan, $actor) {
            $kuitansi = Kuitansi::where('id', $kuitansiId)->lockForUpdate()->firstOrFail();

            if ($kuitansi->status !== 'MENUNGGU_LURAH') {
                throw new InvalidArgumentException('Hanya kuitansi berstatus MENUNGGU_LURAH yang dapat disetujui oleh Lurah.');
            }

            if ($actor !== null) {
                if ($actor->role !== 'LURAH') {
                    throw new InvalidArgumentException('Hanya peran LURAH yang dapat memberikan otorisasi Lurah.');
                }
                if ((int) $actor->unit_kerja_id !== (int) $kuitansi->unit_kerja_id) {
                    throw new InvalidArgumentException('Lurah hanya berwenang mengotorisasi kuitansi di kelurahannya sendiri.');
                }
            }

            if ($kuitansi->activeSpjItem()->exists()) {
                throw new InvalidArgumentException('Kuitansi yang telah masuk ke dalam bundel pengajuan SPJ aktif tidak dapat disetujui ulang.');
            }

            $statusLama = $kuitansi->status;

            $kuitansi->update([
                'status' => 'MENUNGGU_VERIFIKASI',
                'lurah_user_id' => $actor?->id,
                'tanggal_lurah_approve' => now(),
                'catatan_lurah' => $catatan,
            ]);

            $this->auditTrailService->log(
                $kuitansi,
                'LURAH_APPROVED',
                $statusLama,
                'MENUNGGU_VERIFIKASI',
                $catatan ?? 'Disetujui oleh Lurah (KPA Kelurahan)',
                $actor
            );

            return $kuitansi;
        });
    }

    /**
     * Penolakan Lurah (KPA Kelurahan): Mengembalikan kuitansi dari MENUNGGU_LURAH ke DRAFT dan memulihkan pagu kas via releaseBudget.
     */
    public function rejectByLurah(int|string $kuitansiId, string $alasan, ?User $actor = null): Kuitansi
    {
        return DB::transaction(function () use ($kuitansiId, $alasan, $actor) {
            $kuitansi = Kuitansi::where('id', $kuitansiId)->lockForUpdate()->firstOrFail();

            if ($kuitansi->status !== 'MENUNGGU_LURAH') {
                throw new InvalidArgumentException('Hanya kuitansi berstatus MENUNGGU_LURAH yang dapat ditolak oleh Lurah.');
            }

            if ($actor !== null) {
                if ($actor->role !== 'LURAH') {
                    throw new InvalidArgumentException('Hanya peran LURAH yang dapat menolak kuitansi pada tahap otorisasi Lurah.');
                }
                if ((int) $actor->unit_kerja_id !== (int) $kuitansi->unit_kerja_id) {
                    throw new InvalidArgumentException('Lurah hanya berwenang menolak kuitansi di kelurahannya sendiri.');
                }
            }

            if ($kuitansi->activeSpjItem()->exists()) {
                throw new InvalidArgumentException('Kuitansi yang telah masuk ke dalam bundel pengajuan SPJ aktif tidak dapat ditolak.');
            }

            $statusLama = $kuitansi->status;

            // Pulihkan pagu anggaran yang terkunci
            $this->budgetService->releaseBudget(
                (string) $kuitansi->anggaran_rekening_id,
                $kuitansi->bulan_anggaran,
                (float) $kuitansi->nominal_bruto
            );

            $kuitansi->update([
                'status' => 'DRAFT',
                'lurah_user_id' => $actor?->id,
                'catatan_lurah' => $alasan,
            ]);

            $this->auditTrailService->log(
                $kuitansi,
                'LURAH_REJECTED',
                $statusLama,
                'DRAFT',
                $alasan,
                $actor,
                [
                    'pagu_dipulihkan' => (float) $kuitansi->nominal_bruto,
                    'bulan_anggaran' => $kuitansi->bulan_anggaran,
                ]
            );

            return $kuitansi;
        });
    }
}
