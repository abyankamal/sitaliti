<?php

namespace App\Services;

use App\Models\Kuitansi;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ReceiptQueryService
{
    /**
     * Mengambil kuitansi terpaginasi dengan scoping wewenang role dan filter pencarian.
     */
    public function getPaginatedReceipts(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max(1, $perPage), 100);

        $query = $this->buildFilteredQuery($user, $filters);

        return $query->with(['rekening.unitKerja', 'lampiran', 'pajak', 'pptkUser'])
            ->orderBy('tanggal_transaksi', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Menghitung ringkasan agregasi tab status berdasarkan konteks user dan filter aktif.
     *
     * @return array<string, int>
     */
    public function getStatusCounts(User $user, array $filters = []): array
    {
        // Hitung tab dengan mengecualikan filter 'status' agar counter badge menampilkan data utuh
        $filtersWithoutStatus = $filters;
        unset($filtersWithoutStatus['status']);

        $baseQuery = $this->buildFilteredQuery($user, $filtersWithoutStatus);

        return [
            'semua' => (clone $baseQuery)->count(),
            'draft' => (clone $baseQuery)->where('status', 'DRAFT')->count(),
            'menunggu_pptk' => (clone $baseQuery)->where('status', 'MENUNGGU_PPTK')->count(),
            'menunggu_verifikasi' => (clone $baseQuery)->where('status', 'MENUNGGU_VERIFIKASI')->count(),
            'revisi_minor' => (clone $baseQuery)->where('status', 'REVISI_MINOR')->count(),
            'siap_bundel' => (clone $baseQuery)->whereIn('status', ['LOLOS_VERIFIKASI', 'TERVERIFIKASI'])->count(),
        ];
    }

    /**
     * Membangun query kuitansi dengan penegakan scoping peran dan kriteria filter.
     */
    public function buildFilteredQuery(User $user, array $filters = []): Builder
    {
        $query = Kuitansi::query();

        // 1. Scoping Akses Berdasarkan Role (Permendagri 77/2020)
        $this->applyRoleScoping($query, $user);

        // 2. Filter Status
        if (! empty($filters['status'])) {
            $status = strtoupper($filters['status']);
            if (in_array($status, ['SIAP_BUNDEL', 'LOLOS_VERIFIKASI', 'TERVERIFIKASI'])) {
                $query->whereIn('status', ['LOLOS_VERIFIKASI', 'TERVERIFIKASI']);
            } else {
                $query->where('status', $status);
            }
        }

        // 3. Filter Unit Kerja
        if (! empty($filters['unit_kerja_id'])) {
            $query->where('unit_kerja_id', $filters['unit_kerja_id']);
        }

        // 4. Filter Bulan Anggaran RAK
        if (! empty($filters['bulan_anggaran'])) {
            $query->where('bulan_anggaran', (int) $filters['bulan_anggaran']);
        }

        // 5. Filter Kategori Belanja (B.1 s.d. B.6)
        if (! empty($filters['kategori_belanja'])) {
            $kategori = $filters['kategori_belanja'];
            $query->whereHas('rekening', function (Builder $q) use ($kategori) {
                $q->where('kategori_belanja', $kategori);
            });
        }

        // 6. Pencarian Teks (Nomor Kuitansi / Uraian / Nama Rekanan)
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('nomor_kuitansi', 'like', "%{$search}%")
                    ->orWhere('uraian', 'like', "%{$search}%")
                    ->orWhere('nama_penerima', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Menerapkan pembatasan data kuitansi sesuai wewenang role pengguna.
     */
    protected function applyRoleScoping(Builder $query, User $user): void
    {
        // 1. Verifikator, KPA Camat, dan BP Kecamatan memiliki visibilitas lintas unit SKPD
        if ($user->isSkpdWide()) {
            return;
        }

        // 2. Operator: hanya unit kerja sendiri dan hanya DRAFT miliknya sendiri
        if ($user->hasRole('OPERATOR') || $user->role === 'OPERATOR') {
            if ($user->unit_kerja_id) {
                $query->where('unit_kerja_id', $user->unit_kerja_id);
            }

            $query->where(function (Builder $q) use ($user) {
                $q->where('status', '!=', 'DRAFT')
                    ->orWhere('user_id', $user->id);
            });

            return;
        }

        // 3. PPTK: unit kerja sendiri + filter subkegiatan jika memiliki penugasan spesifik
        if ($user->hasRole('PPTK') || $user->role === 'PPTK') {
            if ($user->unit_kerja_id) {
                $query->where('unit_kerja_id', $user->unit_kerja_id);
            }

            $assignedCodes = $user->assignedSubkegiatanCodes();
            if (! empty($assignedCodes)) {
                $query->whereHas('rekening', function (Builder $q) use ($assignedCodes) {
                    $q->whereIn('kode_sub_kegiatan', $assignedCodes);
                });
            }

            return;
        }

        // 4. Lurah dan BPP Kelurahan: terisolasi pada unit kerjanya sendiri
        if ($user->unit_kerja_id) {
            $query->where('unit_kerja_id', $user->unit_kerja_id);
        }
    }
}
