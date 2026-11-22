<?php

namespace Tests\Feature\Export;

use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvFormulaInjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_export_escapes_formula_prefixes_and_preserves_dash_placeholder(): void
    {
        $this->seed(UnitKerjaSeeder::class);
        $unit = UnitKerja::first();

        $user = User::factory()->create([
            'unit_kerja_id' => $unit->id,
            'role' => 'VERIFIKATOR',
        ]);

        $rekening = AnggaranRekening::create([
            'unit_kerja_id' => $unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Subkegiatan Ekspor',
            'kode_rekening' => '5.1.02.01.01.0001',
            'nama_rekening' => 'Belanja ATK',
            'pagu_murni' => 10000000.00,
            'pagu_perubahan' => 10000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        Kuitansi::create([
            'nomor_kuitansi' => 'KW-CSV-001',
            'unit_kerja_id' => $unit->id,
            'anggaran_rekening_id' => $rekening->id,
            'user_id' => $user->id,
            'bulan_anggaran' => 4,
            'tanggal_transaksi' => '2026-04-10',
            'uraian' => "=CMD|'/C calc'!A0",
            'nama_penerima' => '@SUM(1+1)',
            'npwp_penerima' => null,
            'nominal_bruto' => 100000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 100000.00,
            'status' => 'DRAFT',
        ]);

        Kuitansi::create([
            'nomor_kuitansi' => 'KW-CSV-002',
            'unit_kerja_id' => $unit->id,
            'anggaran_rekening_id' => $rekening->id,
            'user_id' => $user->id,
            'bulan_anggaran' => 4,
            'tanggal_transaksi' => '2026-04-11',
            'uraian' => '-1+1',
            'nama_penerima' => '+1+1',
            'npwp_penerima' => null,
            'nominal_bruto' => 100000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 100000.00,
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($user)->get('/api/v1/receipts/export?format=csv');
        $response->assertStatus(200);

        $csv = $response->streamedContent();

        $this->assertStringContainsString("\"'=CMD|'/C calc'!A0\"", $csv);
        $this->assertStringContainsString("'@SUM(1+1)", $csv);
        $this->assertStringContainsString("'-1+1", $csv);
        $this->assertStringContainsString("'+1+1", $csv);
        // Placeholder '-' untuk NPWP & SP2D kosong tidak boleh menjadi '-'
        $this->assertStringNotContainsString("'-'", $csv);
    }
}
