<?php

namespace Tests\Feature\Security;

use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\MasterChecklistSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlSheetAccessTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $kelurahan;

    private UnitKerja $otherKelurahan;

    private AnggaranRekening $rekening;

    private User $operator;

    private User $otherUnitOperator;

    private User $pptk;

    private User $lurah;

    private User $bpp;

    private User $verifikator;

    private User $kpa;

    private Kuitansi $kuitansi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterChecklistSeeder::class);

        $this->kelurahan = UnitKerja::create([
            'kode_unit' => '7.01.01',
            'nama_unit' => 'Kelurahan Sukagalih',
            'tipe' => 'KELURAHAN',
            'is_active' => true,
        ]);

        $this->otherKelurahan = UnitKerja::create([
            'kode_unit' => '7.01.02',
            'nama_unit' => 'Kelurahan Pataruman',
            'tipe' => 'KELURAHAN',
            'is_active' => true,
        ]);

        $this->rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->kelurahan->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Administrasi Umum',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja ATK',
            'pagu_murni' => 50000000.00,
            'pagu_perubahan' => 50000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        $this->operator = User::factory()->create(['unit_kerja_id' => $this->kelurahan->id, 'role' => 'OPERATOR']);
        $this->otherUnitOperator = User::factory()->create(['unit_kerja_id' => $this->otherKelurahan->id, 'role' => 'OPERATOR']);
        $this->pptk = User::factory()->create(['unit_kerja_id' => $this->kelurahan->id, 'role' => 'PPTK']);
        $this->lurah = User::factory()->create(['unit_kerja_id' => $this->kelurahan->id, 'role' => 'LURAH']);
        $this->bpp = User::factory()->create(['unit_kerja_id' => $this->kelurahan->id, 'role' => 'BPP']);
        $this->verifikator = User::factory()->create(['unit_kerja_id' => $this->kelurahan->id, 'role' => 'VERIFIKATOR']);
        $this->kpa = User::factory()->create(['unit_kerja_id' => $this->kelurahan->id, 'role' => 'KPA']);

        $this->kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-CS-0001',
            'unit_kerja_id' => $this->kelurahan->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'verifikator_id' => $this->verifikator->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-15',
            'uraian' => 'Belanja ATK Lembar Kendali',
            'nama_penerima' => 'Toko ATK',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'LOLOS_VERIFIKASI',
            'qr_token' => 'qr-token-uuid-001',
            'tanggal_verifikasi' => now(),
        ]);
    }

    public function test_control_sheet_accessible_by_authorized_roles_and_returns_real_verifikator(): void
    {
        foreach ([$this->operator, $this->pptk, $this->lurah, $this->bpp, $this->verifikator, $this->kpa] as $user) {
            $res = $this->actingAs($user)->getJson("/api/v1/control-sheet/{$this->kuitansi->id}");
            $res->assertStatus(200)
                ->assertJsonPath('data.qr_token', 'qr-token-uuid-001')
                ->assertJsonPath('data.verifikator.id', $this->verifikator->id)
                ->assertJsonPath('data.verifikator.name', $this->verifikator->name);

            $this->assertStringContainsString('/verify/qr-token-uuid-001', (string) $res->json('data.verification_url'));
        }

        // Operator unit lain ditolak 403
        $this->actingAs($this->otherUnitOperator)
            ->getJson("/api/v1/control-sheet/{$this->kuitansi->id}")
            ->assertStatus(403);
    }

    public function test_master_checklist_endpoint_returns_active_items_ordered_by_urutan(): void
    {
        $resDot = $this->actingAs($this->operator)->getJson('/api/v1/master/checklist/B.1');
        $resDot->assertStatus(200);
        $itemsDot = $resDot->json('data');
        $this->assertNotEmpty($itemsDot);

        $urutans = array_column($itemsDot, 'urutan');
        $sorted = $urutans;
        sort($sorted);
        $this->assertEquals($sorted, $urutans);

        $resCode = $this->actingAs($this->operator)->getJson('/api/v1/master/checklist/B1_ATK');
        $resCode->assertStatus(200);
        $this->assertCount(count($itemsDot), $resCode->json('data'));
    }
}
