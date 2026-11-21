<?php

namespace App\Services;

use App\Contracts\BudgetReservationServiceInterface;
use App\Contracts\VerificationServiceInterface;
use App\Models\Kuitansi;
use App\Models\KuitansiVerifikasiChecklist;
use App\Models\MasterChecklistItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class VerificationService implements VerificationServiceInterface
{
    protected AuditTrailService $auditTrailService;

    public function __construct(
        protected BudgetReservationServiceInterface $budgetService,
        ?AuditTrailService $auditTrailService = null
    ) {
        $this->auditTrailService = $auditTrailService ?? app(AuditTrailService::class);
    }

    /**
     * Mengambil daftar antrean kuitansi yang perlu diverifikasi berdasarkan peran login pengguna.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getQueue(string $userId, string $role, array $filters = []): array
    {
        $user = User::find($userId);

        $query = Kuitansi::with(['unitKerja', 'rekening', 'creator'])
            ->withCount('lampiran')
            ->orderByRaw("CASE WHEN status IN ('MENUNGGU_PPTK', 'MENUNGGU_LURAH', 'MENUNGGU_VERIFIKASI', 'REVISI_MINOR') THEN 0 ELSE 1 END ASC")
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } elseif ($role === 'PPTK') {
            // Khusus peran PPTK, default antrean adalah kuitansi yang menunggu persetujuannya
            $query->whereIn('status', [
                'MENUNGGU_PPTK',
                'MENUNGGU_LURAH',
                'MENUNGGU_VERIFIKASI',
                'REVISI_MINOR',
                'LOLOS_VERIFIKASI',
            ]);
        } else {
            // Default antrean verifikator/BP mengecualikan DRAFT, MENUNGGU_PPTK, dan MENUNGGU_LURAH
            $query->whereIn('status', [
                'MENUNGGU_VERIFIKASI',
                'REVISI_MINOR',
                'REVISI_MAYOR',
                'LOLOS_VERIFIKASI',
            ]);
        }

        // Penerapan isolasi wewenang unit & subkegiatan
        if ($user) {
            if ($user->isKelurahanScoped()) {
                if ($user->unit_kerja_id) {
                    $query->where('unit_kerja_id', $user->unit_kerja_id);
                }
            } elseif (! empty($filters['unit_id'])) {
                $query->where('unit_kerja_id', $filters['unit_id']);
            }

            if ($user->role === 'PPTK' || $role === 'PPTK') {
                if ($user->unit_kerja_id) {
                    $query->where('unit_kerja_id', $user->unit_kerja_id);
                }
                $assignedCodes = $user->assignedSubkegiatanCodes();
                if (! empty($assignedCodes)) {
                    $query->whereHas('rekening', function (Builder $q) use ($assignedCodes) {
                        $q->whereIn('kode_sub_kegiatan', $assignedCodes);
                    });
                }
            }
        } else {
            // Fallback jika user model tidak ditemukan
            if (! empty($filters['unit_id'])) {
                $query->where('unit_kerja_id', $filters['unit_id']);
            }
        }

        $limit = min(max((int) ($filters['limit'] ?? 200), 1), 500);
        $receipts = $query->limit($limit)->get();

        return $receipts->map(function (Kuitansi $k) {
            return [
                'id' => $k->nomor_kuitansi,
                'kuitansi_id' => $k->id,
                'unit' => $k->unitKerja?->nama_unit ?? 'Unit Kerja',
                'sub_activity' => $k->rekening?->nama_sub_kegiatan ?? '',
                'sub_kegiatan' => $k->rekening?->nama_sub_kegiatan ?? '',
                'rekening' => $k->rekening?->nama_rekening ?? '',
                'uraian' => $k->uraian,
                'vendor' => $k->nama_penerima ?? '-',
                'category' => $k->rekening?->kategori_belanja ?? 'B.1',
                'kategori' => $k->rekening?->kategori_belanja ?? 'B.1',
                'nominal' => (float) $k->nominal_bruto,
                'nominal_neto' => (float) $k->nominal_neto,
                'total_pajak' => (float) $k->total_pajak,
                'tanggal' => $k->tanggal_transaksi?->format('Y-m-d'),
                'status' => $k->status,
                'sla_deadline' => $k->tenggat_sla?->toIso8601String(),
                'is_sla_breached' => $k->is_sla_breached,
                'sla_remaining_hours' => $k->sla_remaining_hours,
                'qr_token' => $k->qr_token,
                'catatan' => $k->catatan_verifikasi,
                'attachments_count' => (int) $k->lampiran_count,
            ];
        })->toArray();
    }

    /**
     * Mengambil detail kuitansi untuk Split-Screen Verification Workspace:
     * - Metadata kuitansi & item barang
     * - Berkas lampiran terindeks
     * - Checklist dinamis per kategori (B.1 - B.6) beserta status pemeriksaan
     * - Timeline audit trail histori revisi
     *
     * @param  string  $kuitansiId  (Bisa berupa ID database atau nomor kuitansi)
     * @return array<string, mixed>
     */
    public function getDetailForWorkspace(string $kuitansiId): array
    {
        $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($kuitansiId, [
            'unitKerja',
            'rekening',
            'creator',
            'verifikator',
            'items',
            'pajak',
            'lampiran',
            'checklistVerifikasi.checklistItem',
        ]);

        $kategori = $kuitansi->rekening?->kategori_belanja ?? 'B.1';

        // Ambil template butir checklist resmi untuk kategori belanja kuitansi ini
        $masterChecklist = MasterChecklistItem::where('kategori_belanja', $kategori)
            ->where('is_active', true)
            ->orderBy('urutan')
            ->get();

        // Gabungkan hasil verifikasi yang sudah ada
        $verifMap = $kuitansi->checklistVerifikasi->keyBy('master_checklist_item_id');

        $checklistSummary = $masterChecklist->map(function (MasterChecklistItem $item) use ($verifMap) {
            $existing = $verifMap->get($item->id);

            return [
                'item_id' => $item->id,
                'kode_item' => $item->kode_item,
                'nama_item' => $item->nama_item,
                'deskripsi' => $item->deskripsi,
                'is_required' => $item->is_required,
                'is_verified' => $existing?->is_verified ?? false,
                'status_kesesuaian' => $existing?->status_kesesuaian ?? 'BELUM_DIPERIKSA',
                'catatan' => $existing?->catatan ?? null,
                'verified_at' => $existing?->verified_at?->toIso8601String(),
            ];
        })->toArray();

        return [
            'id' => $kuitansi->id,
            'nomor_kuitansi' => $kuitansi->nomor_kuitansi,
            'status' => $kuitansi->status,
            'tanggal_transaksi' => $kuitansi->tanggal_transaksi?->format('Y-m-d'),
            'uraian' => $kuitansi->uraian,
            'nama_penerima' => $kuitansi->nama_penerima,
            'npwp_penerima' => $kuitansi->npwp_penerima,
            'nominal_bruto' => (float) $kuitansi->nominal_bruto,
            'total_pajak' => (float) $kuitansi->total_pajak,
            'nominal_neto' => (float) $kuitansi->nominal_neto,
            'qr_token' => $kuitansi->qr_token,
            'catatan_verifikasi' => $kuitansi->catatan_verifikasi,
            'tenggat_sla' => $kuitansi->tenggat_sla?->toIso8601String(),
            'is_sla_breached' => $kuitansi->is_sla_breached,
            'sla_remaining_hours' => $kuitansi->sla_remaining_hours,
            'unit_kerja' => $kuitansi->unitKerja,
            'rekening' => $kuitansi->rekening,
            'items' => $kuitansi->items,
            'pajak' => $kuitansi->pajak,
            'lampiran' => $kuitansi->lampiran,
            'checklist' => $checklistSummary,
        ];
    }

    /**
     * Memproses keputusan verifikasi (Lolos, Revisi Minor, atau Revisi Mayor/Tolak).
     *
     * @param  string  $action  'VERIFIED' | 'REVISION_MINOR' | 'REVISION_MAJOR'
     * @param array{
     *     notes?: string,
     *     checklist_results?: array<int, array{item_id: int|string, status: string, notes?: string}>
     * } $payload
     * @return array{
     *     success: bool,
     *     kuitansi_id: string,
     *     new_status: string,
     *     qr_token: string|null,
     *     tenggat_sla: string|null,
     *     message: string
     * }
     */
    public function processAction(string $kuitansiId, string $action, array $payload, string $verifierUserId): array
    {
        $notes = $payload['notes'] ?? '';
        $checklistResults = $payload['checklist_results'] ?? [];

        return DB::transaction(function () use ($kuitansiId, $action, $notes, $checklistResults, $verifierUserId) {
            $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($kuitansiId, lockForUpdate: true);

            // Anti-bypass guard: Kuitansi harus disetujui PPTK (dan diotorisasi Lurah untuk Kelurahan) terlebih dahulu sebelum diverifikasi
            if (in_array($kuitansi->status, ['DRAFT', 'MENUNGGU_PPTK'], true)) {
                throw new InvalidArgumentException('Kuitansi harus disetujui oleh PPTK terlebih dahulu sebelum dapat diverifikasi.');
            }

            if ($kuitansi->status === 'MENUNGGU_LURAH') {
                throw new InvalidArgumentException('Kuitansi harus diotorisasi oleh Lurah terlebih dahulu sebelum dapat diverifikasi.');
            }

            if (! in_array($kuitansi->status, ['MENUNGGU_VERIFIKASI', 'REVISI_MINOR'], true)) {
                throw new InvalidArgumentException("Kuitansi berstatus '{$kuitansi->status}' tidak dapat diproses verifikasi.");
            }

            $statusLama = $kuitansi->status;
            $verifierUser = User::find($verifierUserId);

            // 1. Simpan/update setiap butir checklist verifikasi
            foreach ($checklistResults as $cr) {
                if (! isset($cr['item_id'])) {
                    continue;
                }

                $statusKesesuaian = $cr['status'] ?? 'SESUAI';
                $isVerified = ($statusKesesuaian === 'SESUAI');

                KuitansiVerifikasiChecklist::updateOrCreate(
                    [
                        'kuitansi_id' => $kuitansi->id,
                        'master_checklist_item_id' => $cr['item_id'],
                    ],
                    [
                        'is_verified' => $isVerified,
                        'status_kesesuaian' => $statusKesesuaian,
                        'catatan' => $cr['notes'] ?? null,
                        'verified_by' => $verifierUserId,
                        'verified_at' => now(),
                    ]
                );
            }

            $qrToken = $kuitansi->qr_token;
            $tenggatSla = null;

            switch (strtoupper($action)) {
                case 'VERIFIED':
                case 'LOLOS_VERIFIKASI':
                    if ($kuitansi->lampiran()->count() === 0) {
                        throw new InvalidArgumentException('Kuitansi tidak dapat diloloskan tanpa minimal 1 berkas lampiran bukti.');
                    }

                    $kategori = $kuitansi->rekening?->kategori_belanja ?? 'B.1';
                    $requiredItemIds = MasterChecklistItem::where('kategori_belanja', $kategori)
                        ->where('is_active', true)
                        ->where('is_required', true)
                        ->pluck('id');

                    if ($requiredItemIds->isNotEmpty()) {
                        $verifiedRequiredCount = KuitansiVerifikasiChecklist::where('kuitansi_id', $kuitansi->id)
                            ->whereIn('master_checklist_item_id', $requiredItemIds)
                            ->where('status_kesesuaian', 'SESUAI')
                            ->count();

                        if ($verifiedRequiredCount < $requiredItemIds->count()) {
                            throw new InvalidArgumentException('Seluruh butir checklist wajib harus berstatus SESUAI sebelum kuitansi dapat diloloskan.');
                        }
                    }

                    $newStatus = 'LOLOS_VERIFIKASI';
                    if (empty($qrToken)) {
                        $qrToken = Str::uuid()->toString();
                    }

                    $kuitansi->update([
                        'status' => $newStatus,
                        'qr_token' => $qrToken,
                        'catatan_verifikasi' => $notes ?: 'Dokumen diverifikasi akuntabel dan lengkap.',
                        'verifikator_id' => $verifierUserId,
                        'tanggal_verifikasi' => now(),
                    ]);

                    $this->auditTrailService->log(
                        $kuitansi,
                        'VERIFIED',
                        $statusLama,
                        $newStatus,
                        $notes ?: 'Dokumen diverifikasi akuntabel dan lengkap.',
                        $verifierUser,
                        ['qr_token' => $qrToken]
                    );

                    $message = 'Berkas kuitansi dinyatakan Lolos Verifikasi Akuntabel. Lembar Kendali diterbitkan.';
                    break;

                case 'REVISION_MINOR':
                case 'REVISI_MINOR':
                    $newStatus = 'REVISI_MINOR';
                    // SLA 2 hari kerja efektif (mengecualikan akhir pekan)
                    $tenggatSla = Carbon::now()->addWeekdays(2);

                    $kuitansi->update([
                        'status' => $newStatus,
                        'catatan_verifikasi' => $notes,
                        'tenggat_sla' => $tenggatSla,
                        'verifikator_id' => $verifierUserId,
                        'tanggal_verifikasi' => now(),
                    ]);

                    $this->auditTrailService->log(
                        $kuitansi,
                        'REVISION_MINOR',
                        $statusLama,
                        $newStatus,
                        $notes,
                        $verifierUser,
                        ['tenggat_sla' => $tenggatSla->toIso8601String()]
                    );

                    $message = sprintf(
                        'Catatan pengembalian berkas revisi minor berhasil dikirim ke pembuat SPJ (Tenggat SLA: %s).',
                        $tenggatSla->format('d/m/Y H:i')
                    );
                    break;

                case 'REVISION_MAJOR':
                case 'REVISI_MAYOR':
                case 'DITOLAK':
                    $newStatus = 'REVISI_MAYOR';

                    // CheckSlaBreachCommand hanya mencatat riwayat tanpa melepas pagu, jadi penolakan mayor wajib selalu melepas terkunci_bulan.
                    $this->budgetService->releaseBudget(
                        (string) $kuitansi->anggaran_rekening_id,
                        $kuitansi->bulan_anggaran,
                        (float) $kuitansi->nominal_bruto
                    );

                    $kuitansi->update([
                        'status' => $newStatus,
                        'catatan_verifikasi' => $notes,
                        'verifikator_id' => $verifierUserId,
                        'tanggal_verifikasi' => now(),
                    ]);

                    $this->auditTrailService->log(
                        $kuitansi,
                        'REVISION_MAJOR',
                        $statusLama,
                        $newStatus,
                        $notes,
                        $verifierUser,
                        [
                            'pagu_dipulihkan' => (float) $kuitansi->nominal_bruto,
                            'bulan_anggaran' => $kuitansi->bulan_anggaran,
                        ]
                    );

                    $message = 'Kuitansi dikembalikan untuk revisi mayor dan pagu anggaran telah dipulihkan.';
                    break;

                default:
                    throw new InvalidArgumentException("Aksi verifikasi '{$action}' tidak dikenali.");
            }

            return [
                'success' => true,
                'kuitansi_id' => (string) $kuitansi->id,
                'new_status' => $newStatus,
                'qr_token' => $qrToken,
                'tenggat_sla' => $tenggatSla?->toIso8601String(),
                'message' => $message,
            ];
        });
    }
}
