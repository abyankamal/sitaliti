<?php

namespace Tests\Feature\Security;

use App\Models\PptkSubkegiatanAssignment;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeederRoleSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Test role permissions in DB match User::defaultRolePermissions().
     */
    public function test_roles_and_permissions_are_synchronized_with_user_default_role_permissions(): void
    {
        $expectedMap = User::defaultRolePermissions();

        foreach ($expectedMap as $roleName => $expectedPerms) {
            $role = Role::findByName($roleName, 'web');
            $this->assertNotNull($role, "Role {$roleName} must exist in database");

            $actualPerms = $role->permissions()->pluck('name')->sort()->values()->all();
            $sortedExpected = collect($expectedPerms)->sort()->values()->all();

            $this->assertEquals(
                $sortedExpected,
                $actualPerms,
                "Permissions for role {$roleName} do not match User::defaultRolePermissions()"
            );
        }
    }

    /**
     * Test all seeded users have a valid unit_kerja_id.
     */
    public function test_all_seeded_users_have_valid_unit_kerja(): void
    {
        $users = User::all();
        $this->assertGreaterThan(0, $users->count());

        foreach ($users as $user) {
            $this->assertNotNull(
                $user->unit_kerja_id,
                "Seeded user {$user->email} must have a valid unit_kerja_id"
            );
            $this->assertNotNull(
                $user->unitKerja,
                "Seeded user {$user->email} unitKerja relation must resolve"
            );
        }
    }

    /**
     * Test official accounts for Kelurahan Pataruman and Sukagalih.
     */
    public function test_kelurahan_official_accounts_and_pptk_assignments(): void
    {
        $pataruman = UnitKerja::where('nama_unit', 'Kelurahan Pataruman')->firstOrFail();
        $sukagalih = UnitKerja::where('nama_unit', 'Kelurahan Sukagalih')->firstOrFail();

        // 1. Lurah Pataruman
        $lurahPataruman = User::where('email', 'lurah.pataruman@tarkid.garutkab.go.id')->firstOrFail();
        $this->assertEquals('LURAH', $lurahPataruman->role);
        $this->assertEquals($pataruman->id, $lurahPataruman->unit_kerja_id);
        $this->assertTrue($lurahPataruman->hasRole('LURAH'));
        $this->assertTrue($lurahPataruman->can('kuitansi.lurah_approve'));

        // 2. PPTK Sarpras Pataruman
        $pptkSarpras = User::where('email', 'pptk.sarpras.pataruman@tarkid.garutkab.go.id')->firstOrFail();
        $this->assertEquals('PPTK', $pptkSarpras->role);
        $this->assertEquals($pataruman->id, $pptkSarpras->unit_kerja_id);
        $sarprasAssignment = PptkSubkegiatanAssignment::where('user_id', $pptkSarpras->id)->firstOrFail();
        $this->assertEquals('7.01.03.2.02.0002', $sarprasAssignment->kode_sub_kegiatan);
        $this->assertEquals($pataruman->id, $sarprasAssignment->unit_kerja_id);

        // 3. PPTK Pemberdayaan Pataruman
        $pptkPemberdayaan = User::where('email', 'pptk.pemberdayaan.pataruman@tarkid.garutkab.go.id')->firstOrFail();
        $this->assertEquals('PPTK', $pptkPemberdayaan->role);
        $this->assertEquals($pataruman->id, $pptkPemberdayaan->unit_kerja_id);
        $pemberdayaanAssignment = PptkSubkegiatanAssignment::where('user_id', $pptkPemberdayaan->id)->firstOrFail();
        $this->assertEquals('7.01.03.2.02.0003', $pemberdayaanAssignment->kode_sub_kegiatan);
        $this->assertEquals($pataruman->id, $pemberdayaanAssignment->unit_kerja_id);

        // 4. Lurah Sukagalih
        $lurahSukagalih = User::where('email', 'lurah.sukagalih@tarkid.garutkab.go.id')->firstOrFail();
        $this->assertEquals('LURAH', $lurahSukagalih->role);
        $this->assertEquals($sukagalih->id, $lurahSukagalih->unit_kerja_id);
    }

    /**
     * Test official accounts for Kecamatan.
     */
    public function test_kecamatan_official_accounts(): void
    {
        $kecamatan = UnitKerja::where('tipe', 'KECAMATAN')->firstOrFail();

        $accounts = [
            'operator@tarkid.garutkab.go.id' => 'OPERATOR',
            'bp@tarkid.garutkab.go.id' => 'BPP',
            'pptk@tarkid.garutkab.go.id' => 'PPTK',
            'kasubag.keuangan@tarkid.garutkab.go.id' => 'VERIFIKATOR',
            'camat@tarkid.garutkab.go.id' => 'KPA',
        ];

        foreach ($accounts as $email => $role) {
            $user = User::where('email', $email)->firstOrFail();
            $this->assertEquals($role, $user->role);
            $this->assertEquals($kecamatan->id, $user->unit_kerja_id);
            $this->assertTrue($user->hasRole($role));
        }
    }

    public function test_reseeding_does_not_overwrite_existing_user_password(): void
    {
        $user = User::where('email', 'operator@tarkid.garutkab.go.id')->firstOrFail();
        $user->update(['password' => Hash::make('MyCustomStrongPass!99')]);

        $this->seed(DatabaseSeeder::class);

        $user->refresh();
        $this->assertTrue(Hash::check('MyCustomStrongPass!99', $user->password));
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_changing_user_role_revokes_previous_spatie_role(): void
    {
        $user = User::where('email', 'operator@tarkid.garutkab.go.id')->firstOrFail();
        $this->assertTrue($user->hasRole('OPERATOR'));

        $user->update(['role' => 'PPTK']);
        $user->refresh();

        $this->assertTrue($user->hasRole('PPTK'));
        $this->assertFalse($user->hasRole('OPERATOR'));
        $this->assertCount(1, $user->roles);
    }

    public function test_production_seeder_rejects_empty_or_default_password(): void
    {
        $originalEnv = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            config(['app.seed_initial_password' => null]);
            $this->expectException(RuntimeException::class);
            $this->artisan('db:seed', ['--force' => true]);
        } finally {
            app()->detectEnvironment(fn () => $originalEnv);
        }
    }

    public function test_production_seeder_skips_test_user_and_uses_configured_password(): void
    {
        User::where('email', 'test@example.com')->delete();

        $originalEnv = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            config(['app.seed_initial_password' => 'ProdInitialPass#2026']);
            $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

            $this->assertNull(User::where('email', 'test@example.com')->first());

            // Akun baru (jika belum ada) akan memakai configured password;
            // akun yang sudah ada di setUp tetap tidak tertimpa password-nya.
            $operator = User::where('email', 'operator@tarkid.garutkab.go.id')->firstOrFail();
            $this->assertTrue(Hash::check('password', $operator->password));

            // Verifikasi pembuatan user baru di production memakai configured password
            $operator->delete();
            $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
            $recreated = User::where('email', 'operator@tarkid.garutkab.go.id')->firstOrFail();
            $this->assertTrue(Hash::check('ProdInitialPass#2026', $recreated->password));
        } finally {
            app()->detectEnvironment(fn () => $originalEnv);
        }
    }
}
