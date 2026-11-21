<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Pindahkan user OPERATOR lama ke BPP (Kelurahan) atau BP (Kecamatan)
        $kelurahanUnitIds = DB::table('master_unit_kerja')
            ->where('tipe', 'KELURAHAN')
            ->pluck('id')
            ->all();

        $kecamatanUnitIds = DB::table('master_unit_kerja')
            ->where('tipe', 'KECAMATAN')
            ->pluck('id')
            ->all();

        if (! empty($kelurahanUnitIds)) {
            DB::table('users')
                ->where('role', 'OPERATOR')
                ->whereIn('unit_kerja_id', $kelurahanUnitIds)
                ->update(['role' => 'BPP']);
        }

        // Sisa OPERATOR (Kecamatan atau unit_kerja_id null) dipetakan ke BP
        DB::table('users')
            ->where('role', 'OPERATOR')
            ->update(['role' => 'BP']);

        // 2. Pindahkan BPP yang bertugas di unit KECAMATAN menjadi BP
        if (! empty($kecamatanUnitIds)) {
            DB::table('users')
                ->where('role', 'BPP')
                ->whereIn('unit_kerja_id', $kecamatanUnitIds)
                ->update(['role' => 'BP']);
        }

        // 3. Pindahkan kuitansi in-flight MENUNGGU_LURAH ke MENUNGGU_VERIFIKASI agar tidak tersangkut
        DB::table('kuitansi')
            ->where('status', 'MENUNGGU_LURAH')
            ->update(['status' => 'MENUNGGU_VERIFIKASI']);

        // 4. Sinkronkan seluruh permissions dan roles dari User::defaultRolePermissions()
        $rolePermissionsMap = User::defaultRolePermissions();

        $allPermissions = collect($rolePermissionsMap)
            ->flatten()
            ->push('kuitansi.delete')
            ->unique()
            ->values();

        foreach ($allPermissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        foreach ($rolePermissionsMap as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        // 5. Sinkronkan ulang Spatie role setiap user sesuai kolom users.role
        User::query()->each(function (User $user) {
            if (! empty($user->role)) {
                $user->syncRoles([$user->role]);
            }
        });

        // 6. Hapus role OPERATOR dari tabel roles
        Role::where('name', 'OPERATOR')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'OPERATOR', 'guard_name' => 'web']);
    }
};
