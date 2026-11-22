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
            ->paginate($perPage)
            ->withQueryString();
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

        $countsByStatus = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'semua' => (int) $countsByStatus->sum(),
            'draft' => (int) ($countsByStatus['DRAFT'] ?? 0),
            'menunggu_pptk' => (int) ($countsByStatus['MENUNGGU_PPTK'] ?? 0),
            'menunggu_lurah' => (int) ($countsByStatus['MENUNGGU_LURAH'] ?? 0),
            'menunggu_verifikasi' => (int) ($countsByStatus['MENUNGGU_VERIFIKASI'] ?? 0),
            'revisi_minor' => (int) ($countsByStatus['REVISI_MINOR'] ?? 0),
            'siap_bundel' => (int) ($countsByStatus['LOLOS_VERIFIKASI'] ?? 0) + (int) ($countsByStatus['TERVERIFIKASI'] ?? 0),
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

        // 5. Filter Relasi Rekening (Kategori Belanja, Sub Kegiatan, Kode Rekening) dalam satu whereHas
        $hasRekeningFilters = ! empty($filters['kategori_belanja'])
            || ! empty($filters['kode_sub_kegiatan'])
            || ! empty($filters['kode_rekening']);

        if ($hasRekeningFilters) {
            $query->whereHas('rekening', function (Builder $q) use ($filters) {
                if (! empty($filters['kategori_belanja'])) {
                    $q->where('kategori_belanja', $filters['kategori_belanja']);
                }
                if (! empty($filters['kode_sub_kegiatan'])) {
                    $q->where('kode_sub_kegiatan', $filters['kode_sub_kegiatan']);
                }
                if (! empty($filters['kode_rekening'])) {
                    $q->where('kode_rekening', $filters['kode_rekening']);
                }
            });
        }

        // 6. Filter Rentang Waktu (tanggal_transaksi langsung agar index kolom DATE terpakai)
        if (! empty($filters['tanggal_dari'])) {
            $query->where('tanggal_transaksi', '>=', $filters['tanggal_dari']);
        }
        if (! empty($filters['tanggal_sampai'])) {
            $query->where('tanggal_transaksi', '<=', $filters['tanggal_sampai']);
        }

        // 6. Pencarian Teks (Nomor Kuitansi / Uraian / Nama Rekanan)
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $likeOperator = $q->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $q->where('nomor_kuitansi', $likeOperator, "%{$search}%")
                    ->orWhere('uraian', $likeOperator, "%{$search}%")
                    ->orWhere('nama_penerima', $likeOperator, "%{$search}%");
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

        // Fail-closed: user non-SKPD-wide tanpa unit kerja tidak boleh melihat kuitansi mana pun
        if ($user->unit_kerja_id === null) {
            $query->whereRaw('1 = 0');

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
