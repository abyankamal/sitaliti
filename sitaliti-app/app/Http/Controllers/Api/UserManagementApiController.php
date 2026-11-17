<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiRiwayat;
use App\Models\KuitansiVerifikasiChecklist;
use App\Models\PengajuanSpj;
use App\Models\PptkSubkegiatanAssignment;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserManagementApiController extends Controller
{
    /**
     * Menampilkan daftar seluruh pengguna beserta unit kerja dan penugasan PPTK.
     */
    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureSkpdAdmin($request)) {
            return $forbidden;
        }

        $query = User::with(['unitKerja', 'pptkSubkegiatanAssignments']);

        if ($request->filled('q')) {
            $keyword = '%'.$request->q.'%';
            $query->where(function ($q) use ($keyword) {
                $likeOperator = $q->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $q->where('name', $likeOperator, $keyword)
                    ->orWhere('username', $likeOperator, $keyword)
                    ->orWhere('email', $likeOperator, $keyword)
                    ->orWhere('nip', $likeOperator, $keyword)
                    ->orWhere('jabatan', $likeOperator, $keyword);
            });
        }

        if ($request->filled('unit_kerja_id')) {
            $query->where('unit_kerja_id', $request->unit_kerja_id);
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Mengambil daftar opsi subkegiatan untuk penugasan PPTK berdasarkan unit kerja.
     */
    public function subActivitiesOptions(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureSkpdAdmin($request)) {
            return $forbidden;
        }

        $query = AnggaranRekening::select('unit_kerja_id', 'kode_sub_kegiatan', 'nama_sub_kegiatan')
            ->distinct();

        if ($request->filled('unit_kerja_id')) {
            $query->where('unit_kerja_id', $request->unit_kerja_id);
        }

        $items = $query->orderBy('kode_sub_kegiatan')->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * Menyimpan pengguna baru dan penugasan subkegiatan jika peran PPTK.
     */
    public function store(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureSkpdAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:60', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:users,username'],
            'email' => 'nullable|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', 'string', Rule::in(['OPERATOR', 'BPP', 'PPTK', 'VERIFIKATOR', 'LURAH', 'KPA'])],
            'unit_kerja_id' => ['required', Rule::exists('master_unit_kerja', 'id')->where('is_active', true)],
            'nip' => 'nullable|string|max:50',
            'jabatan' => 'nullable|string|max:100',
            'sub_kegiatan_codes' => 'nullable|array',
            'sub_kegiatan_codes.*' => 'string',
        ]);

        $this->validateRoleUnitCompatibility($validated['role'], (int) $validated['unit_kerja_id']);

        return DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'username' => strtolower($validated['username']),
                'email' => $validated['email'] ?? null,
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'unit_kerja_id' => $validated['unit_kerja_id'],
                'nip' => $validated['nip'] ?? null,
                'jabatan' => $validated['jabatan'] ?? null,
            ]);

            if ($user->role === 'PPTK' && ! empty($validated['sub_kegiatan_codes'])) {
                foreach ($validated['sub_kegiatan_codes'] as $code) {
                    PptkSubkegiatanAssignment::create([
                        'user_id' => $user->id,
                        'unit_kerja_id' => $user->unit_kerja_id,
                        'kode_sub_kegiatan' => $code,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengguna baru berhasil ditambahkan.',
                'data' => $user->load(['unitKerja', 'pptkSubkegiatanAssignments']),
            ], 201);
        });
    }

    /**
     * Memperbarui data pengguna dan sinkronisasi penugasan PPTK.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        if ($forbidden = $this->ensureSkpdAdmin($request)) {
            return $forbidden;
        }

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:60', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role' => ['required', 'string', Rule::in(['OPERATOR', 'BPP', 'PPTK', 'VERIFIKATOR', 'LURAH', 'KPA'])],
            'unit_kerja_id' => ['required', Rule::exists('master_unit_kerja', 'id')->where('is_active', true)],
            'nip' => 'nullable|string|max:50',
            'jabatan' => 'nullable|string|max:100',
            'sub_kegiatan_codes' => 'nullable|array',
            'sub_kegiatan_codes.*' => 'string',
        ]);

        $this->validateRoleUnitCompatibility($validated['role'], (int) $validated['unit_kerja_id']);

        return DB::transaction(function () use ($user, $validated) {
            $updateData = [
                'name' => $validated['name'],
                'username' => strtolower($validated['username']),
                'email' => $validated['email'] ?? null,
                'role' => $validated['role'],
                'unit_kerja_id' => $validated['unit_kerja_id'],
                'nip' => $validated['nip'] ?? null,
                'jabatan' => $validated['jabatan'] ?? null,
            ];

            if (! empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $user->update($updateData);

            if ($user->role === 'PPTK') {
                PptkSubkegiatanAssignment::where('user_id', $user->id)->delete();

                if (! empty($validated['sub_kegiatan_codes'])) {
                    foreach ($validated['sub_kegiatan_codes'] as $code) {
                        PptkSubkegiatanAssignment::create([
                            'user_id' => $user->id,
                            'unit_kerja_id' => $user->unit_kerja_id,
                            'kode_sub_kegiatan' => $code,
                        ]);
                    }
                }
            } else {
                PptkSubkegiatanAssignment::where('user_id', $user->id)->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Data pengguna berhasil diperbarui.',
                'data' => $user->load(['unitKerja', 'pptkSubkegiatanAssignments']),
            ]);
        });
    }

    /**
     * Menghapus pengguna dengan proteksi jejak audit BPK & integritas transaksi.
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        if ($forbidden = $this->ensureSkpdAdmin($request)) {
            return $forbidden;
        }

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Anda tidak dapat menghapus akun Anda sendiri.',
                ],
            ], 422);
        }

        $hasTransactions = Kuitansi::where('user_id', $user->id)
            ->orWhere('pptk_user_id', $user->id)
            ->orWhere('lurah_user_id', $user->id)
            ->orWhere('verifikator_id', $user->id)
            ->exists()
            || PengajuanSpj::where('user_id', $user->id)
                ->orWhere('ppk_skpd_id', $user->id)
                ->exists()
            || KuitansiRiwayat::where('user_id', $user->id)->exists()
            || KuitansiVerifikasiChecklist::where('verified_by', $user->id)->exists();

        if ($hasTransactions) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Pengguna tidak dapat dihapus karena tercatat dalam jejak audit kuitansi. Anda dapat mengubah perannya jika dinonaktifkan.',
                ],
            ], 422);
        }

        DB::transaction(function () use ($user) {
            PptkSubkegiatanAssignment::where('user_id', $user->id)->delete();
            $user->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil dihapus.',
        ]);
    }

    private function ensureSkpdAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->isKelurahanScoped() || ! $user->isSkpdWide()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Hanya administrator tingkat SKPD yang berwenang mengelola pengguna.',
                ],
            ], 403);
        }

        return null;
    }

    private function validateRoleUnitCompatibility(string $role, int $unitKerjaId): void
    {
        $unit = UnitKerja::findOrFail($unitKerjaId);

        if (in_array($role, ['VERIFIKATOR', 'KPA'], true) && $unit->tipe === 'KELURAHAN') {
            throw ValidationException::withMessages([
                'role' => ['Peran VERIFIKATOR atau KPA hanya dapat ditetapkan pada unit kerja tipe KECAMATAN.'],
            ]);
        }

        if ($role === 'LURAH' && $unit->tipe === 'KECAMATAN') {
            throw ValidationException::withMessages([
                'role' => ['Peran LURAH hanya dapat ditetapkan pada unit kerja tipe KELURAHAN.'],
            ]);
        }
    }
}
