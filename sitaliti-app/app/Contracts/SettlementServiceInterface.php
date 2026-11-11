<?php

namespace App\Contracts;

use App\Models\PengajuanSpj;
use App\Models\User;

interface SettlementServiceInterface
{
    /**
     * Membuat bundel pengajuan SPJ baru (SPP-GU/TU/LS atau LPJ BPP) dan menggabungkan kuitansi lolos verifikasi.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, int|string>  $kuitansiIds
     */
    public function createBundle(array $data, array $kuitansiIds, User $user): PengajuanSpj;

    /**
     * Mengeluarkan kuitansi bermasalah dari bundel aktif (Unbundle).
     */
    public function unbundleReceipt(int|string $bundleId, int|string $kuitansiId, ?string $reason = null, ?User $actor = null): bool;

    /**
     * Mencatat nomor SP2D SIPD-RI dan memindahkan seluruh kuncian pagu kuitansi menjadi realisasi definitif.
     */
    public function recordSipdSp2d(int|string $bundleId, string $nomorSp2d, ?string $nomorSpp = null, ?string $tanggalSp2d = null, ?User $actor = null): PengajuanSpj;

    /**
     * Mengambil daftar bundel SPJ untuk dashboard/index view.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getBundles(array $filters = [], ?User $actor = null): array;

    /**
     * Mengambil detail lengkap suatu bundel SPJ.
     *
     * @return array<string, mixed>
     */
    public function getBundleDetail(int|string $bundleId, ?User $actor = null): array;
}
