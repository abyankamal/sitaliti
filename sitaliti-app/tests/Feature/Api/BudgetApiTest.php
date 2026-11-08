<?php

namespace Tests\Feature\Api;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UnitKerja $unit;

    private AnggaranRekening $rekening;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->unit = UnitKerja::first();

        $this->user = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
        ]);

        $this->rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Penyusunan Perencanaan Program',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja ATK Kantor',
            'pagu_murni' => 24000000.00,
            'pagu_perubahan' => 24000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        for ($m = 1; $m <= 12; $m++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $this->rekening->id,
                'bulan' => $m,
                'alokasi_pagu' => 2000000.00,
                'terkunci_bulan' => ($m === 2) ? 500000.00 : 0.00,
                'realisasi_bulan' => ($m === 1) ? 1000000.00 : 0.00,
            ]);
        }
    }

    /**
     * Test GET /api/v1/budget/units
     */
    public function test_get_units_returns_active_units_list(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/budget/units');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(6, $response->json('data'));
    }

    /**
     * Test GET /api/v1/budget/sub-activities
     */
    public function test_get_sub_activities_filters_by_unit(): void
    {
        $response = $this->actingAs($this->user)->getJson("/api/v1/budget/sub-activities?unit_id={$this->unit->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('7.01.01.2.01.0001', $data[0]['code']);
        $this->assertEquals('B.1', $data[0]['category']);
    }

    /**
     * Test GET /api/v1/budget/rak-context/{rekeningId}
     */
    public function test_get_rak_context_returns_monthly_allocation_and_carry_over(): void
    {
        $response = $this->actingAs($this->user)->getJson("/api/v1/budget/rak-context/{$this->rekening->id}?month=2");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'selected_month' => 2,
                    'is_future_month' => false,
                ],
            ]);

        $this->assertGreaterThan(0, $response->json('data.total_tersedia'));
    }

    /**
     * Test GET /api/v1/budget/summary
     */
    public function test_get_budget_summary_returns_aggregated_totals(): void
    {
        // Buat kuitansi untuk menguji penghitungan antrean live
        Kuitansi::create([
            'nomor_kuitansi' => 'KW-SUMM-001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->user->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-15',
            'uraian' => 'Pengadaan Kebutuhan ATK Rapat',
            'nama_penerima' => 'CV Makmur',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        Kuitansi::create([
            'nomor_kuitansi' => 'KW-SUMM-002',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->user->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-16',
            'uraian' => 'Pengadaan Kebutuhan ATK Revisi',
            'nama_penerima' => 'CV Makmur',
            'nominal_bruto' => 300000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 300000.00,
            'status' => 'REVISI_MINOR',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/budget/summary');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_pagu' => 24000000,
                    'total_terkunci' => 500000,
                    'total_realisasi' => 1000000,
                    'pending_verification_count' => 1,
                    'active_revisions_count' => 1,
                    'meta' => [
                        'district' => 'Kecamatan Tarogong Kidul',
                        'total_units' => 6,
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(12, $data['monthly_allocation']);
        $this->assertEquals(1, $data['monthly_allocation'][0]['month']);
        $this->assertEquals('Januari', $data['monthly_allocation'][0]['name']);
        $this->assertEquals(2000000, $data['monthly_allocation'][0]['target']);
        $this->assertEquals(1000000, $data['monthly_allocation'][0]['realisasi']);

        $this->assertCount(6, $data['units']);
        $this->assertEquals($this->unit->id, $data['units'][0]['id']);
        $this->assertEquals(24000000, $data['units'][0]['pagu']);
        $this->assertEquals(1000000, $data['units'][0]['terserap']);
        $this->assertEquals(500000, $data['units'][0]['terkunci']);
        $this->assertArrayHasKey('recent_activities', $data);
    }
}
