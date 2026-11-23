<?php

namespace App\Services;

use App\Models\AnggaranRekening;
use App\Models\KuitansiSppdPeserta;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SppdReportService
{
    /**
     * Membangun query laporan rekap SPPD berdasarkan filter dan scoping RBAC pengguna.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildFilteredQuery(User $user, array $filters = []): Builder
    {
        $query = KuitansiSppdPeserta::query()
            ->with([
                'pegawai.unitKerja:id,kode_unit,nama_unit',
                'kuitansi.rekening:id,unit_kerja_id,kode_sub_kegiatan,nama_sub_kegiatan,kode_rekening,nama_rekening,kategori_belanja',
                'kuitansi.unitKerja:id,kode_unit,nama_unit',
            ]);

        $this->applyRoleScoping($query, $user);

        // Filter Rentang Tanggal (semantik overlap terhadap tanggal berangkat - pulang)
        if (! empty($filters['tanggal_dari'])) {
            $query->whereDate('tanggal_pulang', '>=', $filters['tanggal_dari']);
        }
        if (! empty($filters['tanggal_sampai'])) {
            $query->whereDate('tanggal_berangkat', '<=', $filters['tanggal_sampai']);
        }

        // Filter Pegawai
        if (! empty($filters['pegawai_id'])) {
            $query->where('pegawai_id', (int) $filters['pegawai_id']);
        }

        // Filter Unit Kerja (hanya berlaku untuk user SKPD-wide)
        if ($user->isSkpdWide() && ! empty($filters['unit_kerja_id'])) {
            $unitId = (int) $filters['unit_kerja_id'];
            $query->whereHas('kuitansi', function (Builder $q) use ($unitId) {
                $q->where('unit_kerja_id', $unitId);
            });
        }

        // Filter Sub Kegiatan
        if (! empty($filters['kode_sub_kegiatan'])) {
            $kodeSub = (string) $filters['kode_sub_kegiatan'];
            $query->whereHas('kuitansi.rekening', function (Builder $q) use ($kodeSub) {
                $q->where('kode_sub_kegiatan', $kodeSub);
            });
        }

        // Filter Status Kuitansi
        if (! empty($filters['status'])) {
            $status = (string) $filters['status'];
            $query->whereHas('kuitansi', function (Builder $q) use ($status) {
                $q->where('status', $status);
            });
        }

        // Pencarian Teks (Nama Pegawai, NIP, Nomor Kuitansi, Tujuan)
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $normalizedNip = str_replace([' ', '-'], '', $search);
            $query->where(function (Builder $q) use ($search, $normalizedNip) {
                $likeOperator = $q->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $q->where('tujuan', $likeOperator, "%{$search}%")
                    ->orWhereHas('pegawai', function (Builder $pq) use ($likeOperator, $search, $normalizedNip) {
                        $pq->where('nama', $likeOperator, "%{$search}%")
                            ->orWhere('nip', $likeOperator, "%{$normalizedNip}%");
                    })
                    ->orWhereHas('kuitansi', function (Builder $kq) use ($likeOperator, $search) {
                        $kq->where('nomor_kuitansi', $likeOperator, "%{$search}%");
                    });
            });
        }

        return $query;
    }

    /**
     * Mengambil data rekap laporan SPPD terpaginasi.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedReport(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $paginator = $this->buildFilteredQuery($user, $filters)
            ->orderByDesc('tanggal_berangkat')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->getCollection()->transform(function (KuitansiSppdPeserta $item) {
            $durasi = ($item->tanggal_berangkat && $item->tanggal_pulang)
                ? (int) $item->tanggal_berangkat->diffInDays($item->tanggal_pulang) + 1
                : 1;

            return [
                'id' => $item->id,
                'kuitansi_id' => $item->kuitansi_id,
                'nomor_kuitansi' => $item->kuitansi?->nomor_kuitansi,
                'status_kuitansi' => $item->kuitansi?->status,
                'pegawai_id' => $item->pegawai_id,
                'nip' => $item->pegawai?->nip,
                'nama_pegawai' => $item->pegawai?->nama,
                'jabatan' => $item->pegawai?->jabatan,
                'golongan' => $item->pegawai?->golongan,
                'unit_kerja' => $item->kuitansi?->unitKerja?->nama_unit ?? '-',
                'kode_sub_kegiatan' => $item->kuitansi?->rekening?->kode_sub_kegiatan ?? '-',
                'nama_sub_kegiatan' => $item->kuitansi?->rekening?->nama_sub_kegiatan ?? '-',
                'tanggal_berangkat' => $item->tanggal_berangkat?->format('Y-m-d'),
                'tanggal_pulang' => $item->tanggal_pulang?->format('Y-m-d'),
                'durasi_hari' => $durasi,
                'tujuan' => $item->tujuan,
                'foto_url' => $item->foto_url,
                'effective_foto_url' => $item->foto_url ?? $item->kuitansi?->sppd_foto_url,
            ];
        });

        return $paginator;
    }

    /**
     * Menghitung ringkasan KPI laporan SPPD secara portabel lintas driver database.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function getSummaryKpi(User $user, array $filters = []): array
    {
        $baseQuery = $this->buildFilteredQuery($user, $filters);

        $rows = (clone $baseQuery)
            ->with('kuitansi:id,sppd_foto_path')
            ->get(['id', 'kuitansi_id', 'pegawai_id', 'tanggal_berangkat', 'tanggal_pulang', 'foto_path']);

        $totalPerjalanan = $rows->count();
        $uniquePegawai = $rows->pluck('pegawai_id')->unique()->count();
        $totalHariOrang = 0;
        $belumAdaFoto = 0;

        foreach ($rows as $row) {
            $days = ($row->tanggal_berangkat && $row->tanggal_pulang)
                ? (int) $row->tanggal_berangkat->diffInDays($row->tanggal_pulang) + 1
                : 1;
            $totalHariOrang += $days;

            $hasPhoto = filled($row->foto_path) || filled($row->kuitansi?->sppd_foto_path);
            if (! $hasPhoto) {
                $belumAdaFoto++;
            }
        }

        return [
            'total_perjalanan' => $totalPerjalanan,
            'total_pegawai_unik' => $uniquePegawai,
            'total_hari_orang' => $totalHariOrang,
            'belum_ada_foto' => $belumAdaFoto,
        ];
    }

    /**
     * Mengambil daftar Sub Kegiatan SPPD untuk opsi filter sesuai scoping role.
     *
     * @return array<int, array{unit_kerja_id: int, kode_sub_kegiatan: string, nama_sub_kegiatan: string}>
     */
    public function getSubKegiatanOptions(User $user): array
    {
        $query = AnggaranRekening::query()
            ->select('unit_kerja_id', 'kode_sub_kegiatan', DB::raw('MIN(nama_sub_kegiatan) as nama_sub_kegiatan'))
            ->where(function (Builder $q) {
                $q->whereIn('kategori_belanja', ['B.4', 'B4', 'B4_PERJADIN'])
                    ->orWhere('kode_rekening', 'like', '5.1.02.04%');
            });

        if (! $user->isSkpdWide()) {
            if ($user->unit_kerja_id === null) {
                return [];
            }
            $query->where('unit_kerja_id', $user->unit_kerja_id);

            if ($user->hasRole('PPTK') || $user->role === 'PPTK') {
                $assignedCodes = $user->assignedSubkegiatanCodes();
                if (! empty($assignedCodes)) {
                    $query->whereIn('kode_sub_kegiatan', $assignedCodes);
                }
            }
        }

        return $query
            ->groupBy('unit_kerja_id', 'kode_sub_kegiatan')
            ->orderBy('kode_sub_kegiatan')
            ->get()
            ->map(fn ($row) => [
                'unit_kerja_id' => (int) $row->unit_kerja_id,
                'kode_sub_kegiatan' => (string) $row->kode_sub_kegiatan,
                'nama_sub_kegiatan' => (string) $row->nama_sub_kegiatan,
            ])
            ->values()
            ->all();
    }

    /**
     * Menerapkan scoping akses pada kuitansi.unit_kerja_id agar selaras dengan canAccessReceipt().
     */
    protected function applyRoleScoping(Builder $query, User $user): void
    {
        if ($user->isSkpdWide()) {
            return;
        }

        if ($user->unit_kerja_id === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $unitId = (int) $user->unit_kerja_id;

        if ($user->hasRole('PPTK') || $user->role === 'PPTK') {
            $assignedCodes = $user->assignedSubkegiatanCodes();
            $query->whereHas('kuitansi', function (Builder $q) use ($unitId, $assignedCodes) {
                $q->where('unit_kerja_id', $unitId);
                if (! empty($assignedCodes)) {
                    $q->whereHas('rekening', function (Builder $rq) use ($assignedCodes) {
                        $rq->whereIn('kode_sub_kegiatan', $assignedCodes);
                    });
                }
            });

            return;
        }

        $query->whereHas('kuitansi', function (Builder $q) use ($unitId) {
            $q->where('unit_kerja_id', $unitId);
        });
    }
}
