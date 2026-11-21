<?php

namespace App\Services;

use App\Contracts\BudgetReservationServiceInterface;
use App\Contracts\SettlementServiceInterface;
use App\Models\Kuitansi;
use App\Models\PengajuanSpj;
use App\Models\PengajuanSpjItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SettlementService implements SettlementServiceInterface
{
    /**
     * Template standar 9 Dokumen Wajib Bagian A untuk pengesahan SPP / LPJ.
     *
     * @var array<int, array{id: string, name: string, required: bool, checked: bool}>
     */
    protected array $defaultChecklistBagianA = [
        ['id' => 'A_01', 'name' => 'Nota Pencairan Dana (NPD)', 'required' => true, 'checked' => true],
        ['id' => 'A_02', 'name' => 'Lembar Pengiriman SPJ', 'required' => true, 'checked' => true],
        ['id' => 'A_03', 'name' => 'Berita Acara Pemeriksaan Kas (BAP Kas)', 'required' => true, 'checked' => true],
        ['id' => 'A_04', 'name' => 'Register Penutupan Kas', 'required' => true, 'checked' => true],
        ['id' => 'A_05', 'name' => 'Laporan Pengeluaran Kas', 'required' => true, 'checked' => true],
        ['id' => 'A_06', 'name' => 'SPJ Fungsional', 'required' => true, 'checked' => true],
        ['id' => 'A_07', 'name' => 'Buku Kas Umum (BKU)', 'required' => true, 'checked' => true],
        ['id' => 'A_08', 'name' => 'Buku Pembantu Pajak', 'required' => true, 'checked' => true],
        ['id' => 'A_09', 'name' => 'Rekening Koran Bank Penampung', 'required' => true, 'checked' => true],
    ];

    protected AuditTrailService $auditTrailService;

    public function __construct(
        protected BudgetReservationServiceInterface $budgetService,
        ?AuditTrailService $auditTrailService = null
    ) {
        $this->auditTrailService = $auditTrailService ?? app(AuditTrailService::class);
    }

    /**
     * Membuat bundel pengajuan SPJ baru dan menggabungkan kuitansi lolos verifikasi.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, int|string>  $kuitansiIds
     */
    public function createBundle(array $data, array $kuitansiIds, User $user): PengajuanSpj
    {
        if (empty($kuitansiIds)) {
            throw new InvalidArgumentException('Bundel pengajuan SPJ wajib menyertakan minimal 1 kuitansi.');
        }

        if ($user->isReadOnlyRole()) {
            throw new InvalidArgumentException('Peran monitoring read-only (Lurah/Camat) hanya memiliki akses baca terhadap bundel SPJ.');
        }

        $tahun = (int) ($data['tahun_anggaran'] ?? date('Y'));
        $bulan = (int) ($data['bulan_periode'] ?? date('n'));
        $jenis = (string) ($data['jenis_bundel'] ?? 'SPP_GU');

        if ($user->isKelurahanScoped()) {
            if ($jenis !== 'LPJ_BPP') {
                throw new InvalidArgumentException('Bendahara Pengeluaran Pembantu di Kelurahan hanya berwenang membuat bundel berjenis LPJ_BPP.');
            }
            $unitId = $user->unit_kerja_id;
        } else {
            $unitId = $data['unit_kerja_id'] ?? $user->unit_kerja_id;
        }

        if (! $unitId) {
            throw new InvalidArgumentException('Unit kerja pengaju SPJ wajib ditentukan.');
        }

        $bulanStr = str_pad((string) $bulan, 2, '0', STR_PAD_LEFT);
        $prefix = "BND-{$jenis}/{$tahun}/{$bulanStr}/";
        $isAutoNumber = blank($data['nomor_bundel'] ?? null);
        $maxAttempts = $isAutoNumber ? 3 : 1;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return DB::transaction(function () use (
                    $prefix,
                    $unitId,
                    $user,
                    $jenis,
                    $tahun,
                    $bulan,
                    $kuitansiIds,
                    $data,
                    $isAutoNumber
                ) {
                    $nomorBundel = $isAutoNumber ? $this->generateNextNomorBundel($prefix) : (string) $data['nomor_bundel'];
                    // 1. Validasi seluruh kuitansi: harus LOLOS_VERIFIKASI dan belum aktif di bundel lain
                    $numericIds = [];
                    $nomorList = [];
                    foreach ($kuitansiIds as $identifier) {
                        $str = (string) $identifier;
                        if (preg_match('/^[1-9][0-9]*$/', $str) === 1 && filter_var($str, FILTER_VALIDATE_INT) !== false) {
                            $numericIds[] = (int) $str;
                        } else {
                            $nomorList[] = $str;
                        }
                    }

                    $receipts = ($numericIds === [] && $nomorList === [])
                        ? collect()
                        : Kuitansi::where(function ($q) use ($numericIds, $nomorList) {
                            if ($numericIds !== []) {
                                $q->whereIn('id', $numericIds);
                            }
                            if ($nomorList !== []) {
                                $method = $numericIds !== [] ? 'orWhereIn' : 'whereIn';
                                $q->{$method}('nomor_kuitansi', $nomorList);
                            }
                        })
                            ->orderBy('id', 'asc')
                            ->lockForUpdate()
                            ->get();

                    if ($receipts->count() !== count(array_unique($kuitansiIds))) {
                        throw new InvalidArgumentException('Beberapa kuitansi yang dipilih tidak ditemukan dalam sistem.');
                    }

                    foreach ($receipts as $r) {
                        if ($r->status !== 'LOLOS_VERIFIKASI') {
                            throw new InvalidArgumentException(
                                "Kuitansi {$r->nomor_kuitansi} berstatus '{$r->status}', hanya kuitansi LOLOS_VERIFIKASI yang dapat dibundel."
                            );
                        }

                        if ($user->isKelurahanScoped() && (int) $r->unit_kerja_id !== (int) $user->unit_kerja_id) {
                            throw new InvalidArgumentException(
                                "Kuitansi {$r->nomor_kuitansi} berasal dari unit kerja lain. BPP Kelurahan hanya dapat membundel kuitansi kelurahannya sendiri."
                            );
                        }
                    }

                    // 2. Buat record Pengajuan SPJ
                    $bundle = PengajuanSpj::create([
                        'nomor_bundel' => $nomorBundel,
                        'unit_kerja_id' => $unitId,
                        'user_id' => $user->id,
                        'jenis_bundel' => $jenis,
                        'tahun_anggaran' => $tahun,
                        'bulan_periode' => $bulan,
                        'total_bruto' => 0.00,
                        'total_pajak' => 0.00,
                        'total_neto' => 0.00,
                        'status_bundel' => $data['status_bundel'] ?? 'DRAFT',
                        'nomor_spp_sipd' => $data['nomor_spp_sipd'] ?? null,
                        'checklist_bagian_a' => $data['checklist_bagian_a'] ?? $this->defaultChecklistBagianA,
                    ]);

                    // 3. Masukkan setiap kuitansi ke pengajuan_spj_item dengan no urut ordner
                    $order = 1;
                    foreach ($receipts as $receipt) {
                        try {
                            PengajuanSpjItem::create([
                                'pengajuan_spj_id' => $bundle->id,
                                'kuitansi_id' => $receipt->id,
                                'no_urut_ordner' => $order++,
                                'status_item' => 'AKTIF',
                            ]);
                        } catch (QueryException $e) {
                            if (str_contains($e->getMessage(), 'uq_satu_kuitansi_aktif') || in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                                throw new InvalidArgumentException(
                                    "Kuitansi {$receipt->nomor_kuitansi} gagal dibundel karena telah aktif pada pengajuan SPJ lain (Proteksi Anti-Klaim Ganda)."
                                );
                            }
                            throw $e;
                        }

                        // Update status kuitansi menjadi DIBUNDEL
                        $receipt->update(['status' => 'DIBUNDEL']);

                        $this->auditTrailService->log(
                            $receipt,
                            'BUNDLED',
                            'LOLOS_VERIFIKASI',
                            'DIBUNDEL',
                            "Kuitansi dimasukkan ke dalam bundel SPJ {$nomorBundel}",
                            $user,
                            ['pengajuan_spj_id' => $bundle->id, 'nomor_bundel' => $nomorBundel]
                        );
                    }

                    // 4. Hitung akumulasi total bruto, pajak, dan neto
                    $bundle->recalculateTotals();

                    return $bundle->fresh(['unitKerja', 'creator', 'activeItems.kuitansi.rekening']);
                });
            } catch (QueryException $e) {
                if (! $isAutoNumber || $attempt >= $maxAttempts || ! $this->isAutoNumberUniqueViolation($e, 'nomor_bundel')) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Gagal membuat nomor bundel unik setelah beberapa percobaan.');
    }

    private function isAutoNumberUniqueViolation(QueryException $e, string $column): bool
    {
        $isUniqueCode = in_array((string) $e->getCode(), ['23000', '23505'], true);

        return $isUniqueCode && str_contains($e->getMessage(), $column);
    }

    /**
     * Mengeluarkan kuitansi bermasalah dari bundel aktif (Unbundle).
     */
    public function unbundleReceipt(int|string $bundleId, int|string $kuitansiId, ?string $reason = null, ?User $actor = null): bool
    {
        if ($actor && $actor->isReadOnlyRole()) {
            throw new InvalidArgumentException('Peran monitoring read-only (Lurah/Camat) tidak berwenang mengeluarkan kuitansi dari bundel.');
        }

        return DB::transaction(function () use ($bundleId, $kuitansiId, $reason, $actor) {
            $bundle = PengajuanSpj::resolveOrFailByIdOrNumber($bundleId, lockForUpdate: true);

            if ($actor && $actor->isKelurahanScoped() && (int) $bundle->unit_kerja_id !== (int) $actor->unit_kerja_id) {
                throw new InvalidArgumentException('BPP Kelurahan tidak berwenang mengeluarkan kuitansi dari bundel unit lain.');
            }

            if ($bundle->status_bundel !== 'DRAFT') {
                throw new InvalidArgumentException("Kuitansi hanya dapat dikeluarkan saat bundel berstatus DRAFT. Status bundel {$bundle->nomor_bundel} saat ini: {$bundle->status_bundel}.");
            }

            $kuitansi = Kuitansi::resolveByIdOrNumber($kuitansiId, lockForUpdate: true);

            $item = $kuitansi
                ? PengajuanSpjItem::where('pengajuan_spj_id', $bundle->id)
                    ->where('kuitansi_id', $kuitansi->id)
                    ->where('status_item', 'AKTIF')
                    ->lockForUpdate()
                    ->first()
                : null;

            if (! $item) {
                throw new InvalidArgumentException('Kuitansi tidak ditemukan dalam keadaan aktif pada bundel SPJ ini.');
            }

            // 1. Ubah status item menjadi DIKELUARKAN (membebaskan active_kuitansi_id via stored column)
            $item->update(['status_item' => 'DIKELUARKAN']);

            // 2. Kembalikan status kuitansi ke LOLOS_VERIFIKASI agar bisa dibundel ulang nanti
            $kuitansi->update(['status' => 'LOLOS_VERIFIKASI']);

            $this->auditTrailService->log(
                $kuitansi,
                'UNBUNDLED',
                'DIBUNDEL',
                'LOLOS_VERIFIKASI',
                $reason ?? "Kuitansi dikeluarkan dari bundel SPJ {$bundle->nomor_bundel}",
                $actor,
                ['pengajuan_spj_id' => $bundle->id, 'nomor_bundel' => $bundle->nomor_bundel]
            );

            // 3. Hitung ulang total bundel
            $bundle->recalculateTotals();

            return true;
        });
    }

    /**
     * Mengajukan bundel SPJ berstatus DRAFT ke PPK (VERIFIKATOR) untuk pemeriksaan Bagian A.
     */
    public function submitForPpk(int|string $bundleId, ?User $actor = null): PengajuanSpj
    {
        return DB::transaction(function () use ($bundleId, $actor) {
            $bundle = PengajuanSpj::resolveOrFailByIdOrNumber($bundleId, lockForUpdate: true);

            if ($actor && $actor->isKelurahanScoped() && (int) $bundle->unit_kerja_id !== (int) $actor->unit_kerja_id) {
                throw new InvalidArgumentException('Anda tidak berwenang mengajukan bundel SPJ dari unit kerja lain.');
            }

            if ($bundle->status_bundel !== 'DRAFT') {
                throw new InvalidArgumentException("Bundel {$bundle->nomor_bundel} harus berstatus DRAFT untuk diajukan ke PPK. Status saat ini: {$bundle->status_bundel}.");
            }

            if ($bundle->activeItems()->count() === 0) {
                throw new InvalidArgumentException("Bundel {$bundle->nomor_bundel} tidak memiliki kuitansi aktif.");
            }

            $bundle->update([
                'status_bundel' => 'MENUNGGU_PPK',
            ]);

            return $bundle->fresh(['unitKerja', 'creator', 'activeItems.kuitansi']);
        });
    }

    /**
     * Persetujuan PPK (VERIFIKATOR) atas kelengkapan 9 dokumen Bagian A sebelum SP2D dapat dicatat.
     *
     * @param  array<int, array<string, mixed>>  $checklistBagianA
     */
    public function approveByPpk(int|string $bundleId, array $checklistBagianA, User $ppk): PengajuanSpj
    {
        if ($ppk->role !== 'VERIFIKATOR' && ! $ppk->hasRole('VERIFIKATOR')) {
            throw new InvalidArgumentException('Hanya Verifikator (PPK) yang berwenang menyetujui bundel SPJ.');
        }

        return DB::transaction(function () use ($bundleId, $checklistBagianA, $ppk) {
            $bundle = PengajuanSpj::resolveOrFailByIdOrNumber($bundleId, lockForUpdate: true);

            if ($bundle->status_bundel !== 'MENUNGGU_PPK') {
                throw new InvalidArgumentException("Bundel {$bundle->nomor_bundel} harus berstatus MENUNGGU_PPK sebelum disetujui PPK. Status saat ini: {$bundle->status_bundel}.");
            }

            $resolvedChecklist = ! empty($checklistBagianA)
                ? $checklistBagianA
                : ($bundle->checklist_bagian_a ?? $this->defaultChecklistBagianA);

            $checkedById = [];
            foreach ($resolvedChecklist as $doc) {
                if (isset($doc['id'])) {
                    $checkedById[$doc['id']] = (bool) ($doc['checked'] ?? false);
                }
            }

            foreach ($this->defaultChecklistBagianA as $requiredDoc) {
                if ($requiredDoc['required'] && empty($checkedById[$requiredDoc['id']])) {
                    throw new InvalidArgumentException("Dokumen wajib Bagian A '{$requiredDoc['name']}' belum terverifikasi oleh PPK.");
                }
            }

            $bundle->update([
                'status_bundel' => 'DISETUJUI_PPK',
                'ppk_skpd_id' => $ppk->id,
                'checklist_bagian_a' => $resolvedChecklist,
            ]);

            return $bundle->fresh(['unitKerja', 'creator', 'ppk', 'activeItems.kuitansi']);
        });
    }

    /**
     * Mencatat nomor SP2D SIPD-RI dan memindahkan seluruh kuncian pagu kuitansi menjadi realisasi definitif.
     */
    public function recordSipdSp2d(int|string $bundleId, string $nomorSp2d, ?string $nomorSpp = null, ?string $tanggalSp2d = null, ?User $actor = null): PengajuanSpj
    {
        if ($actor && ($actor->isReadOnlyRole() || $actor->isKelurahanScoped())) {
            throw new InvalidArgumentException('Hanya BP Kecamatan yang berwenang mencatat nomor SP2D SIPD-RI.');
        }

        return DB::transaction(function () use ($bundleId, $nomorSp2d, $nomorSpp, $tanggalSp2d, $actor) {
            $bundle = PengajuanSpj::resolveOrFailByIdOrNumber($bundleId, ['activeItems.kuitansi'], lockForUpdate: true);

            if ($bundle->status_bundel === 'SYNCED_SIPD') {
                throw new InvalidArgumentException("Bundel {$bundle->nomor_bundel} sudah berstatus SYNCED_SIPD dan tidak dapat dicatat ulang (Proteksi Anti-Double Settlement).");
            }

            if ($bundle->status_bundel !== 'DISETUJUI_PPK') {
                throw new InvalidArgumentException("Bundel {$bundle->nomor_bundel} harus berstatus DISETUJUI_PPK sebelum dapat dicatat SP2D. Status saat ini: {$bundle->status_bundel}.");
            }

            foreach ($bundle->activeItems as $item) {
                $kuitansi = $item->kuitansi;
                if ($kuitansi) {
                    // Eksekusi pemindahan kuncian pagu menjadi realisasi definitif di ledger & RAK
                    $this->budgetService->settleBudget(
                        (string) $kuitansi->anggaran_rekening_id,
                        $kuitansi->bulan_anggaran,
                        (float) $kuitansi->nominal_bruto
                    );

                    $kuitansi->update(['status' => 'SELESAI_SP2D']);

                    $this->auditTrailService->log(
                        $kuitansi,
                        'SP2D_SETTLED',
                        'DIBUNDEL',
                        'SELESAI_SP2D',
                        "Pencairan SP2D SIPD-RI terkonfirmasi: {$nomorSp2d}",
                        $actor,
                        [
                            'nomor_sp2d' => $nomorSp2d,
                            'nomor_spp' => $nomorSpp ?? $bundle->nomor_spp_sipd,
                            'pengajuan_spj_id' => $bundle->id,
                            'realisasi_pagu' => (float) $kuitansi->nominal_bruto,
                        ]
                    );
                }
            }

            $bundle->update([
                'status_bundel' => 'SYNCED_SIPD',
                'nomor_sp2d_sipd' => $nomorSp2d,
                'nomor_spp_sipd' => $nomorSpp ?? $bundle->nomor_spp_sipd,
                'tanggal_sp2d' => $tanggalSp2d ?? now()->toDateString(),
            ]);

            return $bundle->fresh(['unitKerja', 'creator', 'activeItems.kuitansi']);
        });
    }

    /**
     * Mengambil daftar bundel SPJ untuk dashboard/index view.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getBundles(array $filters = [], ?User $actor = null): array
    {
        $query = PengajuanSpj::with(['unitKerja', 'activeItems.kuitansi.rekening'])
            ->orderBy('created_at', 'desc');

        if ($actor && $actor->isKelurahanScoped()) {
            if ($actor->unit_kerja_id) {
                $query->where('unit_kerja_id', $actor->unit_kerja_id);
            }
        } elseif (! empty($filters['unit_id'])) {
            $query->where('unit_kerja_id', $filters['unit_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status_bundel', $filters['status']);
        }

        if (! empty($filters['tahun_anggaran'])) {
            $query->where('tahun_anggaran', (int) $filters['tahun_anggaran']);
        }

        $limit = min(max((int) ($filters['limit'] ?? 100), 1), 250);
        $bundles = $query->limit($limit)->get();

        return $bundles->map(function (PengajuanSpj $b) {
            return [
                'id' => $b->id,
                'nomor_bundel' => $b->nomor_bundel,
                'nomor_spp' => $b->nomor_spp_sipd ?? $b->nomor_bundel,
                'jenis' => $b->jenis_bundel,
                'tahun_anggaran' => (int) $b->tahun_anggaran,
                'bulan_periode' => (int) $b->bulan_periode,
                'periode' => $this->formatPeriode((int) $b->bulan_periode, (int) $b->tahun_anggaran),
                'unit' => $b->unitKerja?->nama_unit ?? 'Unit Kerja',
                'tanggal' => $b->created_at?->format('Y-m-d'),
                'total_nominal' => (float) $b->total_bruto,
                'total_pajak' => (float) $b->total_pajak,
                'total_neto' => (float) $b->total_neto,
                'status' => $b->status_bundel,
                'nomor_sipd' => $b->nomor_sp2d_sipd ?? $b->nomor_spp_sipd ?? '-',
                'checklist_bagian_a' => $b->checklist_bagian_a ?? $this->defaultChecklistBagianA,
                'kuitansi_list' => $b->activeItems->map(function (PengajuanSpjItem $item) {
                    $k = $item->kuitansi;

                    return [
                        'id' => $k?->nomor_kuitansi ?? (string) $item->kuitansi_id,
                        'kuitansi_id' => $item->kuitansi_id,
                        'uraian' => $k?->uraian ?? '',
                        'nominal' => (float) ($k?->nominal_bruto ?? 0),
                        'kategori' => $k?->rekening?->kategori_belanja ?? 'B.1',
                        'status' => $k?->status ?? 'VERIFIED',
                    ];
                })->values()->all(),
            ];
        })->toArray();
    }

    /**
     * Mengambil detail lengkap suatu bundel SPJ.
     *
     * @return array<string, mixed>
     */
    public function getBundleDetail(int|string $bundleId, ?User $actor = null): array
    {
        $bundle = PengajuanSpj::resolveOrFailByIdOrNumber($bundleId, [
            'unitKerja',
            'creator',
            'ppk',
            'activeItems.kuitansi.rekening',
            'activeItems.kuitansi.items',
            'activeItems.kuitansi.pajak',
            'activeItems.kuitansi.lampiran',
        ]);

        if ($actor && $actor->isKelurahanScoped()) {
            if ((int) $bundle->unit_kerja_id !== (int) $actor->unit_kerja_id) {
                throw new InvalidArgumentException('Anda tidak berwenang mengakses detail bundel SPJ dari unit kerja lain.');
            }
        }

        return [
            'id' => $bundle->id,
            'nomor_bundel' => $bundle->nomor_bundel,
            'nomor_spp' => $bundle->nomor_spp_sipd ?? $bundle->nomor_bundel,
            'jenis' => $bundle->jenis_bundel,
            'unit' => $bundle->unitKerja?->nama_unit ?? 'Unit Kerja',
            'unit_kerja' => $bundle->unitKerja,
            'tanggal' => $bundle->created_at?->format('Y-m-d'),
            'total_nominal' => (float) $bundle->total_bruto,
            'total_bruto' => (float) $bundle->total_bruto,
            'total_pajak' => (float) $bundle->total_pajak,
            'total_neto' => (float) $bundle->total_neto,
            'status' => $bundle->status_bundel,
            'nomor_sipd' => $bundle->nomor_sp2d_sipd ?? $bundle->nomor_spp_sipd ?? '-',
            'nomor_sp2d_sipd' => $bundle->nomor_sp2d_sipd,
            'tanggal_sp2d' => $bundle->tanggal_sp2d?->format('Y-m-d'),
            'checklist_bagian_a' => $bundle->checklist_bagian_a ?? $this->defaultChecklistBagianA,
            'items' => $bundle->activeItems,
        ];
    }

    /**
     * Menghasilkan nomor bundel berikutnya yang unik secara berurutan di dalam transaksi.
     */
    private function generateNextNomorBundel(string $prefix): string
    {
        $lastNomor = PengajuanSpj::query()
            ->where('nomor_bundel', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('nomor_bundel')
            ->value('nomor_bundel');

        $nextSeq = 1;
        if (is_string($lastNomor) && str_starts_with($lastNomor, $prefix)) {
            $suffix = substr($lastNomor, strlen($prefix));
            if (ctype_digit($suffix)) {
                $nextSeq = (int) $suffix + 1;
            }
        }

        do {
            $candidate = $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
            $exists = PengajuanSpj::query()->where('nomor_bundel', $candidate)->exists();
            $nextSeq++;
        } while ($exists);

        return $candidate;
    }

    private function formatPeriode(int $bulan, int $tahun): string
    {
        $namaBulan = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return ($namaBulan[$bulan] ?? "Bulan {$bulan}").' '.$tahun;
    }
}
