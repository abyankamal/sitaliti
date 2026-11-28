<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\UnitKerja;
use App\Services\ReceiptQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptWebController extends Controller
{
    public function __construct(
        protected ReceiptQueryService $receiptQueryService
    ) {}

    /**
     * Halaman Utama Daftar & Manajemen Kuitansi (Inertia: Receipts/Index).
     */
    public function index(Request $request): Response
    {
        $dateRules = [
            'tanggal_dari' => ['nullable', 'date'],
            'tanggal_sampai' => ['nullable', 'date'],
        ];
        if ($request->filled('tanggal_dari')) {
            $dateRules['tanggal_sampai'][] = 'after_or_equal:tanggal_dari';
        }
        $request->validate($dateRules);

        $user = $request->user();
        $filters = $request->only([
            'status',
            'unit_kerja_id',
            'bulan_anggaran',
            'kategori_belanja',
            'kode_sub_kegiatan',
            'kode_rekening',
            'tanggal_dari',
            'tanggal_sampai',
            'search',
        ]);
        $perPage = (int) $request->input('per_page', 15);

        if ($user && ! $user->isSkpdWide()) {
            $filters['unit_kerja_id'] = $user->unit_kerja_id;
        }

        $receipts = $this->receiptQueryService->getPaginatedReceipts($user, $filters, $perPage);
        $tabCounts = $this->receiptQueryService->getStatusCounts($user, $filters);

        $unitKerjaQuery = UnitKerja::where('is_active', true)
            ->select('id', 'kode_unit', 'nama_unit', 'tipe')
            ->orderBy('id', 'asc');

        $rekeningBaseQuery = AnggaranRekening::query();

        if ($user && ! $user->isSkpdWide()) {
            if ($user->unit_kerja_id) {
                $unitKerjaQuery->where('id', $user->unit_kerja_id);
                $user->loadMissing('unitKerja');
                if ($user->unitKerja?->tipe === 'KELURAHAN') {
                    $kecamatanIds = UnitKerja::where('tipe', 'KECAMATAN')->pluck('id');
                    $rekeningBaseQuery->where(function ($q) use ($user, $kecamatanIds) {
                        $q->where('unit_kerja_id', $user->unit_kerja_id);
                        if ($kecamatanIds->isNotEmpty()) {
                            $q->orWhere(function ($kq) use ($kecamatanIds) {
                                $kq->whereIn('unit_kerja_id', $kecamatanIds)
                                    ->where(function ($sq) {
                                        $sq->whereIn('kategori_belanja', ['B.4', 'B4', 'B4_PERJADIN'])
                                            ->orWhere('kode_rekening', 'like', '5.1.02.04%');
                                    });
                            });
                        }
                    });
                } else {
                    $rekeningBaseQuery->where('unit_kerja_id', $user->unit_kerja_id);
                }
            } else {
                $unitKerjaQuery->whereRaw('1 = 0');
                $rekeningBaseQuery->whereRaw('1 = 0');
            }

            if ($user->hasRole('PPTK') || $user->role === 'PPTK') {
                $assignedCodes = $user->assignedSubkegiatanCodes();
                if (! empty($assignedCodes)) {
                    $rekeningBaseQuery->whereIn('kode_sub_kegiatan', $assignedCodes);
                }
            }
        }

        $unitKerjaList = $unitKerjaQuery->get();

        $subKegiatanList = (clone $rekeningBaseQuery)
            ->selectRaw('unit_kerja_id, kode_sub_kegiatan, MIN(nama_sub_kegiatan) as nama_sub_kegiatan')
            ->groupBy('unit_kerja_id', 'kode_sub_kegiatan')
            ->orderBy('kode_sub_kegiatan')
            ->get();

        $rekeningList = (clone $rekeningBaseQuery)
            ->selectRaw('unit_kerja_id, kode_sub_kegiatan, kode_rekening, MIN(nama_rekening) as nama_rekening')
            ->groupBy('unit_kerja_id', 'kode_sub_kegiatan', 'kode_rekening')
            ->orderBy('kode_rekening')
            ->get();

        return Inertia::render('Receipts/Index', [
            'receipts' => $receipts,
            'tabCounts' => $tabCounts,
            'filters' => $filters,
            'unitKerjaList' => $unitKerjaList,
            'subKegiatanList' => $subKegiatanList,
            'rekeningList' => $rekeningList,
        ]);
    }

    /**
     * Halaman Edit / Revisi Kuitansi (Inertia: Receipts/Edit).
     */
    public function edit(Request $request, string|int $id): Response
    {
        $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($id);
        if (! $request->user()->canMutateReceipt($kuitansi)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengedit kuitansi ini.');
        }

        if (! in_array($kuitansi->status, ['DRAFT', 'REVISI_MINOR', 'REVISI_MAYOR'], true)) {
            abort(403, 'Kuitansi hanya dapat diedit pada status DRAFT, REVISI_MINOR, atau REVISI_MAYOR.');
        }

        return Inertia::render('Receipts/Edit', ['id' => $kuitansi->id]);
    }

    /**
     * Halaman Ruang Verifikasi Kuitansi (Inertia: Verification/Workspace).
     */
    public function verificationWorkspace(Request $request, string|int $id): Response
    {
        $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($id);
        if (! $request->user()->canAccessReceipt($kuitansi)) {
            abort(403, 'Anda tidak memiliki hak akses untuk membuka ruang verifikasi kuitansi ini.');
        }

        return Inertia::render('Verification/Workspace', ['id' => $kuitansi->id]);
    }

    /**
     * Halaman Lembar Kendali Kuitansi (Inertia: ControlSheet/Show).
     */
    public function controlSheet(Request $request, string|int $id): Response
    {
        $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($id);
        if (! $request->user()->canAccessReceipt($kuitansi)) {
            abort(403, 'Anda tidak memiliki hak akses untuk membuka lembar kendali kuitansi ini.');
        }

        return Inertia::render('ControlSheet/Show', ['id' => $kuitansi->id]);
    }
}
