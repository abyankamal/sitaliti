<?php

namespace App\Contracts;

interface VerificationServiceInterface
{
    /**
     * Mengambil daftar antrean kuitansi yang perlu diverifikasi berdasarkan peran login pengguna.
     *
     * @param string $userId
     * @param string $role
     * @param array $filters
     * @return array
     */
    public function getQueue(string $userId, string $role, array $filters = []): array;

    /**
     * Mengambil detail kuitansi untuk Split-Screen Verification Workspace:
     * - Metadata kuitansi & item barang
     * - Berkas lampiran terindeks
     * - Checklist dinamis per kategori (B.1 - B.6) beserta status pemeriksaan
     * - Timeline audit trail histori revisi
     *
     * @param string $kuitansiId
     * @return array
     */
    public function getDetailForWorkspace(string $kuitansiId): array;

    /**
     * Memproses keputusan verifikasi (Lolos, Revisi Minor, atau Revisi Mayor/Tolak).
     *
     * @param string $kuitansiId
     * @param string $action 'VERIFIED' | 'REVISION_MINOR' | 'REVISION_MAJOR'
     * @param array{
     *     notes?: string,
     *     checklist_results?: array<int, array{item_id: string, status: string, notes?: string}>
     * } $payload
     * @param string $verifierUserId
     * @return array{
     *     success: bool,
     *     kuitansi_id: string,
     *     new_status: string,
     *     qr_token: string|null,
     *     tenggat_sla: string|null,
     *     message: string
     * }
     */
    public function processAction(string $kuitansiId, string $action, array $payload, string $verifierUserId): array;
}

