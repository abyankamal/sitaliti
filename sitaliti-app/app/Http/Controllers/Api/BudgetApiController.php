<?php

namespace App\Http\Controllers\Api;

use App\Contracts\BudgetReservationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiRiwayat;
use App\Models\UnitKerja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetApiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected BudgetReservationServiceInterface $budgetService
    ) {}

    /**
     * Endpoint ringkasan anggaran untuk Dashboard.vue (budgetService.getDashboardSummary)
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $isKelurahan = $user && $user->isKelurahanScoped();
        $targetUnitId = $isKelurahan ? $user->unit_kerja_id : null;

        // 0. Base Queries scoped by Unit jika Kelurahan
        $rekeningQuery = AnggaranRekening::query();
        if ($isKelurahan) {
            $rekeningQuery->where('unit_kerja_id', $targetUnitId);
        }
        $rekeningIds = $rekeningQuery->pluck('id');

        $totalPagu = (float) (clone $rekeningQuery)->sum('pagu_perubahan');

        $kasQuery = AnggaranKasBulanan::query();
        if ($isKelurahan) {
            $kasQuery->whereIn('anggaran_rekening_id', $rekeningIds);
        }
        $kasTotals = (clone $kasQuery)
            ->selectRaw('COALESCE(SUM(terkunci_bulan), 0) as total_terkunci, COALESCE(SUM(realisasi_bulan), 0) as total_realisasi')
            ->first();
        $totalTerkunci = (float) ($kasTotals?->total_terkunci ?? 0);
        $totalRealisasi = (float) ($kasTotals?->total_realisasi ?? 0);
        $sisaPagu = max(0.0, $totalPagu - $totalTerkunci - $totalRealisasi);
        $persentaseRealisasi = $totalPagu > 0 ? round(($totalRealisasi / $totalPagu) * 100, 2) : 0;

        $kuitansiQuery = Kuitansi::query();
        if ($isKelurahan) {
            $kuitansiQuery->where('unit_kerja_id', $targetUnitId);
        }

        $statusCounts = (clone $kuitansiQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $pendingPptkCount = (int) ($statusCounts['MENUNGGU_PPTK'] ?? 0);
        $pendingLurahCount = (int) ($statusCounts['MENUNGGU_LURAH'] ?? 0);
        $pendingVerificationCount = (int) ($statusCounts['MENUNGGU_VERIFIKASI'] ?? 0);
        $revisiMinorCount = (int) ($statusCounts['REVISI_MINOR'] ?? 0);
        $revisiMayorCount = (int) ($statusCounts['REVISI_MAYOR'] ?? 0);
        $activeRevisionsCount = $revisiMinorCount + $revisiMayorCount;
        $verifiedCount = (int) ($statusCounts['LOLOS_VERIFIKASI'] ?? 0)
            + (int) ($statusCounts['DIBUNDEL'] ?? 0)
            + (int) ($statusCounts['SELESAI_SP2D'] ?? 0);

        $queueCounts = [
            'menunggu_pptk' => $pendingPptkCount,
            'menunggu_lurah' => $pendingLurahCount,
            'menunggu_verifikasi' => $pendingVerificationCount,
            'revisi_minor' => $revisiMinorCount,
            'revisi_mayor' => $revisiMayorCount,
            'lolos_verifikasi' => $verifiedCount,
        ];

        $summaryData = [
            'total_pagu' => $totalPagu,
            'total_terserap' => $totalRealisasi,
            'total_realisasi' => $totalRealisasi,
            'total_terkunci' => $totalTerkunci,
            'total_sisa' => $sisaPagu,
            'sisa_pagu' => $sisaPagu,
            'persentase_realisasi' => $persentaseRealisasi,
            'pending_pptk_count' => $pendingPptkCount,
            'pending_lurah_count' => $pendingLurahCount,
            'pending_verification_count' => $pendingVerificationCount,
            'active_revisions_count' => $activeRevisionsCount,
            'verified_count' => $verifiedCount,
            'queue_counts' => $queueCounts,
        ];

        // 1. Alokasi 12 Bulan RAK
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $monthlySumsQuery = AnggaranKasBulanan::selectRaw('
            bulan,
            SUM(alokasi_pagu) as target,
            SUM(realisasi_bulan) as realisasi,
            SUM(terkunci_bulan) as terkunci
        ')->groupBy('bulan');

        if ($isKelurahan) {
            $monthlySumsQuery->whereIn('anggaran_rekening_id', $rekeningIds);
        }

        $monthlySums = $monthlySumsQuery->get()->keyBy('bulan');

        $currentMonth = (int) date('n');

        $monthlyAllocation = [];
        for ($m = 1; $m <= 12; $m++) {
            $row = $monthlySums->get($m);
            $target = (float) ($row?->target ?? 0);
            $realisasi = (float) ($row?->realisasi ?? 0);
            $terkunci = (float) ($row?->terkunci ?? 0);
            $sisa = max(0.0, $target - $realisasi - $terkunci);

            if ($m < $currentMonth) {
                $status = 'closed';
            } elseif ($m === $currentMonth) {
                $status = 'active';
            } else {
                $status = 'upcoming';
            }

            $monthlyAllocation[] = [
                'month' => $m,
                'name' => $monthNames[$m],
                'target' => $target,
                'realisasi' => $realisasi,
                'terkunci' => $terkunci,
                'sisa' => $sisa,
                'status' => $status,
            ];
        }

        // Subquery agregasi kas bulanan per rekening agar pagu_perubahan tidak terkalikan 12 bulan
        $kasPerRekeningSub = DB::table('anggaran_kas_bulanan')
            ->selectRaw('anggaran_rekening_id, SUM(realisasi_bulan) as terserap, SUM(terkunci_bulan) as terkunci')
            ->groupBy('anggaran_rekening_id');

        // 2. Agregasi Realisasi Unit Kerja (1 query agregasi SQL)
        $unitsQuery = UnitKerja::query()
            ->where('master_unit_kerja.is_active', true)
            ->leftJoin('anggaran_rekening', 'anggaran_rekening.unit_kerja_id', '=', 'master_unit_kerja.id')
            ->leftJoinSub($kasPerRekeningSub, 'kas_agg', function ($join) {
                $join->on('kas_agg.anggaran_rekening_id', '=', 'anggaran_rekening.id');
            })
            ->selectRaw('
                master_unit_kerja.id,
                master_unit_kerja.kode_unit,
                master_unit_kerja.nama_unit,
                COALESCE(SUM(anggaran_rekening.pagu_perubahan), 0) as pagu,
                COALESCE(SUM(kas_agg.terserap), 0) as terserap,
                COALESCE(SUM(kas_agg.terkunci), 0) as terkunci
            ')
            ->groupBy('master_unit_kerja.id', 'master_unit_kerja.kode_unit', 'master_unit_kerja.nama_unit')
            ->orderBy('master_unit_kerja.id');

        if ($isKelurahan) {
            $unitsQuery->where('master_unit_kerja.id', $targetUnitId);
        }

        $units = $unitsQuery->get()->map(function ($row) {
            $pagu = (float) $row->pagu;
            $terserap = (float) $row->terserap;
            $terkunci = (float) $row->terkunci;

            return [
                'id' => $row->id,
                'code' => $row->kode_unit,
                'name' => $row->nama_unit,
                'pagu' => $pagu,
                'terserap' => $terserap,
                'terkunci' => $terkunci,
                'sisa' => max(0.0, $pagu - $terserap - $terkunci),
            ];
        })->values()->all();

        // 3. Breakdown Subkegiatan (1 query agregasi SQL)
        $subKegiatanQuery = DB::table('anggaran_rekening')
            ->leftJoinSub($kasPerRekeningSub, 'kas_agg', function ($join) {
                $join->on('kas_agg.anggaran_rekening_id', '=', 'anggaran_rekening.id');
            })
            ->selectRaw('
                anggaran_rekening.kode_sub_kegiatan as code,
                MIN(anggaran_rekening.nama_sub_kegiatan) as name,
                COALESCE(SUM(anggaran_rekening.pagu_perubahan), 0) as pagu,
                COALESCE(SUM(kas_agg.terserap), 0) as terserap,
                COALESCE(SUM(kas_agg.terkunci), 0) as terkunci,
                COUNT(anggaran_rekening.id) as rekening_count
            ')
            ->groupBy('anggaran_rekening.kode_sub_kegiatan')
            ->orderBy('anggaran_rekening.kode_sub_kegiatan');

        if ($isKelurahan) {
            $subKegiatanQuery->where('anggaran_rekening.unit_kerja_id', $targetUnitId);
        }

        $subActivities = $subKegiatanQuery->get()->map(function ($row) {
            $pagu = (float) $row->pagu;
            $terserap = (float) $row->terserap;
            $terkunci = (float) $row->terkunci;

            return [
                'code' => (string) $row->code,
                'name' => $row->name,
                'pagu' => $pagu,
                'terserap' => $terserap,
                'terkunci' => $terkunci,
                'sisa' => max(0.0, $pagu - $terserap - $terkunci),
                'rekening_count' => (int) $row->rekening_count,
            ];
        })->values()->all();

        // 4. Aktivitas Terkini dari KuitansiRiwayat
        $recentQuery = KuitansiRiwayat::with(['kuitansi.unitKerja', 'kuitansi.rekening', 'user'])
            ->latest('id');

        if ($isKelurahan) {
            $recentQuery->whereHas('kuitansi', function ($q) use ($targetUnitId) {
                $q->where('unit_kerja_id', $targetUnitId);
            });
        }

        $recentActivities = $recentQuery
            ->take(10)
            ->get()
            ->map(function (KuitansiRiwayat $riwayat) {
                $kuitansi = $riwayat->kuitansi;

                return [
                    'id' => $kuitansi?->nomor_kuitansi ?? "LOG-{$riwayat->id}",
                    'kuitansi_id' => $kuitansi?->id,
                    'action' => $riwayat->action,
                    'unit' => $kuitansi?->unitKerja?->nama_unit ?? 'Unit Kerja',
                    'kegiatan' => $kuitansi?->rekening?->nama_sub_kegiatan ?? $riwayat->action,
                    'uraian' => $riwayat->catatan ?: ($kuitansi?->uraian ?? 'Aktivitas Verifikasi'),
                    'kategori' => $kuitansi?->rekening?->kategori_belanja ?? 'B.1',
                    'nominal' => (float) ($kuitansi?->nominal_bruto ?? 0),
                    'tanggal' => $riwayat->created_at?->format('Y-m-d') ?? date('Y-m-d'),
                    'timestamp' => $riwayat->created_at?->format('d/m/Y H:i'),
                    'status' => $riwayat->status_baru ?: ($kuitansi?->status ?? 'DRAFT'),
                    'actor_name' => $riwayat->user?->name ?? 'Sistem / Arsip Pegawai',
                    'actor_role' => $riwayat->user?->role ?? null,
                    'verifikator' => $riwayat->user?->name ?? 'Sistem / Verifikator',
                ];
            })->values()->all();

        // 5. Meta Dataset
        $unitCount = $isKelurahan ? 1 : UnitKerja::where('is_active', true)->count();
        $subActivityCount = count($subActivities);
        $rekeningCount = $rekeningIds->count();

        $meta = [
            'fiscal_year' => (int) date('Y'),
            'district' => 'Kecamatan Tarogong Kidul',
            'district_code' => '32.05.01',
            'dataset_name' => 'RAK Perubahan '.date('Y'),
            'total_budget' => $totalPagu,
            'total_units' => $unitCount,
            'unit_count' => $unitCount,
            'total_sub_activities' => $subActivityCount,
            'sub_activity_count' => $subActivityCount,
            'total_accounts' => $rekeningCount,
            'rekening_count' => $rekeningCount,
        ];

        $payload = array_merge($summaryData, [
            'summary' => $summaryData,
            'meta' => $meta,
            'units' => $units,
            'sub_activities' => $subActivities,
            'monthly_allocation' => $monthlyAllocation,
            'recent_activities' => $recentActivities,
        ]);

        return $this->successResponse($payload, 'Ringkasan RAK berhasil dimuat');
    }

    /**
     * Endpoint daftar unit kerja (budgetService.getUnits)
     */
    public function units(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = UnitKerja::where('is_active', true);

        if ($user && $user->isKelurahanScoped()) {
            $query->where('id', $user->unit_kerja_id);
        }

        $units = $query->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'code' => $u->kode_unit,
                'name' => $u->nama_unit,
                'type' => $u->tipe,
            ];
        });

        return $this->successResponse($units, 'Daftar unit kerja berhasil dimuat');
    }

    /**
     * Endpoint daftar subkegiatan dan rekening belanja berdasarkan unit_id (budgetService.getSubActivitiesByUnit)
     */
    public function subActivities(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AnggaranRekening::query();

        if ($user && ! $user->isSkpdWide()) {
            if ($user->unit_kerja_id) {
                $query->where('unit_kerja_id', $user->unit_kerja_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($request->filled('unit_id')) {
            $query->where('unit_kerja_id', $request->input('unit_id'));
        }

        if ($user && ($user->hasRole('PPTK') || $user->role === 'PPTK')) {
            $assignedCodes = $user->pptkSubkegiatanAssignments()->pluck('kode_sub_kegiatan');
            if ($assignedCodes->isNotEmpty()) {
                $query->whereIn('kode_sub_kegiatan', $assignedCodes);
            }
        }

        $records = $query->orderBy('kode_sub_kegiatan')
            ->orderBy('kode_rekening')
            ->get();

        // Group by subkegiatan agar sesuai struktur RAKSelectorModal (subkegiatan memiliki anak array rekening)
        $grouped = $records->groupBy('kode_sub_kegiatan')->map(function ($items) {
            $first = $items->first();

            return [
                'id' => $first->kode_sub_kegiatan, // ID subkegiatan untuk selector
                'unit_id' => $first->unit_kerja_id,
                'code' => $first->kode_sub_kegiatan,
                'name' => $first->nama_sub_kegiatan,
                'category' => $first->kategori_belanja ?? 'B.1',
                'rekening' => $items->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'code' => $r->kode_rekening,
                        'name' => $r->nama_rekening,
                        'kategori' => $r->kategori_belanja ?? 'B.1',
                        'pagu_murni' => (float) $r->pagu_murni,
                        'pagu_perubahan' => (float) $r->pagu_perubahan,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return $this->successResponse($grouped, 'Daftar sub kegiatan dan rekening berhasil dimuat');
    }

    /**
     * Endpoint alokasi RAK bulanan dan akumulasi carry-over (budgetService.getRakContext)
     */
    public function rakContext(Request $request, int|string $rekeningId): JsonResponse
    {
        $user = $request->user();
        $rekening = AnggaranRekening::find($rekeningId);
        if (! $rekening) {
            return $this->errorResponse('REKENING_NOT_FOUND', 'Rekening anggaran tidak ditemukan.', null, 404);
        }

        if ($user && ! $user->isSkpdWide() && (! $user->unit_kerja_id || (int) $rekening->unit_kerja_id !== (int) $user->unit_kerja_id)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki hak akses untuk melihat RAK rekening unit kerja lain.', null, 403);
        }

        if ($user && ($user->hasRole('PPTK') || $user->role === 'PPTK')) {
            if (! $user->canAccessPptkSubkegiatan((int) $rekening->unit_kerja_id, (string) $rekening->kode_sub_kegiatan)) {
                return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki akses ke subkegiatan rekening ini.', null, 403);
            }
        }

        $month = (int) ($request->input('month', date('n')));
        $data = $this->budgetService->getPopupAllocationData((string) $rekeningId, $month);

        return $this->successResponse($data, 'Data kuota RAK bulanan berhasil dimuat');
    }
}
