<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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
        $user = $request->user();
        $filters = $request->only([
            'status',
            'unit_kerja_id',
            'bulan_anggaran',
            'kategori_belanja',
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

        if ($user && ! $user->isSkpdWide()) {
            if ($user->unit_kerja_id) {
                $unitKerjaQuery->where('id', $user->unit_kerja_id);
            } else {
                $unitKerjaQuery->whereRaw('1 = 0');
            }
        }

        $unitKerjaList = $unitKerjaQuery->get();

        return Inertia::render('Receipts/Index', [
            'receipts' => $receipts,
            'tabCounts' => $tabCounts,
            'filters' => $filters,
            'unitKerjaList' => $unitKerjaList,
        ]);
    }

    /**
     * Halaman Edit / Revisi Kuitansi (Inertia: Receipts/Edit).
     */
    public function edit(Request $request, string|int $id): Response
    {
        $kuitansi = Kuitansi::resolveOrFailByIdOrNumber($id);
        if (! $request->user()->canAccessReceipt($kuitansi)) {
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
