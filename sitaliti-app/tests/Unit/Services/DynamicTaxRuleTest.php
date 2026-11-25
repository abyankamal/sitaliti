<?php

namespace Tests\Unit\Services;

use App\Models\MasterTarifPajak;
use App\Services\TaxCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicTaxRuleTest extends TestCase
{
    use RefreshDatabase;

    private TaxCalculationService $taxService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->taxService = new TaxCalculationService;
    }

    public function test_uses_dynamic_tax_rate_for_configured_fiscal_year(): void
    {
        MasterTarifPajak::create([
            'tahun_anggaran' => 2027,
            'kode_pajak' => 'PPN_11',
            'nama_pajak' => 'PPN 12% Tahun 2027',
            'tarif' => 0.1200,
            'ambang_batas' => 2000000.00,
            'is_active' => true,
        ]);

        $result = $this->taxService->calculateTax('B1_ATK', 11200000, [
            'has_npwp' => true,
            'tahun_anggaran' => 2027,
        ]);

        // DPP = 11.200.000 / 1.12 = 10.000.000, PPN = 1.200.000
        $this->assertEquals(10000000.0, $result['dpp']);
        $this->assertEquals(1200000.0, $result['ppn']);
        $this->assertTrue($this->taxService->validateTaxCompliance(11200000, $result));
    }

    public function test_uses_dynamic_pph21_non_asn_rate_and_falls_back_when_year_missing(): void
    {
        MasterTarifPajak::create([
            'tahun_anggaran' => 2027,
            'kode_pajak' => 'PPH21_NON_ASN',
            'nama_pajak' => 'PPh 21 Non-ASN 2027',
            'tarif' => 0.0300,
            'is_active' => true,
        ]);

        $custom = $this->taxService->calculateTax('B3_HONOR', 1000000, [
            'golongan_asn' => 'NON_ASN',
            'tahun_anggaran' => 2027,
        ]);
        $this->assertEquals(30000.0, $custom['pph21']);

        // Tahun tanpa konfigurasi di DB -> fallback aman ke 2.5% (PMK 168/2023)
        $fallback = $this->taxService->calculateTax('B3_HONOR', 1000000, [
            'golongan_asn' => 'NON_ASN',
            'tahun_anggaran' => 2099,
        ]);
        $this->assertEquals(25000.0, $fallback['pph21']);
    }
}
