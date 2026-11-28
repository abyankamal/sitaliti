<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PegawaiNonAsnTest extends TestCase
{
    use RefreshDatabase;

    private function createOperator(): User
    {
        $unit = UnitKerja::create([
            'kode_unit' => '7.01.0.00.0.00.41.0000',
            'nama_unit' => 'Kecamatan Tarogong Kidul',
            'tipe' => 'KECAMATAN',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role' => 'BP',
            'unit_kerja_id' => $unit->id,
        ]);
    }

    public function test_can_create_multiple_non_asn_pegawai_without_nip(): void
    {
        $user = $this->createOperator();

        $this->actingAs($user)
            ->postJson('/api/v1/pegawai', [
                'status_kepegawaian' => 'NON_ASN',
                'nip' => '',
                'nik' => '3205010101950001',
                'nama' => 'Teguh Rahayu',
                'jabatan' => 'Pelaksana',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status_kepegawaian', 'NON_ASN')
            ->assertJsonPath('data.nip', null);

        $this->actingAs($user)
            ->postJson('/api/v1/pegawai', [
                'status_kepegawaian' => 'NON_ASN',
                'nip' => '-',
                'nama' => 'Dedi Kurniawan',
                'jabatan' => 'Tenaga Teknis',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status_kepegawaian', 'NON_ASN')
            ->assertJsonPath('data.nip', null);
    }

    public function test_asn_and_pppk_still_require_nip(): void
    {
        $user = $this->createOperator();

        $this->actingAs($user)
            ->postJson('/api/v1/pegawai', [
                'status_kepegawaian' => 'ASN',
                'nip' => '',
                'nama' => 'Siti Supiah',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nip']);

        $this->actingAs($user)
            ->postJson('/api/v1/pegawai', [
                'status_kepegawaian' => 'PPPK',
                'nip' => '',
                'nama' => 'Rahmat Rahayu',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nip']);
    }

    public function test_can_update_non_asn_without_nip_and_fetch_options(): void
    {
        $user = $this->createOperator();

        $pegawai = Pegawai::create([
            'unit_kerja_id' => $user->unit_kerja_id,
            'status_kepegawaian' => 'NON_ASN',
            'nip' => null,
            'nama' => 'Teguh Rahayu',
            'jabatan' => 'Pelaksana',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/pegawai/{$pegawai->id}", [
                'status_kepegawaian' => 'NON_ASN',
                'nip' => null,
                'nama' => 'Teguh Rahayu Updated',
                'jabatan' => 'Staf Teknis',
            ])
            ->assertOk()
            ->assertJsonPath('data.nama', 'Teguh Rahayu Updated');

        $this->actingAs($user)
            ->getJson('/api/v1/pegawai/options')
            ->assertOk()
            ->assertJsonPath('data.0.status_kepegawaian', 'NON_ASN');
    }
}
