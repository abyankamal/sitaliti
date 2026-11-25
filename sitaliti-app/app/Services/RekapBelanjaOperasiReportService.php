<?php

namespace App\Services;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\MasterRekapBelanjaOperasi;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RekapBelanjaOperasiReportService
{
    /**
     * Menghasilkan ringkasan KPI, daftar baris rekap per pos, dan opsi filter.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getReport(User $user, array $filters = []): array
    {
        [$bulanDari, $bulanSampai] = $this->resolveMonthRange($filters);

        $rekeningQuery = $this->buildScopedRekeningQuery($user, $filters);

        $rekeningRows = $rekeningQuery
            ->leftJoin('rekap_belanja_operasi_rekening as rbor', 'anggaran_rekening.kode_rekening', '=', 'rbor.kode_rekening')
            ->leftJoin('master_rekap_belanja_operasi as mrbo', 'rbor.master_rekap_id', '=', 'mrbo.id')
            ->select([
                'anggaran_rekening.id as anggaran_rekening_id',
                'anggaran_rekening.kode_rekening',
                'anggaran_rekening.pagu_perubahan',
                'rbor.master_rekap_id',
                'mrbo.kode as pos_kode',
                'mrbo.nama as pos_nama',
                'mrbo.urutan as pos_urutan',
            ])
            ->get();

        $rekeningIds = $rekeningRows->pluck('anggaran_rekening_id')->all();

        $rakByRekening = empty($rekeningIds)
            ? collect()
            : AnggaranKasBulanan::query()
                ->whereIn('anggaran_rekening_id', $rekeningIds)
                ->whereBetween('bulan', [$bulanDari, $bulanSampai])
                ->selectRaw('anggaran_rekening_id, COALESCE(SUM(alokasi_pagu), 0) as target_rak')
                ->groupBy('anggaran_rekening_id')
                ->pluck('target_rak', 'anggaran_rekening_id');

        $realisasiByRekening = empty($rekeningIds)
            ? collect()
            : Kuitansi::query()
                ->whereIn('anggaran_rekening_id', $rekeningIds)
                ->where('status', 'SELESAI_SP2D')
                ->whereBetween('bulan_anggaran', [$bulanDari, $bulanSampai])
                ->selectRaw('anggaran_rekening_id, COALESCE(SUM(nominal_bruto), 0) as total_realisasi')
                ->groupBy('anggaran_rekening_id')
                ->pluck('total_realisasi', 'anggaran_rekening_id');

        $grouped = [];
        foreach ($rekeningRows as $row) {
            $posKey = $row->master_rekap_id !== null ? (string) $row->master_rekap_id : 'unmapped';

            if (! isset($grouped[$posKey])) {
                $grouped[$posKey] = [
                    'pos_key' => $posKey,
                    'master_rekap_id' => $row->master_rekap_id !== null ? (int) $row->master_rekap_id : null,
                    'kode' => $row->pos_kode ?? 'UNMAPPED',
                    'nama' => $row->pos_nama ?? 'Belum Dipetakan',
                    'urutan' => $row->pos_urutan !== null ? (int) $row->pos_urutan : 999999,
                    'kode_rekening_set' => [],
                    'pagu_tahunan' => 0.0,
                    'target_rak_periode' => 0.0,
                    'realisasi_sp2d' => 0.0,
                ];
            }

            $rekId = (int) $row->anggaran_rekening_id;
            $grouped[$posKey]['kode_rekening_set'][$row->kode_rekening] = true;
            $grouped[$posKey]['pagu_tahunan'] += (float) $row->pagu_perubahan;
            $grouped[$posKey]['target_rak_periode'] += (float) ($rakByRekening[$rekId] ?? 0);
            $grouped[$posKey]['realisasi_sp2d'] += (float) ($realisasiByRekening[$rekId] ?? 0);
        }

        $rows = collect($grouped)
            ->sortBy([['urutan', 'asc'], ['nama', 'asc']])
            ->map(function (array $item): array {
                $pagu = round($item['pagu_tahunan'], 2);
                $rak = round($item['target_rak_periode'], 2);
                $realisasi = round($item['realisasi_sp2d'], 2);
                $sisa = round(max(0.0, $pagu - $realisasi), 2);
                $persen = $pagu > 0 ? round(($realisasi / $pagu) * 100, 2) : 0.0;

                return [
                    'pos_key' => $item['pos_key'],
                    'master_rekap_id' => $item['master_rekap_id'],
                    'kode' => $item['kode'],
                    'nama' => $item['nama'],
                    'jumlah_rekening' => count($item['kode_rekening_set']),
                    'pagu_tahunan' => $pagu,
                    'target_rak_periode' => $rak,
                    'realisasi_sp2d' => $realisasi,
                    'sisa_anggaran' => $sisa,
                    'persentase_realisasi' => $persen,
                ];
            })
            ->values()
            ->all();

        $totalPagu = round(array_sum(array_column($rows, 'pagu_tahunan')), 2);
        $totalRak = round(array_sum(array_column($rows, 'target_rak_periode')), 2);
        $totalRealisasi = round(array_sum(array_column($rows, 'realisasi_sp2d')), 2);
        $totalSisa = round(max(0.0, $totalPagu - $totalRealisasi), 2);
        $persenTotal = $totalPagu > 0 ? round(($totalRealisasi / $totalPagu) * 100, 2) : 0.0;

        return [
            'summary' => [
                'total_pagu_tahunan' => $totalPagu,
                'total_target_rak_periode' => $totalRak,
                'total_realisasi_sp2d' => $totalRealisasi,
                'total_sisa_anggaran' => $totalSisa,
                'persentase_realisasi' => $persenTotal,
                'bulan_dari' => $bulanDari,
                'bulan_sampai' => $bulanSampai,
            ],
            'rows' => $rows,
            'filters' => [
                'pos_options' => MasterRekapBelanjaOperasi::query()
                    ->where('is_active', true)
                    ->orderBy('urutan')
                    ->orderBy('nama')
                    ->get(['id', 'kode', 'nama']),
                'unit_options' => $user->isSkpdWide()
                    ? UnitKerja::query()->orderBy('nama_unit')->get(['id', 'kode_unit', 'nama_unit'])
                    : [],
            ],
        ];
    }

    /**
     * Mengambil rincian rekening & kuitansi SP2D untuk satu pos rekap (drill-down).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDrilldown(User $user, array $filters, string $posKey): array
    {
        [$bulanDari, $bulanSampai] = $this->resolveMonthRange($filters);

        $filters['master_rekap_id'] = $posKey;
        $rekeningQuery = $this->buildScopedRekeningQuery($user, $filters)
            ->leftJoin('rekap_belanja_operasi_rekening as rbor', 'anggaran_rekening.kode_rekening', '=', 'rbor.kode_rekening')
            ->leftJoin('master_rekap_belanja_operasi as mrbo', 'rbor.master_rekap_id', '=', 'mrbo.id')
            ->with('unitKerja:id,kode_unit,nama_unit')
            ->select('anggaran_rekening.*')
            ->orderBy('anggaran_rekening.kode_rekening')
            ->orderBy('anggaran_rekening.kode_sub_kegiatan');

        $rekenings = $rekeningQuery->get();
        $rekeningIds = $rekenings->pluck('id')->all();

        $rakByRekening = empty($rekeningIds)
            ? collect()
            : AnggaranKasBulanan::query()
                ->whereIn('anggaran_rekening_id', $rekeningIds)
                ->whereBetween('bulan', [$bulanDari, $bulanSampai])
                ->selectRaw('anggaran_rekening_id, COALESCE(SUM(alokasi_pagu), 0) as target_rak')
                ->groupBy('anggaran_rekening_id')
                ->pluck('target_rak', 'anggaran_rekening_id');

        $realisasiByRekening = empty($rekeningIds)
            ? collect()
            : Kuitansi::query()
                ->whereIn('anggaran_rekening_id', $rekeningIds)
                ->where('status', 'SELESAI_SP2D')
                ->whereBetween('bulan_anggaran', [$bulanDari, $bulanSampai])
                ->selectRaw('anggaran_rekening_id, COALESCE(SUM(nominal_bruto), 0) as total_realisasi')
                ->groupBy('anggaran_rekening_id')
                ->pluck('total_realisasi', 'anggaran_rekening_id');

        $rekeningDetails = $rekenings->map(function (AnggaranRekening $r) use ($rakByRekening, $realisasiByRekening): array {
            $pagu = (float) $r->pagu_perubahan;
            $realisasi = (float) ($realisasiByRekening[$r->id] ?? 0);

            return [
                'id' => $r->id,
                'kode_rekening' => $r->kode_rekening,
                'nama_rekening' => $r->nama_rekening,
                'kode_sub_kegiatan' => $r->kode_sub_kegiatan,
                'nama_sub_kegiatan' => $r->nama_sub_kegiatan,
                'unit_kerja' => $r->unitKerja?->nama_unit ?? '-',
                'pagu_tahunan' => round($pagu, 2),
                'target_rak_periode' => round((float) ($rakByRekening[$r->id] ?? 0), 2),
                'realisasi_sp2d' => round($realisasi, 2),
                'sisa_anggaran' => round(max(0.0, $pagu - $realisasi), 2),
            ];
        })->values()->all();

        $kuitansiDetails = empty($rekeningIds)
            ? []
            : Kuitansi::query()
                ->with(['rekening:id,kode_rekening,nama_rekening', 'unitKerja:id,nama_unit'])
                ->whereIn('anggaran_rekening_id', $rekeningIds)
                ->where('status', 'SELESAI_SP2D')
                ->whereBetween('bulan_anggaran', [$bulanDari, $bulanSampai])
                ->orderByDesc('tanggal_transaksi')
                ->limit(200)
                ->get()
                ->map(fn (Kuitansi $k): array => [
                    'id' => $k->id,
                    'nomor_kuitansi' => $k->nomor_kuitansi,
                    'tanggal_transaksi' => $k->tanggal_transaksi?->format('Y-m-d'),
                    'bulan_anggaran' => $k->bulan_anggaran,
                    'uraian' => $k->uraian,
                    'kode_rekening' => $k->rekening?->kode_rekening ?? '-',
                    'unit_kerja' => $k->unitKerja?->nama_unit ?? '-',
                    'nominal_bruto' => round((float) $k->nominal_bruto, 2),
                ])->values()->all();

        return [
            'rekenings' => $rekeningDetails,
            'kuitansi' => $kuitansiDetails,
        ];
    }

    /**
     * Membangun base query AnggaranRekening 5.1.% leaf dengan scoping RBAC ketat.
     *
     * @param  array<string, mixed>  $filters
     */
    private function buildScopedRekeningQuery(User $user, array $filters): Builder
    {
        $query = AnggaranRekening::query()
            ->where('anggaran_rekening.is_leaf', true)
            ->where('anggaran_rekening.kode_rekening', 'like', '5.1.%');

        if ($user->isKelurahanScoped()) {
            if ($user->unit_kerja_id === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('anggaran_rekening.unit_kerja_id', $user->unit_kerja_id);
            }
        } elseif ($user->role === 'PPTK') {
            if ($user->unit_kerja_id === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('anggaran_rekening.unit_kerja_id', $user->unit_kerja_id);
            }

            $codes = $user->assignedSubkegiatanCodes();
            if (! empty($codes)) {
                $query->whereIn('anggaran_rekening.kode_sub_kegiatan', $codes);
            }
        } elseif (! empty($filters['unit_kerja_id'])) {
            $query->where('anggaran_rekening.unit_kerja_id', (int) $filters['unit_kerja_id']);
        }

        if (isset($filters['master_rekap_id']) && $filters['master_rekap_id'] !== '') {
            if ($filters['master_rekap_id'] === 'unmapped') {
                $query->whereNull('rbor.master_rekap_id');
            } else {
                $query->where('rbor.master_rekap_id', (int) $filters['master_rekap_id']);
            }
        }

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $like = $this->likeOperator();
            $query->where(function (Builder $q) use ($term, $like): void {
                $q->where('anggaran_rekening.kode_rekening', $like, $term)
                    ->orWhere('anggaran_rekening.nama_rekening', $like, $term)
                    ->orWhere(DB::raw('COALESCE(mrbo.kode, \'\')'), $like, $term)
                    ->orWhere(DB::raw('COALESCE(mrbo.nama, \'\')'), $like, $term);
            });
        }

        return $query;
    }

    private function likeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: int, 1: int}
     */
    private function resolveMonthRange(array $filters): array
    {
        $dari = isset($filters['bulan_dari']) && $filters['bulan_dari'] !== ''
            ? max(1, min(12, (int) $filters['bulan_dari']))
            : 1;
        $sampai = isset($filters['bulan_sampai']) && $filters['bulan_sampai'] !== ''
            ? max(1, min(12, (int) $filters['bulan_sampai']))
            : 12;

        if ($dari > $sampai) {
            return [$sampai, $dari];
        }

        return [$dari, $sampai];
    }
}
