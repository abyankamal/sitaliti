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
    public function __construct(
        protected TaxCalculationServiceInterface $taxService,
        protected BudgetReservationServiceInterface $budgetService
    ) {}

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

            return $kuitansi->load(['items', 'pajak', 'lampiran', 'rekening', 'unitKerja']);
        });
    }

    /**
     * Mengajukan kuitansi ke PPTK: memanggil reserveBudget() secara atomic dan mengubah status.
     */
    public function submitToPptk(int|string $kuitansiId): Kuitansi
    {
        $kuitansi = Kuitansi::findOrFail($kuitansiId);

        if (! in_array($kuitansi->status, ['DRAFT', 'REVISI_MAYOR'])) {
            throw new InvalidArgumentException('Hanya kuitansi berstatus DRAFT atau REVISI_MAYOR yang dapat diajukan ke PPTK.');
        }

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

        return $kuitansi;
    }
}
