<?php

namespace Tests\Feature\Budget;

use App\Models\AnggaranRekening;
use App\Models\MasterRekapBelanjaOperasi;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterRekapBelanjaOperasiCrudTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $kecamatan;

    private UnitKerja $kelurahan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kecamatan = UnitKerja::create([
            'kode_unit' => 'KEC-TK',
            'nama_unit' => 'Kecamatan Tarogong Kidul',
            'tipe' => 'KECAMATAN',
        ]);

        $this->kelurahan = UnitKerja::create([
            'kode_unit' => 'KEL-A',
            'nama_unit' => 'Kelurahan A',
            'tipe' => 'KELURAHAN',
        ]);
    }

    public function test_bp_and_verifikator_can_crud_master_rekap_and_map_exclusively(): void
    {
        $bp = User::factory()->create(['role' => 'BP', 'unit_kerja_id' => $this->kecamatan->id]);
        $verifikator = User::factory()->create(['role' => 'VERIFIKATOR', 'unit_kerja_id' => $this->kecamatan->id]);

        AnggaranRekening::create([
            'unit_kerja_id' => $this->kecamatan->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.01',
            'nama_sub_kegiatan' => 'Sub Kegiatan A',
            'kode_rekening' => '5.1.02.01.01.0001',
            'nama_rekening' => 'Belanja ATK',
            'pagu_murni' => 10000000,
            'pagu_perubahan' => 10000000,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        $createRes = $this->actingAs($bp)->postJson('/api/v1/master/rekap-belanja-operasi', [
            'kode' => 'OP-01',
            'nama' => 'Belanja Barang Pakai Habis',
            'urutan' => 1,
            'is_active' => true,
            'kode_rekening' => ['5.1.02.01.01.0001'],
        ]);

        $createRes->assertCreated()
            ->assertJsonPath('data.kode', 'OP-01')
            ->assertJsonPath('data.rekening_mappings_count', 1);

        $posId = $createRes->json('data.id');

        // VERIFIKATOR dapat memperbarui pos
        $updateRes = $this->actingAs($verifikator)->putJson("/api/v1/master/rekap-belanja-operasi/{$posId}", [
            'kode' => 'OP-01',
            'nama' => 'Belanja Barang & Jasa',
            'urutan' => 2,
            'is_active' => true,
            'kode_rekening' => ['5.1.02.01.01.0001'],
        ]);

        $updateRes->assertOk()
            ->assertJsonPath('data.nama', 'Belanja Barang & Jasa');

        // Kode rekening yang sama tidak boleh dipetakan ke pos lain
        $conflictRes = $this->actingAs($bp)->postJson('/api/v1/master/rekap-belanja-operasi', [
            'kode' => 'OP-02',
            'nama' => 'Pos Lain',
            'kode_rekening' => ['5.1.02.01.01.0001'],
        ]);

        $conflictRes->assertStatus(422)
            ->assertJsonValidationErrors(['kode_rekening']);

        // Hapus pos
        $this->actingAs($bp)
            ->deleteJson("/api/v1/master/rekap-belanja-operasi/{$posId}")
            ->assertOk();

        $this->assertDatabaseMissing('master_rekap_belanja_operasi', ['id' => $posId]);
    }

    public function test_non_kecamatan_and_unauthorized_roles_are_forbidden(): void
    {
        $pos = MasterRekapBelanjaOperasi::create([
            'kode' => 'OP-01',
            'nama' => 'Belanja Operasi',
        ]);

        foreach (['BPP', 'PPTK', 'LURAH', 'KPA'] as $role) {
            $unitId = in_array($role, ['BPP', 'LURAH'], true) ? $this->kelurahan->id : $this->kecamatan->id;
            $user = User::factory()->create(['role' => $role, 'unit_kerja_id' => $unitId]);

            $this->actingAs($user)
                ->getJson('/api/v1/master/rekap-belanja-operasi')
                ->assertForbidden();

            $this->actingAs($user)
                ->postJson('/api/v1/master/rekap-belanja-operasi', [
                    'kode' => 'OP-X',
                    'nama' => 'Test',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->putJson("/api/v1/master/rekap-belanja-operasi/{$pos->id}", [
                    'kode' => 'OP-01',
                    'nama' => 'Edit',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->deleteJson("/api/v1/master/rekap-belanja-operasi/{$pos->id}")
                ->assertForbidden();
        }
    }

    public function test_rejects_non_belanja_operasi_kode_rekening(): void
    {
        $bp = User::factory()->create(['role' => 'BP', 'unit_kerja_id' => $this->kecamatan->id]);

        $this->actingAs($bp)->postJson('/api/v1/master/rekap-belanja-operasi', [
            'kode' => 'OP-ERR',
            'nama' => 'Invalid Prefix',
            'kode_rekening' => ['5.2.02.01.01.0001'],
        ])->assertStatus(422)->assertJsonValidationErrors(['kode_rekening']);

        $this->actingAs($bp)->postJson('/api/v1/master/rekap-belanja-operasi', [
            'kode' => 'OP-ERR2',
            'nama' => 'Invalid 5.10',
            'kode_rekening' => ['5.10.01.01.0001'],
        ])->assertStatus(422)->assertJsonValidationErrors(['kode_rekening']);
    }
}
