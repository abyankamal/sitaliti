<?php

namespace Tests\Feature\Security;

use App\Models\PptkSubkegiatanAssignment;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $operatorUser;

    protected UnitKerja $kecamatan;

    protected UnitKerja $pataruman;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->kecamatan = UnitKerja::where('tipe', 'KECAMATAN')->firstOrFail();
        $this->pataruman = UnitKerja::where('nama_unit', 'Kelurahan Pataruman')->firstOrFail();

        $this->adminUser = User::where('email', 'kasubag.keuangan@tarkid.garutkab.go.id')->firstOrFail();
        $this->operatorUser = User::where('email', 'operator@tarkid.garutkab.go.id')->firstOrFail();
    }

    public function test_unauthorized_user_cannot_access_user_management_api(): void
    {
        $response = $this->actingAs($this->operatorUser)
            ->getJson('/api/v1/users');

        $response->assertStatus(403);
    }

    public function test_authorized_admin_can_list_users_with_filters(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/users?unit_kerja_id='.$this->pataruman->id);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        foreach ($data as $item) {
            $this->assertEquals($this->pataruman->id, $item['unit_kerja_id']);
        }
    }

    public function test_admin_can_create_new_lurah_user(): void
    {
        $payload = [
            'name' => 'Lurah Jayawaras Baru',
            'email' => 'lurah.jayawaras.baru@tarkid.garutkab.go.id',
            'password' => 'password123',
            'role' => 'LURAH',
            'unit_kerja_id' => $this->pataruman->id,
            'nip' => '198001012005011005',
            'jabatan' => 'Lurah / KPA',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/users', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Lurah Jayawaras Baru')
            ->assertJsonPath('data.role', 'LURAH');

        $this->assertDatabaseHas('users', [
            'email' => 'lurah.jayawaras.baru@tarkid.garutkab.go.id',
            'role' => 'LURAH',
        ]);
    }

    public function test_admin_can_create_pptk_user_with_subkegiatan_assignments(): void
    {
        $payload = [
            'name' => 'PPTK Baru Pataruman',
            'email' => 'pptk.baru.pataruman@tarkid.garutkab.go.id',
            'password' => 'password123',
            'role' => 'PPTK',
            'unit_kerja_id' => $this->pataruman->id,
            'nip' => '198502022009011002',
            'jabatan' => 'Kasi Ekbang',
            'sub_kegiatan_codes' => ['7.01.03.2.02.0002'],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/users', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $createdUserId = $response->json('data.id');

        $this->assertDatabaseHas('pptk_subkegiatan_assignments', [
            'user_id' => $createdUserId,
            'kode_sub_kegiatan' => '7.01.03.2.02.0002',
            'unit_kerja_id' => $this->pataruman->id,
        ]);
    }

    public function test_admin_can_update_user_and_reassign_subkegiatan(): void
    {
        $pptk = User::where('email', 'pptk.sarpras.pataruman@tarkid.garutkab.go.id')->firstOrFail();

        $payload = [
            'name' => 'PPTK Sarpras Pataruman Diperbarui',
            'email' => $pptk->email,
            'role' => 'PPTK',
            'unit_kerja_id' => $this->pataruman->id,
            'nip' => '198000000000000000',
            'jabatan' => 'Kasi Pembangunan',
            'sub_kegiatan_codes' => ['7.01.03.2.02.0002', '7.01.03.2.02.0003'],
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson('/api/v1/users/'.$pptk->id, $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'PPTK Sarpras Pataruman Diperbarui');

        $assignments = PptkSubkegiatanAssignment::where('user_id', $pptk->id)->pluck('kode_sub_kegiatan')->toArray();
        $this->assertEqualsCanonicalizing(['7.01.03.2.02.0002', '7.01.03.2.02.0003'], $assignments);
    }

    public function test_user_cannot_delete_self(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->deleteJson('/api/v1/users/'.$this->adminUser->id);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_delete_user_without_receipts(): void
    {
        $newUser = User::create([
            'name' => 'Staf Magang',
            'email' => 'magang@tarkid.garutkab.go.id',
            'password' => bcrypt('password'),
            'role' => 'OPERATOR',
            'unit_kerja_id' => $this->pataruman->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson('/api/v1/users/'.$newUser->id);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', [
            'id' => $newUser->id,
        ]);
    }

    public function test_kelurahan_scoped_user_is_forbidden_from_users_web_and_api(): void
    {
        $kelurahanAdmin = User::factory()->create([
            'unit_kerja_id' => $this->pataruman->id,
            'role' => 'VERIFIKATOR',
        ]);

        $this->actingAs($kelurahanAdmin)->get('/users')->assertStatus(403);
        $this->actingAs($kelurahanAdmin)->getJson('/api/v1/users')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
        $this->actingAs($kelurahanAdmin)->getJson('/api/v1/users/sub-activities-options')->assertStatus(403);
        $this->actingAs($kelurahanAdmin)->postJson('/api/v1/users', [
            'name' => 'Test',
            'email' => 'blocked@tarkid.garutkab.go.id',
            'password' => 'password123',
            'role' => 'OPERATOR',
            'unit_kerja_id' => $this->pataruman->id,
        ])->assertStatus(403);
        $this->actingAs($kelurahanAdmin)->putJson('/api/v1/users/'.$this->operatorUser->id, [
            'name' => 'Test',
            'email' => $this->operatorUser->email,
            'role' => 'OPERATOR',
            'unit_kerja_id' => $this->pataruman->id,
        ])->assertStatus(403);
        $this->actingAs($kelurahanAdmin)->deleteJson('/api/v1/users/'.$this->operatorUser->id)->assertStatus(403);
    }

    public function test_role_unit_compatibility_and_active_unit_validation(): void
    {
        $inactiveUnit = UnitKerja::create([
            'kode_unit' => '7.01.0.00.0.00.41.9998',
            'nama_unit' => 'Unit Nonaktif',
            'tipe' => 'KELURAHAN',
            'is_active' => false,
        ]);

        // Inactive unit ditolak 422
        $this->actingAs($this->adminUser)->postJson('/api/v1/users', [
            'name' => 'User Nonaktif',
            'email' => 'inactive@tarkid.garutkab.go.id',
            'password' => 'password123',
            'role' => 'OPERATOR',
            'unit_kerja_id' => $inactiveUnit->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['unit_kerja_id']);

        // VERIFIKATOR/KPA di KELURAHAN ditolak 422
        foreach (['VERIFIKATOR', 'KPA'] as $skpdRole) {
            $this->actingAs($this->adminUser)->postJson('/api/v1/users', [
                'name' => "Salah {$skpdRole}",
                'email' => strtolower($skpdRole).'.kel@tarkid.garutkab.go.id',
                'password' => 'password123',
                'role' => $skpdRole,
                'unit_kerja_id' => $this->pataruman->id,
            ])->assertStatus(422)->assertJsonValidationErrors(['role']);
        }

        // LURAH di KECAMATAN ditolak 422
        $this->actingAs($this->adminUser)->postJson('/api/v1/users', [
            'name' => 'Lurah Salah Unit',
            'email' => 'lurah.kec@tarkid.garutkab.go.id',
            'password' => 'password123',
            'role' => 'LURAH',
            'unit_kerja_id' => $this->kecamatan->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['role']);

        // Update ke kombinasi tidak valid juga ditolak 422
        $this->actingAs($this->adminUser)->putJson('/api/v1/users/'.$this->operatorUser->id, [
            'name' => $this->operatorUser->name,
            'email' => $this->operatorUser->email,
            'role' => 'LURAH',
            'unit_kerja_id' => $this->kecamatan->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['role']);
    }
}
