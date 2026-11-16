<?php

namespace Tests\Feature\Receipt;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptTaxOptionsPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private UnitKerja $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(UnitKerjaSeeder::class);
        $this->unit = UnitKerja::first();

        $this->operator = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'OPERATOR',
        ]);
    }

    private function createRekening(string $kodeRekening, string $kategori): AnggaranRekening
    {
        $rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Subkegiatan Uji Pajak',
            'kode_rekening' => $kodeRekening,
            'nama_rekening' => "Rekening {$kategori}",
            'pagu_murni' => 50000000.00,
            'pagu_perubahan' => 50000000.00,
            'kategori_belanja' => $kategori,
            'is_leaf' => true,
        ]);

        for ($m = 1; $m <= 12; $m++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $rekening->id,
                'bulan' => $m,
                'alokasi_pagu' => 10000000.00,
                'terkunci_bulan' => 0.00,
                'realisasi_bulan' => 0.00,
            ]);
        }

        return $rekening;
    }

    public function test_b3_honor_golongan_iv_persists_and_survives_partial_update(): void
    {
        $rekening = $this->createRekening('5.1.02.02.01.0003', 'B3_HONOR');

        $storeRes = $this->actingAs($this->operator)->postJson('/api/v1/receipts', [
            'anggaran_rekening_id' => $rekening->id,
            'bulan_anggaran' => 4,
            'tanggal_transaksi' => '2026-04-10',
            'uraian_belanja' => 'Honorarium Narasumber Gol IV',
            'nama_penerima' => 'Dr. Ahmad',
            'npwp_penerima' => '123456789012345',
            'golongan_asn' => 'GOLONGAN_IV',
            'items' => [
                ['nama_barang' => 'Honor Narasumber', 'volume' => 1, 'satuan' => 'OJ', 'harga_satuan' => 1000000],
            ],
        ]);

        $storeRes->assertStatus(201);
        $kuitansiId = $storeRes->json('data.id');

        $kuitansi = Kuitansi::findOrFail($kuitansiId);
        $this->assertEquals('GOLONGAN_IV', $kuitansi->golongan_asn);
        $this->assertEquals(150000.00, (float) $kuitansi->total_pajak);

        // Partial update tanpa mengirim golongan_asn harus mempertahankan GOLONGAN_IV (15%)
        $updateRes = $this->actingAs($this->operator)->postJson("/api/v1/receipts/{$kuitansiId}/update", [
            'uraian_belanja' => 'Honorarium Narasumber Gol IV (Typo Fixed)',
            'items' => [
                ['nama_barang' => 'Honor Narasumber', 'volume' => 1, 'satuan' => 'OJ', 'harga_satuan' => 1000000],
            ],
        ]);

        $updateRes->assertStatus(200);
        $kuitansi->refresh();
        $this->assertEquals('GOLONGAN_IV', $kuitansi->golongan_asn);
        $this->assertEquals(150000.00, (float) $kuitansi->total_pajak);
        $this->assertEquals(850000.00, (float) $kuitansi->nominal_neto);
    }

    public function test_b4_sewa_kendaraan_and_b5_non_pkp_persist_across_updates(): void
    {
        $rekB4 = $this->createRekening('5.1.02.04.01.0001', 'B4_PERJADIN');
        $resB4 = $this->actingAs($this->operator)->postJson('/api/v1/receipts', [
            'anggaran_rekening_id' => $rekB4->id,
            'bulan_anggaran' => 4,
            'tanggal_transaksi' => '2026-04-10',
            'uraian_belanja' => 'Sewa Kendaraan Dinas',
            'nama_penerima' => 'PT Rental Jaya',
            'npwp_penerima' => '123456789012345',
            'is_sewa_kendaraan' => true,
            'items' => [
                ['nama_barang' => 'Sewa Mobil', 'volume' => 1, 'satuan' => 'Hari', 'harga_satuan' => 1000000],
            ],
        ]);
        $resB4->assertStatus(201);
        $b4Id = $resB4->json('data.id');
        $this->assertTrue((bool) Kuitansi::findOrFail($b4Id)->is_sewa_kendaraan);
        $this->assertEquals(20000.00, (float) $resB4->json('data.total_pajak'));

        $updateB4 = $this->actingAs($this->operator)->postJson("/api/v1/receipts/{$b4Id}/update", [
            'uraian_belanja' => 'Sewa Kendaraan Dinas (Revisi Uraian)',
            'items' => [
                ['nama_barang' => 'Sewa Mobil', 'volume' => 1, 'satuan' => 'Hari', 'harga_satuan' => 1000000],
            ],
        ]);
        $updateB4->assertStatus(200);
        $this->assertEquals(20000.00, (float) $updateB4->json('data.total_pajak'));

        $rekB5 = $this->createRekening('5.1.02.03.02.0001', 'B5_PEMELIHARAAN');
        $resB5 = $this->actingAs($this->operator)->postJson('/api/v1/receipts', [
            'anggaran_rekening_id' => $rekB5->id,
            'bulan_anggaran' => 4,
            'tanggal_transaksi' => '2026-04-10',
            'uraian_belanja' => 'Pemeliharaan AC Non-PKP',
            'nama_penerima' => 'CV Dingin Sejuk',
            'npwp_penerima' => '123456789012345',
            'is_pkp' => false,
            'items' => [
                ['nama_barang' => 'Servis AC', 'volume' => 1, 'satuan' => 'Paket', 'harga_satuan' => 3000000],
            ],
        ]);
        $resB5->assertStatus(201);
        $b5Id = $resB5->json('data.id');
        $this->assertFalse((bool) Kuitansi::findOrFail($b5Id)->is_pkp);
        // Non-PKP pada B.5: hanya PPh 23 2% dari 3.000.000 = 60.000 (tanpa PPN 11%)
        $this->assertEquals(60000.00, (float) $resB5->json('data.total_pajak'));

        $updateB5 = $this->actingAs($this->operator)->postJson("/api/v1/receipts/{$b5Id}/update", [
            'uraian_belanja' => 'Pemeliharaan AC Non-PKP (Revisi Uraian)',
            'items' => [
                ['nama_barang' => 'Servis AC', 'volume' => 1, 'satuan' => 'Paket', 'harga_satuan' => 3000000],
            ],
        ]);
        $updateB5->assertStatus(200);
        $this->assertFalse((bool) Kuitansi::findOrFail($b5Id)->is_pkp);
        $this->assertEquals(60000.00, (float) $updateB5->json('data.total_pajak'));
    }

    public function test_invalid_golongan_asn_is_rejected_with_422(): void
    {
        $rekening = $this->createRekening('5.1.02.02.01.0004', 'B3_HONOR');

        $res = $this->actingAs($this->operator)->postJson('/api/v1/receipts', [
            'anggaran_rekening_id' => $rekening->id,
            'bulan_anggaran' => 4,
            'tanggal_transaksi' => '2026-04-10',
            'uraian_belanja' => 'Honor Invalid',
            'nama_penerima' => 'Test',
            'golongan_asn' => 'GOLONGAN_TIDAK_VALID',
            'items' => [
                ['nama_barang' => 'Honor', 'volume' => 1, 'satuan' => 'OJ', 'harga_satuan' => 500000],
            ],
        ]);

        $res->assertStatus(422);
    }
}
