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

class BudgetApiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected BudgetReservationServiceInterface $budgetService
    ) {}

    /**
     * Endpoint ringkasan anggaran untuk Dashboard.vue (budgetService.getDashboardSummary)
     */
    public function summary(): JsonResponse
    {
        $totalPagu = (float) AnggaranRekening::sum('pagu_perubahan');
        $totalTerkunci = (float) AnggaranKasBulanan::sum('terkunci_bulan');
        $totalRealisasi = (float) AnggaranKasBulanan::sum('realisasi_bulan');
        $sisaPagu = max(0.0, $totalPagu - $totalTerkunci - $totalRealisasi);
        $persentaseRealisasi = $totalPagu > 0 ? round(($totalRealisasi / $totalPagu) * 100, 2) : 0;

        $pendingPptkCount = Kuitansi::where('status', 'MENUNGGU_PPTK')->count();
        $pendingVerificationCount = Kuitansi::where('status', 'MENUNGGU_VERIFIKASI')->count();
        $activeRevisionsCount = Kuitansi::whereIn('status', ['REVISI_MINOR', 'REVISI_MAYOR'])->count();
        $verifiedCount = Kuitansi::whereIn('status', ['LOLOS_VERIFIKASI', 'DIBUNDEL', 'SELESAI_SP2D'])->count();

        $queueCounts = [
            'menunggu_pptk' => $pendingPptkCount,
            'menunggu_verifikasi' => $pendingVerificationCount,
            'revisi_minor' => Kuitansi::where('status', 'REVISI_MINOR')->count(),
            'revisi_mayor' => Kuitansi::where('status', 'REVISI_MAYOR')->count(),
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

        $monthlySums = AnggaranKasBulanan::selectRaw('
            bulan,
            SUM(alokasi_pagu) as target,
            SUM(realisasi_bulan) as realisasi,
            SUM(terkunci_bulan) as terkunci
        ')
            ->groupBy('bulan')
            ->get()
            ->keyBy('bulan');

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

        // 2. Agregasi Realisasi 6 Unit Kerja
        $units = UnitKerja::where('is_active', true)->orderBy('id')->get()->map(function (UnitKerja $unit) {
            $rekeningIds = AnggaranRekening::where('unit_kerja_id', $unit->id)->pluck('id');
            $pagu = (float) AnggaranRekening::where('unit_kerja_id', $unit->id)->sum('pagu_perubahan');

            $terserap = 0.0;
            $terkunci = 0.0;
            if ($rekeningIds->isNotEmpty()) {
                $terserap = (float) AnggaranKasBulanan::whereIn('anggaran_rekening_id', $rekeningIds)->sum('realisasi_bulan');
                $terkunci = (float) AnggaranKasBulanan::whereIn('anggaran_rekening_id', $rekeningIds)->sum('terkunci_bulan');
            }

            $sisa = max(0.0, $pagu - $terserap - $terkunci);

            return [
                'id' => $unit->id,
                'code' => $unit->kode_unit,
                'name' => $unit->nama_unit,
                'pagu' => $pagu,
                'terserap' => $terserap,
                'terkunci' => $terkunci,
                'sisa' => $sisa,
            ];
        })->values()->all();

        // 3. Aktivitas Terkini dari KuitansiRiwayat
        $recentActivities = KuitansiRiwayat::with(['kuitansi.unitKerja', 'kuitansi.rekening', 'user'])
            ->latest('id')
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

        // 4. Meta Dataset
        $meta = [
            'fiscal_year' => (int) date('Y'),
            'district' => 'Kecamatan Tarogong Kidul',
            'district_code' => '32.05.01',
            'dataset_name' => 'RAK Perubahan '.date('Y'),
            'total_budget' => $totalPagu,
            'total_units' => UnitKerja::where('is_active', true)->count(),
            'total_sub_activities' => AnggaranRekening::distinct('kode_sub_kegiatan')->count('kode_sub_kegiatan'),
            'total_accounts' => AnggaranRekening::count(),
        ];

        $payload = array_merge($summaryData, [
            'summary' => $summaryData,
            'meta' => $meta,
            'units' => $units,
            'monthly_allocation' => $monthlyAllocation,
            'recent_activities' => $recentActivities,
        ]);

        return $this->successResponse($payload, 'Ringkasan RAK berhasil dimuat');
    }

    /**
     * Endpoint daftar 6 unit kerja (budgetService.getUnits)
     */
    public function units(): JsonResponse
    {
        $units = UnitKerja::where('is_active', true)->get()->map(function ($u) {
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
        $unitId = $request->input('unit_id');
        $query = AnggaranRekening::query();

        if ($unitId) {
            $query->where('unit_kerja_id', $unitId);
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
        $month = (int) ($request->input('month', date('n')));
        $data = $this->budgetService->getPopupAllocationData((string) $rekeningId, $month);

        return $this->successResponse($data, 'Data kuota RAK bulanan berhasil dimuat');
    }
}
