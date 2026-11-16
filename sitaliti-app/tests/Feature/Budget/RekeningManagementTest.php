<?php

namespace Tests\Feature\Budget;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\BudgetManagementService;
use App\Services\BudgetReservationService;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class RekeningManagementTest extends TestCase
{
    use RefreshDatabase;

    private BudgetManagementService $service;

    private UnitKerja $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->unit = UnitKerja::firstOrFail();
        $this->service = app(BudgetManagementService::class);
    }

    public function test_create_rekening_with_zero_indexed_list_creates_12_months_and_integrates_with_reservation(): void
    {
        // Array 0..11 (format list JSON standar dari JavaScript)
        $alokasiList = [
            1000000, 2000000, 0, 1500000, 0, 0,
            0, 0, 0, 0, 0, 500000,
        ];

        $rekening = $this->service->createRekening([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Penyusunan Dokumen Perencanaan',
            'kode_rekening' => '5.1.02.01.01.0099',
            'nama_rekening' => 'Belanja Alat Tulis Kantor Tambahan',
            'kategori_belanja' => 'B.1',
            'alokasi' => $alokasiList,
        ]);

        $this->assertEquals(5000000.00, (float) $rekening->pagu_murni);
        $this->assertEquals(5000000.00, (float) $rekening->pagu_perubahan);
        $this->assertTrue($rekening->is_leaf);
        $this->assertCount(12, $rekening->kasBulanan);

        // Pastikan bulan 1 = 1jt, bulan 2 = 2jt, bulan 12 = 500rb (tidak tergeser!)
        $byMonth = $rekening->kasBulanan->keyBy('bulan');
        $this->assertEquals(1000000.00, (float) $byMonth[1]->alokasi_pagu);
        $this->assertEquals(2000000.00, (float) $byMonth[2]->alokasi_pagu);
        $this->assertEquals(500000.00, (float) $byMonth[12]->alokasi_pagu);

        // Integrasi langsung dengan BudgetReservationService (popup & reserve)
        $reservationService = app(BudgetReservationService::class);
        $popup = $reservationService->getPopupAllocationData((string) $rekening->id, 4, 4);
        $this->assertEquals(1500000.00, $popup['pagu_murni_bulan']);
        $this->assertEquals(3000000.00, $popup['carry_over_lalu']);

        $reservationService->reserveBudget((string) $rekening->id, 4, 500000.00, 4);
        $this->assertEquals(500000.00, (float) $byMonth[4]->fresh()->terkunci_bulan);
    }

    public function test_create_rekening_with_one_indexed_map_and_reuses_existing_subkegiatan_name(): void
    {
        AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0002',
            'nama_sub_kegiatan' => 'Nama Subkegiatan Resmi Eksisting',
            'kode_rekening' => '5.1.02.01.01.0001',
            'nama_rekening' => 'Rekening Awal',
            'pagu_murni' => 1000000.00,
            'pagu_perubahan' => 1000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        $alokasiMap = array_fill(1, 12, 100000);

        $rekening = $this->service->createRekening([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0002',
            'nama_sub_kegiatan' => 'Nama Berbeda Yang Harus Diabaikan',
            'kode_rekening' => '5.1.02.01.01.0002',
            'nama_rekening' => 'Rekening Kedua',
            'kategori_belanja' => 'B.2',
            'alokasi' => $alokasiMap,
        ]);

        $this->assertEquals('Nama Subkegiatan Resmi Eksisting', $rekening->nama_sub_kegiatan);
        $this->assertEquals(1200000.00, (float) $rekening->pagu_perubahan);
    }

    public function test_duplicate_rekening_throws_validation_exception(): void
    {
        $payload = [
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0003',
            'nama_sub_kegiatan' => 'Subkegiatan Duplikat',
            'kode_rekening' => '5.1.02.01.01.0010',
            'nama_rekening' => 'Belanja Kertas',
            'kategori_belanja' => 'B.1',
            'alokasi' => array_fill(0, 12, 100000),
        ];

        $this->service->createRekening($payload);

        $this->expectException(ValidationException::class);
        $this->service->createRekening($payload);
    }

    public function test_normalize_allocations_rejects_invalid_keys_zero_total_and_decimal_overflow(): void
    {
        // Key acak (1..11 + 99) harus ditolak
        $invalidKeys = array_fill(1, 11, 100000);
        $invalidKeys[99] = 100000;

        try {
            $this->service->normalizeAllocations($invalidKeys);
            $this->fail('Seharusnya melempar ValidationException untuk key 99.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('alokasi', $e->errors());
        }

        // Total 0 harus ditolak
        try {
            $this->service->normalizeAllocations(array_fill(0, 12, 0));
            $this->fail('Seharusnya melempar ValidationException untuk total 0.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('alokasi', $e->errors());
        }

        // Total melebihi decimal(15,2) harus ditolak
        try {
            $this->service->normalizeAllocations(array_fill(0, 12, 1000000000000.00));
            $this->fail('Seharusnya melempar ValidationException untuk overflow decimal(15,2).');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('alokasi', $e->errors());
        }
    }

    public function test_atomic_rollback_leaves_no_orphan_rekening_when_monthly_insert_fails(): void
    {
        AnggaranKasBulanan::creating(function (AnggaranKasBulanan $kas) {
            if ($kas->bulan === 5) {
                throw new RuntimeException('Simulated failure on month 5');
            }
        });

        try {
            $this->service->createRekening([
                'unit_kerja_id' => $this->unit->id,
                'kode_sub_kegiatan' => '7.01.01.2.01.0009',
                'nama_sub_kegiatan' => 'Subkegiatan Rollback',
                'kode_rekening' => '5.1.02.01.01.0077',
                'nama_rekening' => 'Rekening Rollback',
                'kategori_belanja' => 'B.1',
                'alokasi' => array_fill(0, 12, 100000),
            ]);
            $this->fail('Seharusnya gagal karena simulasi error bulan 5.');
        } catch (RuntimeException) {
            // Expected
        } finally {
            AnggaranKasBulanan::flushEventListeners();
        }

        $this->assertDatabaseMissing('anggaran_rekening', [
            'kode_rekening' => '5.1.02.01.01.0077',
        ]);
        $this->assertSame(0, AnggaranKasBulanan::count());
    }

    public function test_api_skpd_admin_can_create_list_and_search_rekening(): void
    {
        $kecamatan = UnitKerja::where('tipe', 'KECAMATAN')->firstOrFail();
        $verifikator = User::factory()->create([
            'unit_kerja_id' => $kecamatan->id,
            'role' => 'VERIFIKATOR',
        ]);

        $payload = [
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0005',
            'nama_sub_kegiatan' => 'Koordinasi Tata Pemerintahan',
            'kode_rekening' => '5.1.02.01.01.0088',
            'nama_rekening' => 'Belanja Cetak Surat',
            'kategori_belanja' => 'B.1',
            'alokasi' => array_fill(0, 12, 250000),
        ];

        $storeRes = $this->actingAs($verifikator)
            ->postJson('/api/v1/budget-management/rekening', $payload);

        $storeRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.kode_rekening', '5.1.02.01.01.0088')
            ->assertJsonPath('data.total_alokasi', 3000000);

        $this->assertIsFloat($storeRes->json('data.pagu_perubahan') + 0.0);
        $this->assertCount(12, $storeRes->json('data.kas_bulanan'));

        $listRes = $this->actingAs($verifikator)
            ->getJson('/api/v1/budget-management/rekening?q=Cetak Surat');

        $listRes->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.kode_rekening', '5.1.02.01.01.0088');

        $subRes = $this->actingAs($verifikator)
            ->getJson('/api/v1/budget-management/sub-activities?unit_kerja_id='.$this->unit->id);

        $subRes->assertOk()
            ->assertJsonPath('data.0.kode_sub_kegiatan', '7.01.01.2.01.0005');
    }

    public function test_api_validation_rejects_bad_formats_inactive_unit_and_overflow(): void
    {
        $kecamatan = UnitKerja::where('tipe', 'KECAMATAN')->firstOrFail();
        $kpa = User::factory()->create([
            'unit_kerja_id' => $kecamatan->id,
            'role' => 'KPA',
        ]);

        $inactiveUnit = UnitKerja::create([
            'kode_unit' => '7.01.0.00.0.00.41.9999',
            'nama_unit' => 'Unit Nonaktif',
            'tipe' => 'KELURAHAN',
            'is_active' => false,
        ]);

        // Format kode salah, panjang > 50, kategori di luar B.1-B.6, unit nonaktif
        $this->actingAs($kpa)->postJson('/api/v1/budget-management/rekening', [
            'unit_kerja_id' => $inactiveUnit->id,
            'kode_sub_kegiatan' => '9.99.01.2.01.0001',
            'nama_sub_kegiatan' => 'Salah',
            'kode_rekening' => '5.1.02.01.01.'.str_repeat('9', 45),
            'nama_rekening' => 'Terlalu Panjang',
            'kategori_belanja' => 'B.9',
            'alokasi' => array_fill(0, 12, -100),
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'unit_kerja_id',
                'kode_sub_kegiatan',
                'kode_rekening',
                'kategori_belanja',
                'alokasi.0',
            ]);
    }

    public function test_api_rbac_and_kelurahan_scope_enforcement(): void
    {
        $kecamatan = UnitKerja::where('tipe', 'KECAMATAN')->firstOrFail();
        $kelurahan = UnitKerja::where('tipe', 'KELURAHAN')->firstOrFail();

        $validPayload = [
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0006',
            'nama_sub_kegiatan' => 'Subkegiatan RBAC',
            'kode_rekening' => '5.1.02.01.01.0066',
            'nama_rekening' => 'Belanja RBAC',
            'kategori_belanja' => 'B.1',
            'alokasi' => array_fill(0, 12, 100000),
        ];

        // 1. Unauthenticated -> 401
        $this->postJson('/api/v1/budget-management/rekening', $validPayload)->assertStatus(401);

        // 2. Operator / PPTK / BPP -> 403
        foreach (['OPERATOR', 'PPTK', 'BPP'] as $role) {
            $user = User::factory()->create(['unit_kerja_id' => $kecamatan->id, 'role' => $role]);
            $this->actingAs($user)->getJson('/api/v1/budget-management/rekening')->assertStatus(403);
            $this->actingAs($user)->postJson('/api/v1/budget-management/rekening', $validPayload)->assertStatus(403);
        }

        // 3. Kelurahan-scoped user bahkan dengan role VERIFIKATOR/KPA -> 403 di seluruh endpoint
        $kelurahanAdmin = User::factory()->create([
            'unit_kerja_id' => $kelurahan->id,
            'role' => 'VERIFIKATOR',
        ]);
        $this->actingAs($kelurahanAdmin)->getJson('/api/v1/budget-management/rekening')->assertStatus(403);
        $this->actingAs($kelurahanAdmin)->getJson('/api/v1/budget-management/sub-activities')->assertStatus(403);
        $this->actingAs($kelurahanAdmin)->postJson('/api/v1/budget-management/rekening', $validPayload)->assertStatus(403);
        $this->actingAs($kelurahanAdmin)->putJson('/api/v1/budget-management/rekening/1', [
            'alokasi' => array_fill(0, 12, 100000),
        ])->assertStatus(403);
    }

    public function test_api_update_allocations_updates_pagu_perubahan_and_enforces_minimum_locked_realized(): void
    {
        $kecamatan = UnitKerja::where('tipe', 'KECAMATAN')->firstOrFail();
        $verifikator = User::factory()->create([
            'unit_kerja_id' => $kecamatan->id,
            'role' => 'VERIFIKATOR',
        ]);

        $rekening = $this->service->createRekening([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0010',
            'nama_sub_kegiatan' => 'Subkegiatan Update Pagu',
            'kode_rekening' => '5.1.02.01.01.0100',
            'nama_rekening' => 'Belanja Update Pagu',
            'kategori_belanja' => 'B.1',
            'alokasi' => array_fill(0, 12, 1000000),
        ]);

        // Bulan 3 punya terkunci 400.000 + realisasi 200.000 = minimum 600.000
        AnggaranKasBulanan::where('anggaran_rekening_id', $rekening->id)
            ->where('bulan', 3)
            ->update([
                'terkunci_bulan' => 400000.00,
                'realisasi_bulan' => 200000.00,
            ]);

        // Coba turunkan bulan 3 menjadi 500.000 (< 600.000) -> harus ditolak 422
        $tooLow = array_fill(0, 12, 1000000);
        $tooLow[2] = 500000; // index 2 = Bulan 3

        $this->actingAs($verifikator)
            ->putJson("/api/v1/budget-management/rekening/{$rekening->id}", [
                'alokasi' => $tooLow,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['alokasi']);

        // Pastikan pagu_perubahan dan bulan 3 tidak berubah
        $this->assertEquals(12000000.00, (float) $rekening->fresh()->pagu_perubahan);

        // Naikkan bulan 3 menjadi 1.500.000 dan bulan 4 menjadi 2.000.000 -> sukses 200
        $validUpdate = array_fill(0, 12, 1000000);
        $validUpdate[2] = 1500000;
        $validUpdate[3] = 2000000;

        $res = $this->actingAs($verifikator)
            ->putJson("/api/v1/budget-management/rekening/{$rekening->id}", [
                'alokasi' => $validUpdate,
            ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_alokasi', 13500000);

        $rekening->refresh();
        $this->assertEquals(12000000.00, (float) $rekening->pagu_murni);
        $this->assertEquals(13500000.00, (float) $rekening->pagu_perubahan);
    }
}
