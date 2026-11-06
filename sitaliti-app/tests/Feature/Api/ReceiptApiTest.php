<?php

namespace Tests\Feature\Api;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private UnitKerja $unit;
    private AnggaranRekening $rekening;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(\Database\Seeders\UnitKerjaSeeder::class);
        $this->unit = UnitKerja::first();

        $this->user = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
        ]);

        $this->rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Penyusunan Dokumen Perencanaan Perangkat Daerah',
            'kode_rekening' => '5.1.02.01.001.00026',
            'nama_rekening' => 'Belanja Alat/Bahan untuk Kegiatan Kantor- Bahan Cetak',
            'pagu_murni' => 20000000.00,
            'pagu_perubahan' => 20000000.00,
            'kategori_belanja' => 'B1_ATK',
            'is_leaf' => true,
        ]);

        // Buat alokasi kas bulanan 12 bulan (1.500.000 per bulan)
        for ($m = 1; $m <= 12; $m++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $this->rekening->id,
                'bulan' => $m,
                'alokasi_pagu' => 15000000.00,
                'terkunci_bulan' => 0.00,
                'realisasi_bulan' => 0.00,
            ]);
        }
    }

    /**
     * Test endpoint POST /api/v1/receipts/validate-draft
     */
    public function test_validate_draft_menghasilkan_simulasi_pajak_dan_sisa_pagu(): void
    {
        $payload = [
            'anggaran_rekening_id' => (string) $this->rekening->id,
            'bulan_anggaran' => 2,
            'kategori_belanja' => 'B1_ATK',
            'has_npwp' => true,
            'items' => [
                [
                    'nama_barang' => 'Kertas Cover Bufalo',
                    'volume' => 20,
                    'satuan' => 'Pak',
                    'harga_satuan' => 111000, // Total = 2.220.000
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/receipts/validate-draft', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_belanja' => 2220000,
                    'is_exceeding_budget' => false,
                    'tax_calculation' => [
                        'dpp' => 2000000,
                        'ppn' => 220000,
                        'pph22' => 30000,
                        'total_pajak' => 250000,
                        'nilai_neto' => 1970000,
                    ],
                ],
            ]);
    }

    /**
     * Test endpoint POST /api/v1/receipts berhasil menyimpan draft beserta upload berkas
     */
    public function test_store_berhasil_menyimpan_draft_kuitansi_dan_lampiran(): void
    {
        $dummyFile = UploadedFile::fake()->create('nota_pembelian.pdf', 150, 'application/pdf');

        $payload = [
            'anggaran_rekening_id' => $this->rekening->id,
            'bulan_anggaran' => 2,
            'nomor_kuitansi' => 'KW-2026-02-0042',
            'nomor_bukti_toko' => 'INV-8899',
            'tanggal_transaksi' => '2026-02-14',
            'uraian' => 'Pengadaan Kertas Bahan Cetak Kegiatan',
            'nama_penerima' => 'CV Sinar Garut',
            'npwp_penerima' => '01.234.567.8-443.000',
            'metode_pembayaran' => 'NON_TUNAI_IBC',
            'items' => [
                [
                    'nama_barang' => 'Kertas Folio F4 70gr',
                    'volume' => 40,
                    'satuan' => 'Rim',
                    'harga_satuan' => 55500, // 2.220.000
                ],
            ],
            'files' => [$dummyFile],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/receipts', $payload);

        dump($response->json());

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'receipt_id' => 'KW-2026-02-0042',
                    'status' => 'DRAFT',
                    'nominal_bruto' => 2220000,
                    'total_pajak' => 250000,
                    'nominal_neto' => 1970000,
                ],
            ]);

        $this->assertDatabaseHas('kuitansi', [
            'nomor_kuitansi' => 'KW-2026-02-0042',
            'status' => 'DRAFT',
        ]);

        $this->assertDatabaseHas('kuitansi_lampiran', [
            'file_name' => 'nota_pembelian.pdf',
        ]);
    }

    /**
     * Test deteksi instan nota kembar via SHA-256 (HTTP 422 DUPLICATE_RECEIPT_ATTACHMENT)
     */
    public function test_penolakan_nota_kembar_via_sha256_menghasilkan_http_422(): void
    {
        $identicContent = 'INI_KONTEN_NOTA_ASLI_YANG_SAMA_PERSIS';
        $file1 = UploadedFile::fake()->createWithContent('nota_asli.pdf', $identicContent);
        $file2 = UploadedFile::fake()->createWithContent('nota_kembar_copy.pdf', $identicContent);

        // Upload pertama -> Sukses
        $payload1 = [
            'anggaran_rekening_id' => $this->rekening->id,
            'bulan_anggaran' => 2,
            'nomor_kuitansi' => 'KW-2026-02-0001',
            'tanggal_transaksi' => '2026-02-10',
            'uraian' => 'Kuitansi Pertama',
            'nama_penerima' => 'CV Sumber Makmur',
            'items' => [
                ['nama_barang' => 'Map Kertas', 'volume' => 10, 'satuan' => 'Pak', 'harga_satuan' => 50000],
            ],
            'files' => [$file1],
        ];

        $res1 = $this->actingAs($this->user)->postJson('/api/v1/receipts', $payload1);
        $res1->assertStatus(201);

        // Upload kedua dengan file kembar pada rekening yang sama -> DITOLAK HTTP 422
        $payload2 = [
            'anggaran_rekening_id' => $this->rekening->id,
            'bulan_anggaran' => 2,
            'nomor_kuitansi' => 'KW-2026-02-0002',
            'tanggal_transaksi' => '2026-02-12',
            'uraian' => 'Kuitansi Kedua Mencoba Mengunggah Nota Kembar',
            'nama_penerima' => 'CV Sumber Makmur',
            'items' => [
                ['nama_barang' => 'Map Plastik', 'volume' => 10, 'satuan' => 'Pak', 'harga_satuan' => 50000],
            ],
            'files' => [$file2],
        ];

        $res2 = $this->actingAs($this->user)->postJson('/api/v1/receipts', $payload2);
        $res2->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'DUPLICATE_RECEIPT_ATTACHMENT',
                ],
            ]);
    }

    /**
     * Test submit kuitansi ke PPTK mengunci pagu anggaran
     */
    public function test_submit_kuitansi_mengunci_pagu_dan_status_menjadi_menunggu_pptk(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-2026-02-0099',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->user->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-15',
            'uraian' => 'Belanja ATK Siap Submit',
            'nama_penerima' => 'Toko Mitra',
            'nominal_bruto' => 1500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1500000.00,
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/submit");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $kuitansi->id,
                    'status' => 'MENUNGGU_PPTK',
                ],
            ]);

        $this->assertEquals('MENUNGGU_PPTK', $kuitansi->fresh()->status);
        $this->assertNotNull($kuitansi->fresh()->tanggal_submit);
    }
}
