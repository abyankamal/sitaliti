<?php

namespace Tests\Feature\Budget;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\UnitKerja;
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
}
