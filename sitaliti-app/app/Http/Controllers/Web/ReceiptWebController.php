<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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

        $receipts = $this->receiptQueryService->getPaginatedReceipts($user, $filters, $perPage);
        $tabCounts = $this->receiptQueryService->getStatusCounts($user, $filters);
        $unitKerjaList = UnitKerja::where('is_active', true)
            ->select('id', 'kode_unit', 'nama_unit', 'tipe')
            ->orderBy('id', 'asc')
            ->get();

        return Inertia::render('Receipts/Index', [
            'receipts' => $receipts,
            'tabCounts' => $tabCounts,
            'filters' => $filters,
            'unitKerjaList' => $unitKerjaList,
        ]);
    }
}
