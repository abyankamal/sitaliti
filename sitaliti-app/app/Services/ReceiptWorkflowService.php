<?php

namespace App\Services;

use App\Contracts\BudgetReservationServiceInterface;
use App\Models\Kuitansi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReceiptWorkflowService
{
    protected AuditTrailService $auditTrailService;

    protected SppdScheduleService $sppdService;

    public function __construct(
        protected BudgetReservationServiceInterface $budgetService,
        ?AuditTrailService $auditTrailService = null,
        ?SppdScheduleService $sppdService = null
    ) {
        $this->auditTrailService = $auditTrailService ?? app(AuditTrailService::class);
        $this->sppdService = $sppdService ?? app(SppdScheduleService::class);
    }

    /**
     * Mengirim ulang kuitansi REVISI_MINOR ke Verifikator (MENUNGGU_VERIFIKASI) tanpa memanggil reserveBudget() ulang.
     */
    public function resubmitMinorRevision(int|string $kuitansiId, ?User $actor = null): Kuitansi
    {
        return DB::transaction(function () use ($kuitansiId, $actor) {
            $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($kuitansiId, lockForUpdate: true);

            if ($kuitansi->status !== 'REVISI_MINOR') {
                throw new InvalidArgumentException('Hanya kuitansi berstatus REVISI_MINOR yang dapat dikirim ulang ke Verifikator.');
            }

            if ($kuitansi->lampiran()->count() === 0) {
                throw new InvalidArgumentException('Kuitansi wajib memiliki minimal 1 lampiran bukti sebelum dikirim ulang ke Verifikator.');
            }

            $this->sppdService->validateReadyForSubmission($kuitansi);

            $kuitansi->update([
                'status' => 'MENUNGGU_VERIFIKASI',
            ]);

            $this->auditTrailService->log(
                $kuitansi,
                'MINOR_REVISION_RESUBMITTED',
                'REVISI_MINOR',
                'MENUNGGU_VERIFIKASI',
                'Kuitansi dikirim ulang setelah perbaikan revisi minor',
                $actor,
                [
                    'nominal_bruto' => (float) $kuitansi->nominal_bruto,
                    'bulan_anggaran' => $kuitansi->bulan_anggaran,
                ]
            );

            return $kuitansi->load(['items', 'pajak', 'lampiran', 'rekening', 'unitKerja', 'sppdPeserta.pegawai']);
        });
    }

    /**
     * Mengajukan kuitansi ke PPTK: memanggil reserveBudget() secara atomic dan mengubah status.
     */
    public function submitToPptk(int|string $kuitansiId, ?User $actor = null): Kuitansi
    {
        return DB::transaction(function () use ($kuitansiId, $actor) {
            $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($kuitansiId, lockForUpdate: true);

            if (! in_array($kuitansi->status, ['DRAFT', 'REVISI_MAYOR'])) {
                throw new InvalidArgumentException('Hanya kuitansi berstatus DRAFT atau REVISI_MAYOR yang dapat diajukan ke PPTK.');
            }

            if ($kuitansi->lampiran()->count() === 0) {
                throw new InvalidArgumentException('Kuitansi wajib memiliki minimal 1 lampiran bukti sebelum diajukan ke PPTK.');
            }

            $this->sppdService->validateReadyForSubmission($kuitansi);

            $statusLama = $kuitansi->status;

            // Kunci pagu anggaran via BudgetReservationService
            $this->budgetService->reserveBudget(
                (string) $kuitansi->anggaran_rekening_id,
                $kuitansi->bulan_anggaran,
                (float) $kuitansi->nominal_bruto
            );

            if ($actor?->canAutoFinalizeReceipt()) {
                $qrToken = $kuitansi->qr_token ?: Str::uuid()->toString();

                $kuitansi->update([
                    'status' => 'LOLOS_VERIFIKASI',
                    'tanggal_submit' => now(),
                    'verifikator_id' => $actor->id,
                    'tanggal_verifikasi' => now(),
                    'qr_token' => $qrToken,
                    'tenggat_sla' => null,
                ]);

                $this->auditTrailService->log(
                    $kuitansi,
                    'AUTO_FINALIZED',
                    $statusLama,
                    'LOLOS_VERIFIKASI',
                    'Kuitansi diajukan dan langsung difinalisasi (LOLOS_VERIFIKASI)',
                    $actor,
                    [
                        'pagu_dikunci' => (float) $kuitansi->nominal_bruto,
                        'bulan_anggaran' => $kuitansi->bulan_anggaran,
                        'qr_token' => $qrToken,
                    ]
                );

                return $kuitansi;
            }

            $kuitansi->update([
                'status' => 'MENUNGGU_VERIFIKASI',
                'tanggal_submit' => now(),
            ]);

            $this->auditTrailService->log(
                $kuitansi,
                'SUBMITTED_TO_VERIFICATION',
                $statusLama,
                'MENUNGGU_VERIFIKASI',
                'Pengajuan kuitansi ke antrean verifikasi dan penguncian pagu kas',
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
            $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($kuitansiId, lockForUpdate: true);

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
            $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($kuitansiId, lockForUpdate: true);

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
            $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($kuitansiId, lockForUpdate: true);

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
            $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($kuitansiId, lockForUpdate: true);

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

    /**
     * Penarikan kuitansi kembali ke DRAFT oleh Operator pembuatnya (Self-Recall).
     * Menggunakan pessimistic lock, melepas pagu terkunci, membersihkan metadata persetujuan,
     * dan memblokir kuitansi yang sudah pernah masuk siklus REVISI_MINOR (SLA), punya QR, atau masuk bundel SPJ.
     */
    public function recallByOperator(int|string $kuitansiId, ?string $alasan = null, ?User $actor = null): Kuitansi
    {
        return DB::transaction(function () use ($kuitansiId, $alasan, $actor) {
            $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($kuitansiId, lockForUpdate: true);

            if ($actor !== null && (int) $kuitansi->user_id !== (int) $actor->id) {
                throw new InvalidArgumentException('Hanya Operator pembuat kuitansi yang dapat menarik kembali kuitansi ini ke DRAFT.');
            }

            if (! in_array($kuitansi->status, ['MENUNGGU_PPTK', 'MENUNGGU_LURAH', 'MENUNGGU_VERIFIKASI'], true)) {
                throw new InvalidArgumentException("Kuitansi berstatus '{$kuitansi->status}' tidak dapat ditarik kembali ke DRAFT.");
            }

            if ($kuitansi->status === 'MENUNGGU_VERIFIKASI' && $kuitansi->tenggat_sla !== null) {
                throw new InvalidArgumentException('Kuitansi yang pernah masuk siklus Revisi Minor (memiliki tenggat SLA) tidak dapat ditarik kembali ke DRAFT.');
            }

            if ($kuitansi->activeSpjItem()->exists() || ! empty($kuitansi->qr_token)) {
                throw new InvalidArgumentException('Kuitansi yang telah terverifikasi atau masuk bundel SPJ tidak dapat ditarik kembali.');
            }

            $statusLama = $kuitansi->status;

            $this->budgetService->releaseBudget(
                (string) $kuitansi->anggaran_rekening_id,
                $kuitansi->bulan_anggaran,
                (float) $kuitansi->nominal_bruto
            );

            $kuitansi->checklistVerifikasi()->delete();

            $kuitansi->update([
                'status' => 'DRAFT',
                'tanggal_submit' => null,
                'pptk_user_id' => null,
                'tanggal_pptk_approve' => null,
                'catatan_pptk' => null,
                'lurah_user_id' => null,
                'tanggal_lurah_approve' => null,
                'catatan_lurah' => null,
            ]);

            $this->auditTrailService->log(
                $kuitansi,
                'RECALLED_BY_OPERATOR',
                $statusLama,
                'DRAFT',
                $alasan ?: 'Kuitansi ditarik kembali ke DRAFT oleh Operator',
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
