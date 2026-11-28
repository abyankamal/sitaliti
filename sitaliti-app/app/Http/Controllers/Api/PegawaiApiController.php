<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PegawaiApiController extends Controller
{
    use ApiResponseTrait;

    /**
     * Mengembalikan daftar opsi pegawai untuk dropdown SPPD dan filter laporan.
     */
    public function options(Request $request): JsonResponse
    {
        $query = Pegawai::query()
            ->with('unitKerja:id,kode_unit,nama_unit');

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        if ($request->filled('unit_kerja_id')) {
            $query->where('unit_kerja_id', (int) $request->input('unit_kerja_id'));
        }

        if ($request->filled('search')) {
            $this->applySearchFilter($query, (string) $request->input('search'));
        }

        $limit = min(max((int) $request->input('limit', 200), 1), 500);

        $items = $query->orderBy('nama')
            ->limit($limit)
            ->get(['id', 'unit_kerja_id', 'status_kepegawaian', 'nip', 'nik', 'nama', 'jabatan', 'golongan', 'is_active']);

        return $this->successResponse($items, 'Daftar opsi pegawai berhasil dimuat');
    }

    /**
     * Menampilkan daftar Master Pegawai terpaginasi sesuai scoping unit kerja.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($forbidden = $this->ensureScopedAccess($user)) {
            return $forbidden;
        }

        $query = Pegawai::query()
            ->with('unitKerja:id,kode_unit,nama_unit')
            ->withCount('sppdPeserta');

        if (! $user->isSkpdWide()) {
            $query->where('unit_kerja_id', $user->unit_kerja_id);
        } elseif ($request->filled('unit_kerja_id')) {
            $query->where('unit_kerja_id', (int) $request->input('unit_kerja_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $this->applySearchFilter($query, (string) $request->input('search'));
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $paginated = $query->orderBy('nama')->paginate($perPage);

        return $this->successResponse($paginated, 'Daftar master pegawai berhasil diambil');
    }

    /**
     * Menyimpan data pegawai baru.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($forbidden = $this->ensureScopedAccess($user)) {
            return $forbidden;
        }

        $statusKepegawaian = strtoupper(trim((string) $request->input('status_kepegawaian', 'ASN')));

        $request->merge([
            'status_kepegawaian' => $statusKepegawaian,
            'nip' => Pegawai::normalizeNip($request->input('nip')),
        ]);

        $validated = $request->validate([
            'status_kepegawaian' => ['required', 'string', Rule::in(['ASN', 'PPPK', 'NON_ASN'])],
            'nip' => [
                Rule::requiredIf(fn () => $statusKepegawaian !== 'NON_ASN'),
                'nullable',
                'string',
                'max:30',
                'unique:pegawai,nip',
            ],
            'nik' => ['nullable', 'string', 'max:20'],
            'nama' => ['required', 'string', 'max:200'],
            'jabatan' => ['nullable', 'string', 'max:150'],
            'golongan' => ['nullable', 'string', 'max:50'],
            'unit_kerja_id' => ['nullable', 'integer', 'exists:master_unit_kerja,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $unitKerjaId = $user->isSkpdWide()
            ? ($validated['unit_kerja_id'] ?? null)
            : $user->unit_kerja_id;

        $pegawai = Pegawai::create([
            'unit_kerja_id' => $unitKerjaId,
            'status_kepegawaian' => $validated['status_kepegawaian'],
            'nip' => $validated['nip'] ?? null,
            'nik' => isset($validated['nik']) ? trim((string) $validated['nik']) : null,
            'nama' => trim($validated['nama']),
            'jabatan' => isset($validated['jabatan']) ? trim($validated['jabatan']) : null,
            'golongan' => isset($validated['golongan']) ? trim($validated['golongan']) : null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return $this->successResponse(
            $pegawai->load('unitKerja:id,kode_unit,nama_unit'),
            'Data pegawai berhasil ditambahkan',
            201
        );
    }

    /**
     * Memperbarui data pegawai.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($forbidden = $this->ensureScopedAccess($user)) {
            return $forbidden;
        }

        $pegawai = Pegawai::find($id);
        if (! $pegawai) {
            return $this->errorResponse('PEGAWAI_NOT_FOUND', 'Data pegawai tidak ditemukan.', null, 404);
        }

        if (! $user->isSkpdWide() && (int) $pegawai->unit_kerja_id !== (int) $user->unit_kerja_id) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak berwenang mengubah data pegawai unit kerja lain.', null, 403);
        }

        $statusKepegawaian = strtoupper(trim((string) $request->input('status_kepegawaian', $pegawai->status_kepegawaian ?? 'ASN')));

        $mergePayload = ['status_kepegawaian' => $statusKepegawaian];
        if ($request->has('nip')) {
            $mergePayload['nip'] = Pegawai::normalizeNip($request->input('nip'));
        }
        $request->merge($mergePayload);

        $validated = $request->validate([
            'status_kepegawaian' => ['required', 'string', Rule::in(['ASN', 'PPPK', 'NON_ASN'])],
            'nip' => [
                Rule::requiredIf(fn () => $statusKepegawaian !== 'NON_ASN'),
                'nullable',
                'string',
                'max:30',
                Rule::unique('pegawai', 'nip')->ignore($pegawai->id),
            ],
            'nik' => ['nullable', 'string', 'max:20'],
            'nama' => ['required', 'string', 'max:200'],
            'jabatan' => ['nullable', 'string', 'max:150'],
            'golongan' => ['nullable', 'string', 'max:50'],
            'unit_kerja_id' => ['nullable', 'integer', 'exists:master_unit_kerja,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $unitKerjaId = $user->isSkpdWide()
            ? (array_key_exists('unit_kerja_id', $validated) ? $validated['unit_kerja_id'] : $pegawai->unit_kerja_id)
            : $user->unit_kerja_id;

        $pegawai->update([
            'unit_kerja_id' => $unitKerjaId,
            'status_kepegawaian' => $validated['status_kepegawaian'],
            'nip' => array_key_exists('nip', $validated) ? $validated['nip'] : $pegawai->nip,
            'nik' => array_key_exists('nik', $validated)
                ? ($validated['nik'] !== null ? trim((string) $validated['nik']) : null)
                : $pegawai->nik,
            'nama' => trim($validated['nama']),
            'jabatan' => array_key_exists('jabatan', $validated)
                ? ($validated['jabatan'] !== null ? trim($validated['jabatan']) : null)
                : $pegawai->jabatan,
            'golongan' => array_key_exists('golongan', $validated)
                ? ($validated['golongan'] !== null ? trim($validated['golongan']) : null)
                : $pegawai->golongan,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $pegawai->is_active,
        ]);

        return $this->successResponse(
            $pegawai->load('unitKerja:id,kode_unit,nama_unit'),
            'Data pegawai berhasil diperbarui'
        );
    }

    /**
     * Menghapus data pegawai jika belum pernah digunakan pada jadwal SPPD.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($forbidden = $this->ensureScopedAccess($user)) {
            return $forbidden;
        }

        $pegawai = Pegawai::find($id);
        if (! $pegawai) {
            return $this->errorResponse('PEGAWAI_NOT_FOUND', 'Data pegawai tidak ditemukan.', null, 404);
        }

        if (! $user->isSkpdWide() && (int) $pegawai->unit_kerja_id !== (int) $user->unit_kerja_id) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak berwenang menghapus data pegawai unit kerja lain.', null, 403);
        }

        if ($pegawai->sppdPeserta()->exists() || $pegawai->sppdAssignmentPeserta()->exists()) {
            return $this->errorResponse(
                'PEGAWAI_IN_USE',
                'Pegawai tidak dapat dihapus karena sudah memiliki riwayat perjalanan dinas (SPPD). Silakan nonaktifkan status pegawai ini.',
                null,
                422
            );
        }

        $pegawai->delete();

        return $this->successResponse(null, 'Data pegawai berhasil dihapus');
    }

    /**
     * Memastikan user non-SKPD memiliki unit kerja yang valid (fail-closed).
     */
    private function ensureScopedAccess(?User $user): ?JsonResponse
    {
        if (! $user) {
            return $this->errorResponse('UNAUTHENTICATED', 'Autentikasi diperlukan.', null, 401);
        }

        if (! $user->isSkpdWide() && $user->unit_kerja_id === null) {
            return $this->errorResponse('FORBIDDEN', 'Akun Anda belum terhubung dengan unit kerja.', null, 403);
        }

        return null;
    }

    /**
     * Menerapkan filter pencarian nama, NIP, atau jabatan yang kompatibel lintas driver DB.
     */
    private function applySearchFilter($query, string $search): void
    {
        $trimmed = trim($search);
        if ($trimmed === '') {
            return;
        }

        $normalizedNip = Pegawai::normalizeNip($trimmed);
        $likeOperator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $keyword = '%'.$trimmed.'%';
        $nipKeyword = '%'.$normalizedNip.'%';

        $query->where(function ($q) use ($likeOperator, $keyword, $nipKeyword) {
            $q->where('nama', $likeOperator, $keyword)
                ->orWhere('nip', $likeOperator, $nipKeyword)
                ->orWhere('nik', $likeOperator, $nipKeyword)
                ->orWhere('jabatan', $likeOperator, $keyword);
        });
    }
}
