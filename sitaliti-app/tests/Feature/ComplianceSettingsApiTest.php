<?php

namespace Tests\Feature;

use App\Models\PengaturanKepatuhan;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComplianceSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $verifikator;

    private User $bp;

    private User $bpp;

    private User $lurah;

    protected function setUp(): void
    {
        parent::setUp();

        $kecamatan = UnitKerja::create([
            'kode_unit' => 'KEC-TK',
            'nama_unit' => 'Kecamatan Tarogong Kidul',
            'tipe' => 'KECAMATAN',
        ]);

        $kelurahan = UnitKerja::create([
            'kode_unit' => 'KEL-PTR',
            'nama_unit' => 'Kelurahan Pataruman',
            'tipe' => 'KELURAHAN',
        ]);

        foreach (User::defaultRolePermissions() as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            foreach ($perms as $permName) {
                Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
            }
            $role->syncPermissions($perms);
        }

        $this->verifikator = User::factory()->create(['role' => 'VERIFIKATOR', 'unit_kerja_id' => $kecamatan->id]);
        $this->bp = User::factory()->create(['role' => 'BP', 'unit_kerja_id' => $kecamatan->id]);
        $this->bpp = User::factory()->create(['role' => 'BPP', 'unit_kerja_id' => $kelurahan->id]);
        $this->lurah = User::factory()->create(['role' => 'LURAH', 'unit_kerja_id' => $kelurahan->id]);
    }

    public function test_bpp_and_lurah_are_forbidden_from_compliance_endpoints(): void
    {
        foreach ([$this->bpp, $this->lurah] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->getJson('/api/v1/compliance')
                ->assertStatus(403);

            $this->actingAs($unauthorizedUser)
                ->postJson('/api/v1/compliance/settings', [
                    'key' => 'ntpn_gate_spp_gu',
                    'value' => false,
                ])
                ->assertStatus(403);
        }
    }

    public function test_verifikator_and_bp_can_manage_compliance_settings_tax_rates_and_sbm(): void
    {
        $this->actingAs($this->verifikator)
            ->postJson('/api/v1/compliance/settings', [
                'key' => 'ntpn_gate_spp_gu',
                'value' => true,
            ])
            ->assertOk();

        $this->assertTrue(PengaturanKepatuhan::isEnabled('ntpn_gate_spp_gu', false));

        $this->actingAs($this->bp)
            ->postJson('/api/v1/compliance/tax-rates', [
                'tahun_anggaran' => 2026,
                'kode_pajak' => 'PPN_11',
                'nama_pajak' => 'PPN 11%',
                'tarif' => 0.11,
                'ambang_batas' => 2000000,
            ])
            ->assertOk();

        $this->actingAs($this->verifikator)
            ->postJson('/api/v1/compliance/sbm-items', [
                'tahun_anggaran' => 2026,
                'kategori_belanja' => 'B1_ATK',
                'kode_sbm' => 'SBM-ATK-TEST',
                'uraian_standar' => 'Kertas HVS A4',
                'satuan' => 'Rim',
                'harga_maksimal' => 65000,
            ])
            ->assertOk();

        $this->actingAs($this->verifikator)
            ->getJson('/api/v1/compliance?tahun_anggaran=2026')
            ->assertOk()
            ->assertJsonPath('data.tahun_anggaran', 2026);
    }
}
