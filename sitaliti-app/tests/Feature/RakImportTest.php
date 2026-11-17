<?php

namespace Tests\Feature;

use App\Contracts\RakImporterServiceInterface;
use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RakImportTest extends TestCase
{
    use RefreshDatabase;

    private RakImporterServiceInterface $importer;

    private string $rakFilePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = app(RakImporterServiceInterface::class);
        $this->rakFilePath = storage_path('app/rak/RAK_Belanja_7.01.0.00.0.00.41.0000 - Kecamatan Tarogong Kidul.xlsx');
    }

    /**
     * Test validasi format file Excel RAK
     */
    public function test_validasi_format_file_rak(): void
    {
        $this->assertTrue(
            $this->importer->validateFileFormat($this->rakFilePath),
            'Format file XLSX RAK wajib valid'
        );

        $this->assertFalse(
            $this->importer->validateFileFormat('/invalid/path/nonexistent.xlsx'),
            'File tidak ada harus mengembalikan false'
        );
    }

    /**
     * Test eksekusi impor RAK secara komprehensif
     * - Memverifikasi total 132 rekening leaf
     * - Memverifikasi total pagu tepat Rp 14.252.126.458
     * - Memverifikasi alokasi kas bulanan 12 bulan per rekening
     */
    public function test_impor_rak_menghasilkan_132_rekening_dan_pagu_tepat_14_miliar(): void
    {
        // Jalankan seeder unit kerja terlebih dahulu
        $this->seed(UnitKerjaSeeder::class);

        $result = $this->importer->importFromFile($this->rakFilePath, 2026);

        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals(39, $result['total_subkegiatan']);
        $this->assertEquals(132, $result['total_rekening']);
        $this->assertEquals(14252126458.00, $result['total_pagu_tahunan']);

        // Verifikasi langsung ke database
        $countRekening = AnggaranRekening::count();
        $this->assertEquals(132, $countRekening, 'Jumlah rekening leaf di database wajib tepat 132');

        $sumPagu = (float) AnggaranRekening::sum('pagu_perubahan');
        $this->assertEquals(14252126458.00, $sumPagu, 'Total pagu di database wajib tepat Rp 14.252.126.458');

        // Verifikasi alokasi kas bulanan: 132 rekening * 12 bulan = 1.584 record
        $countKasBulanan = AnggaranKasBulanan::count();
        $this->assertEquals(132 * 12, $countKasBulanan, 'Total record kas bulanan wajib 1.584 (132 * 12)');

        // Verifikasi total kas bulanan sama persis dengan total pagu
        $sumKasBulanan = (float) AnggaranKasBulanan::sum('alokasi_pagu');
        $this->assertEquals(14252126458.00, $sumKasBulanan, 'Total alokasi 12 bulan kas bulanan wajib sama persis dengan total pagu');
    }

    /**
     * Test eksekusi Artisan command sitaliti:import-rak
     */
    public function test_artisan_command_sitaliti_import_rak_berhasil(): void
    {
        $this->seed(UnitKerjaSeeder::class);

        $this->artisan('sitaliti:import-rak', ['file' => $this->rakFilePath])
            ->assertSuccessful();

        $this->assertDatabaseHas('master_unit_kerja', [
            'kode_unit' => '7.01.0.00.0.00.41.0000',
        ]);

        $this->assertEquals(132, AnggaranRekening::count());
    }

    public function test_reimport_rak_tidak_menimpa_terkunci_dan_realisasi_bulan(): void
    {
        $this->seed(UnitKerjaSeeder::class);

        $importer = app(RakImporterServiceInterface::class);
        $importer->importFromFile($this->rakFilePath);

        $kas = AnggaranKasBulanan::where('bulan', 1)
            ->where('alokasi_pagu', '>', 0)
            ->firstOrFail();

        $kas->update([
            'terkunci_bulan' => 250000.00,
            'realisasi_bulan' => 500000.00,
        ]);

        $importer->importFromFile($this->rakFilePath);

        $kas->refresh();
        $this->assertEquals(250000.00, (float) $kas->terkunci_bulan);
        $this->assertEquals(500000.00, (float) $kas->realisasi_bulan);
    }

    public function test_reimport_rak_mempertahankan_pagu_murni_dan_menolak_jika_melanggar_kumulatif(): void
    {
        $this->seed(UnitKerjaSeeder::class);

        $importer = app(RakImporterServiceInterface::class);
        $importer->importFromFile($this->rakFilePath);

        $kas = AnggaranKasBulanan::where('bulan', 1)
            ->where('alokasi_pagu', '>', 0)
            ->firstOrFail();

        $rekening = $kas->rekening;
        $rekening->update(['pagu_murni' => 999999999.00]);

        // Re-impor sah tidak boleh menimpa pagu_murni eksisting
        $importer->importFromFile($this->rakFilePath);
        $this->assertEquals(999999999.00, (float) $rekening->fresh()->pagu_murni);

        // Jika realisasi + terkunci bulan 1 melebihi akumulasi alokasi RAK bulan 1, re-impor wajib ditolak
        $kas->update([
            'terkunci_bulan' => (float) $kas->alokasi_pagu + 100000.00,
        ]);

        try {
            $importer->importFromFile($this->rakFilePath);
            $this->fail('Seharusnya menolak re-impor karena melanggar batas kumulatif.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString($rekening->kode_rekening, $e->getMessage());
            $this->assertStringContainsString('bulan 1', $e->getMessage());
        }
    }
}
