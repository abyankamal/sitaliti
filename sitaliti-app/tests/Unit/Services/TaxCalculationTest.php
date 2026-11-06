<?php

namespace Tests\Unit\Services;

use App\Contracts\TaxCalculationServiceInterface;
use Tests\TestCase;

class TaxCalculationTest extends TestCase
{
    private TaxCalculationServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Resolusi instance service dari container atau instansiasi langsung
        $this->service = app(TaxCalculationServiceInterface::class);
    }

    /**
     * Test 1: Belanja ATK (B.1) <= Rp 2.000.000 bebas pemungutan PPN dan PPh 22
     * Sesuai PMK No. 59/PMK.03/2022 Pasal 18 ayat (1) huruf a:
     * Bendahara pemerintah dikecualikan dari pemungutan PPN dan PPh 22 untuk pembayaran <= Rp 2.000.000.
     */
    public function test_atk_di_bawah_atau_sama_dengan_2_juta_bebas_pungut_ppn_dan_pph22_pmk59(): void
    {
        $bruto = 1500000.0;
        $result = $this->service->calculateTax('B1_ATK', $bruto, ['has_npwp' => true]);

        $this->assertEquals($bruto, $result['nilai_bruto']);
        $this->assertEquals(0.0, $result['ppn'], 'PPN harus 0 jika <= 2 juta (PMK 59/2022)');
        $this->assertEquals(0.0, $result['pph22'], 'PPh 22 harus 0 jika <= 2 juta (PMK 59/2022)');
        $this->assertEquals(0.0, $result['pbjt_mamin']);
        $this->assertEquals(0.0, $result['bea_meterai']);
        $this->assertEquals(0.0, $result['total_pajak']);
        $this->assertEquals($bruto, $result['nilai_neto'], 'Neto harus sama persis dengan Bruto');
    }

    /**
     * Test 2: Belanja ATK (B.1) tepat batas Rp 2.000.000 bebas pemungutan
     */
    public function test_atk_tepat_batas_2_juta_bebas_pungut_ppn_dan_pph22(): void
    {
        $bruto = 2000000.0;
        $result = $this->service->calculateTax('B1_ATK', $bruto, ['has_npwp' => true]);

        $this->assertEquals(0.0, $result['ppn']);
        $this->assertEquals(0.0, $result['pph22']);
        $this->assertEquals(0.0, $result['total_pajak']);
        $this->assertEquals($bruto, $result['nilai_neto']);
    }

    /**
     * Test 3: Belanja ATK (B.1) > Rp 2.000.000 dengan NPWP (Bebas Drift Pembulatan Rp 1)
     * Kasus: Bruto Rp 2.220.000
     * DPP = round(2.220.000 / 1.11) = 2.000.000
     * PPN = 2.220.000 - 2.000.000 = 220.000
     * PPh 22 (1.5% dari DPP) = 1.5% * 2.000.000 = 30.000
     * Total Pajak = 220.000 + 30.000 = 250.000
     * Neto = 2.220.000 - 250.000 = 1.970.000
     */
    public function test_atk_di_atas_2_juta_hitung_dpp_ppn_dan_pph22_tanpa_drift_rp1(): void
    {
        $bruto = 2220000.0;
        $result = $this->service->calculateTax('B1_ATK', $bruto, ['has_npwp' => true]);

        $this->assertEquals(2000000.0, $result['dpp']);
        $this->assertEquals(220000.0, $result['ppn']);
        $this->assertEquals(30000.0, $result['pph22']);
        $this->assertEquals(0.0, $result['pbjt_mamin']);
        $this->assertEquals(0.0, $result['bea_meterai']);
        $this->assertEquals(250000.0, $result['total_pajak']);
        $this->assertEquals(1970000.0, $result['nilai_neto']);

        // Invarian pembulatan
        $this->assertEquals($bruto, $result['dpp'] + $result['ppn'], 'DPP + PPN wajib sama persis dengan Bruto');
    }

    /**
     * Test 4: Belanja ATK (B.1) tanpa NPWP dikenakan penalti PPh 22 sebesar 100% (tarif menjadi 3.0%)
     * Sesuai UU PPh / UU HPP: Wajib Pajak yang tidak memiliki NPWP dikenakan tarif 100% lebih tinggi (1.5% * 2 = 3.0%).
     * Bruto: Rp 2.220.000 -> DPP = Rp 2.000.000
     * PPN = Rp 220.000
     * PPh 22 (3.0% dari DPP) = 3.0% * 2.000.000 = 60.000
     * Total Pajak = 220.000 + 60.000 = 280.000
     * Neto = 2.220.000 - 280.000 = 1.940.000
     */
    public function test_atk_tanpa_npwp_dikenakan_penalti_pph22_100_persen(): void
    {
        $bruto = 2220000.0;
        $result = $this->service->calculateTax('B1_ATK', $bruto, ['has_npwp' => false]);

        $this->assertEquals(2000000.0, $result['dpp']);
        $this->assertEquals(220000.0, $result['ppn']);
        $this->assertEquals(60000.0, $result['pph22'], 'Tarif PPh 22 non-NPWP harus 3.0% (penalti 100%)');
        $this->assertEquals(280000.0, $result['total_pajak']);
        $this->assertEquals(1940000.0, $result['nilai_neto']);
    }

    /**
     * Test 5: Belanja Mamin (B.2) hanya dikenakan PBJT 10% dan MUTLAK bebas PPN pusat (UU HKPD No. 1/2022)
     * Berdasarkan UU HKPD No. 1/2022 dan Perda Kab. Garut No. 1/2024:
     * Objek PBJT Mamin (restoran/katering) dipungut Pemda 10% dan bukan objek PPN pusat.
     * Bruto: Rp 1.100.000
     * DPP = round(1.100.000 / 1.10) = 1.000.000
     * PBJT = 1.100.000 - 1.000.000 = 100.000
     * PPN = 0
     * Total Pajak = 100.000
     * Neto = 1.000.000
     */
    public function test_mamin_hanya_kena_pbjt_10_persen_dan_bebas_ppn(): void
    {
        $bruto = 1100000.0;
        $result = $this->service->calculateTax('B2_MAMIN', $bruto);

        $this->assertEquals(1000000.0, $result['dpp']);
        $this->assertEquals(0.0, $result['ppn'], 'Mamin mutlak bebas PPN pusat 11% (UU HKPD)');
        $this->assertEquals(100000.0, $result['pbjt_mamin'], 'PBJT Mamin Pemda Garut adalah 10%');
        $this->assertEquals(0.0, $result['pph22']);
        $this->assertEquals(100000.0, $result['total_pajak']);
        $this->assertEquals(1000000.0, $result['nilai_neto']);
    }

    /**
     * Test 6: Honorarium (B.3) PPh 21 berbasis Golongan PNS (PP 80/2010)
     * DPP = 100% Bruto (tidak dibagi 1.11, bebas PPN).
     * - Gol I & II: 0%
     * - Gol III: 5%
     * - Gol IV: 15%
     * - Non-ASN: 5%
     */
    public function test_honorarium_pph21_golongan_i_ii_iii_iv(): void
    {
        $bruto = 2000000.0;

        // Golongan I / II (0%)
        $resGol2 = $this->service->calculateTax('B3_HONOR', $bruto, ['golongan_asn' => 'GOLONGAN_II']);
        $this->assertEquals(0.0, $resGol2['pph21']);
        $this->assertEquals($bruto, $resGol2['nilai_neto']);

        // Golongan III (5%)
        $resGol3 = $this->service->calculateTax('B3_HONOR', $bruto, ['golongan_asn' => 'GOLONGAN_III']);
        $this->assertEquals(100000.0, $resGol3['pph21']); // 5% * 2.000.000
        $this->assertEquals(1900000.0, $resGol3['nilai_neto']);

        // Golongan IV (15%)
        $resGol4 = $this->service->calculateTax('B3_HONOR', $bruto, ['golongan_asn' => 'GOLONGAN_IV']);
        $this->assertEquals(300000.0, $resGol4['pph21']); // 15% * 2.000.000
        $this->assertEquals(1700000.0, $resGol4['nilai_neto']);

        // Non-ASN (5%)
        $resNonAsn = $this->service->calculateTax('B3_HONOR', $bruto, ['golongan_asn' => 'NON_ASN']);
        $this->assertEquals(100000.0, $resNonAsn['pph21']);
        $this->assertEquals(1900000.0, $resNonAsn['nilai_neto']);
    }

    /**
     * Test 7: Perjadin (B.4) Sewa Kendaraan dikenakan PPh 23 (2.0% jika NPWP, 4.0% non-NPWP)
     */
    public function test_perjadin_sewa_kendaraan_kena_pph23(): void
    {
        $bruto = 1000000.0;

        // Dengan NPWP (2%)
        $resNpwp = $this->service->calculateTax('B4_PERJADIN', $bruto, ['has_npwp' => true]);
        $this->assertEquals(20000.0, $resNpwp['pph23']);
        $this->assertEquals(980000.0, $resNpwp['nilai_neto']);

        // Tanpa NPWP (4% - surcharge 100%)
        $resNonNpwp = $this->service->calculateTax('B4_PERJADIN', $bruto, ['has_npwp' => false]);
        $this->assertEquals(40000.0, $resNonNpwp['pph23']);
        $this->assertEquals(960000.0, $resNonNpwp['nilai_neto']);
    }

    /**
     * Test 8: Bea Meterai Rp 10.000 hanya wajib pada transaksi > Rp 5.000.000 (UU No. 10/2020)
     * Dan Bea Meterai TIDAK mengurangi nilai neto pembayaran ke rekanan.
     */
    public function test_bea_meterai_hanya_aktif_di_atas_5_juta_dan_tidak_potong_neto(): void
    {
        // Transaksi tepat 5 juta -> bebas meterai
        $res5Juta = $this->service->calculateTax('B1_ATK', 5000000.0, ['has_npwp' => true]);
        $this->assertEquals(0.0, $res5Juta['bea_meterai']);

        // Transaksi 6 juta -> wajib meterai Rp 10.000
        $res6Juta = $this->service->calculateTax('B1_ATK', 6000000.0, ['has_npwp' => true]);
        $this->assertEquals(10000.0, $res66Juta['bea_meterai'] ?? $res6Juta['bea_meterai']);
        
        // Memastikan bea meterai tidak memotong neto pembayaran
        $dpp = round(6000000.0 / 1.11);
        $ppn = 6000000.0 - $dpp;
        $pph22 = round(0.015 * $dpp);
        $expectedTotalPajak = $ppn + $pph22;
        $expectedNeto = 6000000.0 - $expectedTotalPajak;

        $this->assertEquals($expectedTotalPajak, $res6Juta['total_pajak']);
        $this->assertEquals($expectedNeto, $res6Juta['nilai_neto'], 'Neto tidak boleh dipotong bea meterai');
    }

    /**
     * Test 9: Validasi Kepatuhan Pajak (validateTaxCompliance)
     * - Keseimbangan matematis Neto = Bruto - Total Pajak
     * - Tidak boleh ada PPN dan PBJT secara bersamaan
     */
    public function test_validasi_kepatuhan_pajak_berhasil(): void
    {
        $res = $this->service->calculateTax('B1_ATK', 2220000.0, ['has_npwp' => true]);
        $isValid = $this->service->validateTaxCompliance(2220000.0, $res);

        $this->assertTrue($isValid);
    }

    /**
     * Test 10: Invarian matematis fundamental pada beragam nominal acak
     */
    public function test_invarian_keseimbangan_matematis_bruto_kurang_total_pajak_sama_dengan_neto(): void
    {
        $testCases = [
            ['B1_ATK', 750000.0, ['has_npwp' => true]],
            ['B1_ATK', 3450000.0, ['has_npwp' => false]],
            ['B2_MAMIN', 1850000.0, []],
            ['B3_HONOR', 4500000.0, ['golongan_asn' => 'GOLONGAN_IV']],
            ['B4_PERJADIN', 2500000.0, ['has_npwp' => false]],
        ];

        foreach ($testCases as [$kategori, $bruto, $opts]) {
            $result = $this->service->calculateTax($kategori, $bruto, $opts);

            $this->assertEquals(
                $bruto,
                round($result['nilai_neto'] + $result['total_pajak'], 2),
                "Pelanggaran invarian: Bruto != Neto + Total Pajak pada kategori $kategori, bruto $bruto"
            );
        }
    }
}

