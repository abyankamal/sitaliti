<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Setup Permissions
        $permissions = [
            'kuitansi.view',
            'kuitansi.create',
            'kuitansi.edit',
            'kuitansi.delete',
            'kuitansi.submit_pptk',
            'kuitansi.pptk_approve',
            'kuitansi.pptk_reject',
            'verification.queue',
            'verification.audit',
            'verification.process_action',
            'verification.generate_qr',
            'bundle.view',
            'bundle.create',
            'bundle.unbundle',
            'bundle.record_sp2d',
            'dashboard.view_all',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        // 2. Setup 5 Roles & Assign Granular Permissions
        $rolePermissionsMap = [
            'OPERATOR' => [
                'kuitansi.view',
                'kuitansi.create',
                'kuitansi.edit',
                'kuitansi.submit_pptk',
            ],
            'BPP' => [
                'kuitansi.view',
                'bundle.view',
                'bundle.create',
                'bundle.unbundle',
            ],
            'PPTK' => [
                'kuitansi.view',
                'kuitansi.pptk_approve',
                'kuitansi.pptk_reject',
                'verification.queue',
                'verification.audit',
            ],
            'VERIFIKATOR' => [
                'kuitansi.view',
                'verification.queue',
                'verification.audit',
                'verification.process_action',
                'verification.generate_qr',
            ],
            'KPA' => [
                'kuitansi.view',
                'bundle.view',
                'bundle.create',
                'bundle.unbundle',
                'bundle.record_sp2d',
                'dashboard.view_all',
            ],
        ];

        foreach ($rolePermissionsMap as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        $this->call([
            UnitKerjaSeeder::class,
            MasterChecklistSeeder::class,
        ]);

        // Otomatis jalankan import RAK jika file tersedia
        $rakPath = storage_path('app/rak/RAK_Belanja_7.01.0.00.0.00.41.0000 - Kecamatan Tarogong Kidul.xlsx');
        if (file_exists($rakPath)) {
            $this->command->call('sitaliti:import-rak', ['file' => $rakPath]);
        }

        // Akun Resmi Percontohan Sesuai Struktur Brief SI-TALITI v2.0
        $officialUsers = [
            [
                'name' => 'Operator BPP Kecamatan',
                'email' => 'operator@tarkid.garutkab.go.id',
                'role' => 'OPERATOR',
            ],
            [
                'name' => 'Bendahara Pengeluaran Pembantu (BPP)',
                'email' => 'bpp@tarkid.garutkab.go.id',
                'role' => 'BPP',
            ],
            [
                'name' => 'Pejabat Pelaksana Teknis Kegiatan (PPTK)',
                'email' => 'pptk@tarkid.garutkab.go.id',
                'role' => 'PPTK',
            ],
            [
                'name' => 'Tim Verifikator Akuntabilitas',
                'email' => 'verifikator@tarkid.garutkab.go.id',
                'role' => 'VERIFIKATOR',
            ],
            [
                'name' => 'Camat Tarogong Kidul (KPA)',
                'email' => 'camat@tarkid.garutkab.go.id',
                'role' => 'KPA',
            ],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'role' => 'OPERATOR',
            ],
        ];

        foreach ($officialUsers as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'role' => $userData['role'],
                    'password' => Hash::make('password'),
                ]
            );
            $user->syncSpatieRole();
        }
    }
}
