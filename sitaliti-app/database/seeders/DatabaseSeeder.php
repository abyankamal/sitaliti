<?php

namespace Database\Seeders;

use App\Models\PptkSubkegiatanAssignment;
use App\Models\UnitKerja;
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

        // 1. Seed Master Data Unit Kerja & Checklist terlebih dahulu
        $this->call([
            UnitKerjaSeeder::class,
            MasterChecklistSeeder::class,
        ]);

        $kecamatan = UnitKerja::where('tipe', 'KECAMATAN')->first();
        $pataruman = UnitKerja::where('nama_unit', 'Kelurahan Pataruman')->first();
        $sukagalih = UnitKerja::where('nama_unit', 'Kelurahan Sukagalih')->first();

        // 2. Setup Permissions & Roles dari Single Source of Truth (User::defaultRolePermissions)
        $rolePermissionsMap = User::defaultRolePermissions();

        // Kumpulkan seluruh permission unik dari seluruh role (ditambah izin individual kuitansi.delete)
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

        // 3. Otomatis jalankan import RAK jika file tersedia
        $rakPath = storage_path('app/rak/RAK_Belanja_7.01.0.00.0.00.41.0000 - Kecamatan Tarogong Kidul.xlsx');
        if (file_exists($rakPath)) {
            $this->command->call('sitaliti:import-rak', ['file' => $rakPath]);
        }

        // 4. Akun Resmi Percontohan Sesuai Struktur Brief SI-TALITI v2.0
        $officialUsers = [
            // --- Akun Tingkat Kecamatan ---
            [
                'name' => 'Operator SPJ Kecamatan',
                'email' => 'operator@tarkid.garutkab.go.id',
                'role' => 'OPERATOR',
                'unit_kerja_id' => $kecamatan?->id,
            ],
            [
                'name' => 'Bendahara Pengeluaran (BP) Kecamatan',
                'email' => 'bp@tarkid.garutkab.go.id',
                'role' => 'BPP',
                'unit_kerja_id' => $kecamatan?->id,
            ],
            [
                'name' => 'Pejabat Pelaksana Teknis Kegiatan (PPTK) Kecamatan',
                'email' => 'pptk@tarkid.garutkab.go.id',
                'role' => 'PPTK',
                'unit_kerja_id' => $kecamatan?->id,
            ],
            [
                'name' => 'Kasubag Keuangan (PPK Kecamatan)',
                'email' => 'kasubag.keuangan@tarkid.garutkab.go.id',
                'role' => 'VERIFIKATOR',
                'unit_kerja_id' => $kecamatan?->id,
            ],
            [
                'name' => 'Kasubag Keuangan (PPK Kecamatan) - Alias Verifikator',
                'email' => 'verifikator@tarkid.garutkab.go.id',
                'role' => 'VERIFIKATOR',
                'unit_kerja_id' => $kecamatan?->id,
            ],
            [
                'name' => 'Camat Tarogong Kidul (KPA)',
                'email' => 'camat@tarkid.garutkab.go.id',
                'role' => 'KPA',
                'unit_kerja_id' => $kecamatan?->id,
            ],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'role' => 'OPERATOR',
                'unit_kerja_id' => $kecamatan?->id,
            ],

            // --- Akun Tingkat Kelurahan Pataruman ---
            [
                'name' => 'Lurah Pataruman',
                'email' => 'lurah.pataruman@tarkid.garutkab.go.id',
                'role' => 'LURAH',
                'unit_kerja_id' => $pataruman?->id,
            ],
            [
                'name' => 'PPTK Sarpras Kelurahan Pataruman',
                'email' => 'pptk.sarpras.pataruman@tarkid.garutkab.go.id',
                'role' => 'PPTK',
                'unit_kerja_id' => $pataruman?->id,
                'assignment_subkegiatan' => '7.01.03.2.02.0002',
            ],
            [
                'name' => 'PPTK Pemberdayaan Kelurahan Pataruman',
                'email' => 'pptk.pemberdayaan.pataruman@tarkid.garutkab.go.id',
                'role' => 'PPTK',
                'unit_kerja_id' => $pataruman?->id,
                'assignment_subkegiatan' => '7.01.03.2.02.0003',
            ],
            [
                'name' => 'Operator Kelurahan Pataruman',
                'email' => 'operator.pataruman@tarkid.garutkab.go.id',
                'role' => 'OPERATOR',
                'unit_kerja_id' => $pataruman?->id,
            ],
            [
                'name' => 'Bendahara Pengeluaran Pembantu (BPP) Kelurahan Pataruman',
                'email' => 'bpp.pataruman@tarkid.garutkab.go.id',
                'role' => 'BPP',
                'unit_kerja_id' => $pataruman?->id,
            ],
            [
                'name' => 'Bendahara Pengeluaran Pembantu (BPP) Kelurahan - Alias',
                'email' => 'bpp@tarkid.garutkab.go.id',
                'role' => 'BPP',
                'unit_kerja_id' => $pataruman?->id,
            ],

            // --- Akun Tingkat Kelurahan Sukagalih ---
            [
                'name' => 'Lurah Sukagalih',
                'email' => 'lurah.sukagalih@tarkid.garutkab.go.id',
                'role' => 'LURAH',
                'unit_kerja_id' => $sukagalih?->id,
            ],
        ];

        foreach ($officialUsers as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'role' => $userData['role'],
                    'unit_kerja_id' => $userData['unit_kerja_id'] ?? null,
                    'password' => Hash::make('password'),
                ]
            );
            $user->syncSpatieRole();

            // Setup penugasan subkegiatan PPTK bila didefinisikan
            if (! empty($userData['assignment_subkegiatan'])) {
                PptkSubkegiatanAssignment::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'kode_sub_kegiatan' => $userData['assignment_subkegiatan'],
                    ],
                    [
                        'unit_kerja_id' => $userData['unit_kerja_id'],
                    ]
                );
            }
        }
    }
}
