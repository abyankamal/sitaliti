<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use App\Models\MasterRekapBelanjaOperasi;
use App\Services\MasterRekapBelanjaOperasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MasterRekapBelanjaOperasiApiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected MasterRekapBelanjaOperasiService $service
    ) {}

    /**
     * Menampilkan daftar pos Master Rekap Belanja Operasi.
     */
    public function index(Request $request): JsonResponse
    {
        $items = $this->service->list([
            'is_active' => $request->input('is_active'),
            'search' => $request->input('search'),
        ]);

        return $this->successResponse($items, 'Daftar master rekap belanja operasi berhasil diambil');
    }

    /**
     * Mengambil opsi kode rekening Belanja Operasi (5.1.% leaf) beserta status pemetaannya.
     */
    public function rekeningOptions(Request $request): JsonResponse
    {
        $excludeId = $request->filled('exclude_master_rekap_id')
            ? (int) $request->input('exclude_master_rekap_id')
            : null;

        $options = $this->service->getRekeningOptions(
            $request->filled('search') ? (string) $request->input('search') : null,
            $excludeId
        );

        return $this->successResponse($options, 'Opsi kode rekening belanja operasi berhasil diambil');
    }

    /**
     * Menyimpan pos Master Rekap Belanja Operasi baru.
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'kode' => strtoupper(trim((string) $request->input('kode', ''))),
        ]);

        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:master_rekap_belanja_operasi,kode'],
            'nama' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'kode_rekening' => ['nullable', 'array'],
            'kode_rekening.*' => ['string', 'max:64'],
        ]);

        $pos = $this->service->create($validated);

        return $this->successResponse($pos, 'Pos rekap belanja operasi berhasil ditambahkan', 201);
    }

    /**
     * Memperbarui pos Master Rekap Belanja Operasi beserta pemetaan rekeningnya.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $pos = MasterRekapBelanjaOperasi::findOrFail($id);

        $request->merge([
            'kode' => strtoupper(trim((string) $request->input('kode', ''))),
        ]);

        $validated = $request->validate([
            'kode' => [
                'required',
                'string',
                'max:30',
                Rule::unique('master_rekap_belanja_operasi', 'kode')->ignore($pos->id),
            ],
            'nama' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'kode_rekening' => ['nullable', 'array'],
            'kode_rekening.*' => ['string', 'max:64'],
        ]);

        $updated = $this->service->update($pos, $validated);

        return $this->successResponse($updated, 'Pos rekap belanja operasi berhasil diperbarui');
    }

    /**
     * Menghapus pos Master Rekap Belanja Operasi.
     */
    public function destroy(int $id): JsonResponse
    {
        $pos = MasterRekapBelanjaOperasi::findOrFail($id);
        $this->service->delete($pos);

        return $this->successResponse(null, 'Pos rekap belanja operasi berhasil dihapus');
    }
}
