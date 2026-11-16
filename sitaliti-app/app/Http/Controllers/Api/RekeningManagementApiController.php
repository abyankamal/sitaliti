<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Services\BudgetManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RekeningManagementApiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected BudgetManagementService $budgetManagementService
    ) {}

    /**
     * Daftar rekening anggaran beserta ringkasan pagu, terkunci, realisasi, dan jadwal kas 12 bulan.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeSkpdAdmin($request);

        $query = AnggaranRekening::with([
            'unitKerja',
            'kasBulanan' => fn ($q) => $q->orderBy('bulan', 'asc'),
        ])
            ->withSum('kasBulanan as total_alokasi', 'alokasi_pagu')
            ->withSum('kasBulanan as total_terkunci', 'terkunci_bulan')
            ->withSum('kasBulanan as total_realisasi', 'realisasi_bulan');

        if ($request->filled('unit_kerja_id')) {
            $query->where('unit_kerja_id', (int) $request->input('unit_kerja_id'));
        }

        if ($request->filled('q')) {
            $keyword = '%'.trim((string) $request->input('q')).'%';
            $query->where(function ($q) use ($keyword) {
                $likeOperator = $q->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $q->where('kode_sub_kegiatan', $likeOperator, $keyword)
                    ->orWhere('nama_sub_kegiatan', $likeOperator, $keyword)
                    ->orWhere('kode_rekening', $likeOperator, $keyword)
                    ->orWhere('nama_rekening', $likeOperator, $keyword);
            });
        }

        $paginated = $query->orderBy('unit_kerja_id')
            ->orderBy('kode_sub_kegiatan')
            ->orderBy('kode_rekening')
            ->paginate(25);

        $paginated->getCollection()->transform(fn (AnggaranRekening $r) => $this->serializeRekening($r));

        return $this->successResponse($paginated, 'Daftar rekening anggaran berhasil dimuat.');
    }

    /**
     * Daftar subkegiatan eksisting per unit kerja untuk dropdown form tambah rekening.
     */
    public function subActivities(Request $request): JsonResponse
    {
        $this->authorizeSkpdAdmin($request);

        $query = AnggaranRekening::query()
            ->select('unit_kerja_id', 'kode_sub_kegiatan', 'nama_sub_kegiatan')
            ->distinct();

        if ($request->filled('unit_kerja_id')) {
            $query->where('unit_kerja_id', (int) $request->input('unit_kerja_id'));
        }

        $items = $query->orderBy('kode_sub_kegiatan')->get();

        return $this->successResponse($items, 'Daftar subkegiatan berhasil dimuat.');
    }

    /**
     * Menyimpan kode rekening baru beserta alokasi kas 12 bulan.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeSkpdAdmin($request);

        $validated = $request->validate([
            'unit_kerja_id' => ['required', Rule::exists('master_unit_kerja', 'id')->where('is_active', true)],
            'kode_sub_kegiatan' => ['required', 'string', 'max:50', 'regex:/^7\.01(\.\d+){4}$/'],
            'nama_sub_kegiatan' => ['required', 'string', 'max:255'],
            'kode_rekening' => ['required', 'string', 'max:50', 'regex:/^5(\.\d+){5}$/'],
            'nama_rekening' => ['required', 'string', 'max:255'],
            'kategori_belanja' => ['required', 'string', Rule::in(['B.1', 'B.2', 'B.3', 'B.4', 'B.5', 'B.6'])],
            'alokasi' => ['required', 'array', 'size:12'],
            'alokasi.*' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);

        $rekening = $this->budgetManagementService->createRekening($validated);
        $rekening->load(['unitKerja', 'kasBulanan' => fn ($q) => $q->orderBy('bulan', 'asc')]);

        return $this->successResponse(
            $this->serializeRekening($rekening),
            'Kode rekening dan alokasi pagu 12 bulan berhasil ditambahkan.',
            201
        );
    }

    /**
     * Memperbarui alokasi kas 12 bulan pada rekening yang sudah ada.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeSkpdAdmin($request);

        $validated = $request->validate([
            'alokasi' => ['required', 'array', 'size:12'],
            'alokasi.*' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);

        $rekening = $this->budgetManagementService->updateAllocations($id, $validated, $request->user());

        return $this->successResponse(
            $this->serializeRekening($rekening),
            'Alokasi pagu bulanan berhasil diperbarui.'
        );
    }

    private function authorizeSkpdAdmin(Request $request): void
    {
        abort_if(
            $request->user()?->isKelurahanScoped(),
            403,
            'Hanya administrator anggaran tingkat SKPD yang berwenang mengelola rekening.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRekening(AnggaranRekening $r): array
    {
        $kasBulanan = $r->kasBulanan->sortBy('bulan')->values()->map(fn (AnggaranKasBulanan $k) => [
            'id' => $k->id,
            'bulan' => (int) $k->bulan,
            'alokasi_pagu' => (float) $k->alokasi_pagu,
            'terkunci_bulan' => (float) $k->terkunci_bulan,
            'realisasi_bulan' => (float) $k->realisasi_bulan,
        ])->all();

        $totalAlokasi = $r->total_alokasi !== null
            ? (float) $r->total_alokasi
            : (float) collect($kasBulanan)->sum('alokasi_pagu');
        $totalTerkunci = $r->total_terkunci !== null
            ? (float) $r->total_terkunci
            : (float) collect($kasBulanan)->sum('terkunci_bulan');
        $totalRealisasi = $r->total_realisasi !== null
            ? (float) $r->total_realisasi
            : (float) collect($kasBulanan)->sum('realisasi_bulan');

        return [
            'id' => $r->id,
            'unit_kerja_id' => $r->unit_kerja_id,
            'unit_kerja' => $r->unitKerja ? [
                'id' => $r->unitKerja->id,
                'kode_unit' => $r->unitKerja->kode_unit,
                'nama_unit' => $r->unitKerja->nama_unit,
                'tipe' => $r->unitKerja->tipe,
            ] : null,
            'kode_sub_kegiatan' => $r->kode_sub_kegiatan,
            'nama_sub_kegiatan' => $r->nama_sub_kegiatan,
            'kode_rekening' => $r->kode_rekening,
            'nama_rekening' => $r->nama_rekening,
            'kategori_belanja' => $r->kategori_belanja,
            'pagu_murni' => (float) $r->pagu_murni,
            'pagu_perubahan' => (float) $r->pagu_perubahan,
            'total_alokasi' => $totalAlokasi,
            'total_terkunci' => $totalTerkunci,
            'total_realisasi' => $totalRealisasi,
            'kas_bulanan' => $kasBulanan,
        ];
    }
}
