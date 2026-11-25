<?php

namespace Tests\Unit\Services;

use App\Models\MasterSbmItem;
use App\Models\PengaturanKepatuhan;
use App\Services\SbmValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SbmValidationTest extends TestCase
{
    use RefreshDatabase;

    private SbmValidationService $sbmService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sbmService = new SbmValidationService;
    }

    public function test_returns_empty_warnings_when_setting_disabled(): void
    {
        MasterSbmItem::create([
            'tahun_anggaran' => 2026,
            'kategori_belanja' => 'B1_ATK',
            'kode_sbm' => 'SBM-ATK-001',
            'uraian_standar' => 'Kertas HVS F4 70gr',
            'satuan' => 'Rim',
            'harga_maksimal' => 65000,
            'is_active' => true,
        ]);

        $warnings = $this->sbmService->checkItems('B1_ATK', [
            ['nama_item' => 'Kertas HVS F4 70gr', 'harga_satuan' => 90000],
        ], 2026);

        $this->assertSame([], $warnings);
    }

    public function test_detects_sbm_warning_when_enabled_and_price_exceeds_ceiling(): void
    {
        PengaturanKepatuhan::setValue('sbm_soft_warning_enabled', true);

        $sbm = MasterSbmItem::create([
            'tahun_anggaran' => 2026,
            'kategori_belanja' => 'B2_MAMIN',
            'kode_sbm' => 'SBM-MAMIN-001',
            'uraian_standar' => 'Nasi Kotak Rapat',
            'satuan' => 'Orang/Kali',
            'harga_maksimal' => 45000,
            'is_active' => true,
        ]);

        // Cocok berdasarkan nama_barang maupun nama_item yang melebihi batas
        $warnings = $this->sbmService->checkItems('B.2', [
            ['nama_barang' => 'Nasi Kotak Rapat Biasa', 'harga_satuan' => 55000],
            ['sbm_item_id' => $sbm->id, 'nama_barang' => 'Nasi Kotak VIP', 'harga_satuan' => 45000],
        ], 2026);

        $this->assertCount(1, $warnings);
        $this->assertEquals('SBM-MAMIN-001', $warnings[0]['kode_sbm']);
        $this->assertEquals(10000.0, $warnings[0]['selisih']);
        $this->assertEquals('Nasi Kotak Rapat Biasa', $warnings[0]['nama_item']);
    }
}
