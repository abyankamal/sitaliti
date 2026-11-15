<?php

namespace Tests\Feature\Public;

use App\Contracts\ControlSheetGeneratorServiceInterface;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiItem;
use App\Models\KuitansiPajak;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class QrVerificationPublicTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unit;

    private User $verifikator;

    private AnggaranRekening $rekening;

    private Kuitansi $kuitansiLolos;

    private string $qrToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->unit = UnitKerja::first();

        $this->verifikator = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'VERIFIKATOR',
            'name' => 'Asep Kurniawan, S.IP',
            'nip' => '198503142010011008',
            'jabatan' => 'Verifikator Keuangan Wilayah',
        ]);

        $this->rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Koordinasi dan Pembinaan Kewilayahan',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja Alat/Bahan untuk Kegiatan Kantor- Bahan Cetak',
            'pagu_murni' => 30000000.00,
            'pagu_perubahan' => 30000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        $this->qrToken = Str::uuid()->toString();

        $this->kuitansiLolos = Kuitansi::create([
            'nomor_kuitansi' => 'KW-2026-06-0042',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->verifikator->id,
            'verifikator_id' => $this->verifikator->id,
            'bulan_anggaran' => 6,
            'tanggal_transaksi' => '2026-06-12',
            'tanggal_verifikasi' => '2026-06-13 10:30:00',
            'uraian' => 'Pengadaan Spanduk dan Leaflet Pembinaan Kelurahan',
            'nama_penerima' => 'CV Sinar Garut Mandiri',
            'npwp_penerima' => '01.234.567.8-443.000',
            'metode_pembayaran' => 'NON_TUNAI_IBC',
            'nominal_bruto' => 3330000.00,
            'total_pajak' => 375000.00,
            'nominal_neto' => 2955000.00,
            'status' => 'LOLOS_VERIFIKASI',
            'qr_token' => $this->qrToken,
            'catatan_verifikasi' => 'Berkas lengkap, faktur pajak dan bukti fisik sah.',
        ]);

        KuitansiItem::create([
            'kuitansi_id' => $this->kuitansiLolos->id,
            'nama_barang' => 'Spanduk Vinyl 3x1',
            'volume' => 10,
            'satuan' => 'Lembar',
            'harga_satuan' => 150000,
            'total_harga' => 1500000,
        ]);

        KuitansiPajak::create([
            'kuitansi_id' => $this->kuitansiLolos->id,
            'kode_pajak' => 'PPN_11',
            'nama_pajak' => 'PPN 11%',
            'tarif' => 0.11,
            'dpp' => 3000000,
            'nominal' => 330000,
        ]);
    }

    /**
     * Test akses endpoint publik /verify/{qr_token} dapat diakses tanpa login (unauthenticated).
     */
    public function test_public_verification_endpoint_dapat_diakses_tanpa_autentikasi(): void
    {
        $response = $this->get("/verify/{$this->qrToken}");

        $response->assertStatus(200);
    }

    /**
     * Test respons JSON verifikasi publik mengembalikan metadata keabsahan dokumen secara akurat.
     */
    public function test_public_verification_json_mengembalikan_detail_keabsahan_dokumen(): void
    {
        $response = $this->getJson("/verify/{$this->qrToken}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'qr_token' => $this->qrToken,
                    'is_valid' => true,
                    'nomor_kuitansi' => 'KW-2026-06-0042',
                    'status' => 'LOLOS_VERIFIKASI',
                    'unit_kerja' => $this->unit->nama_unit,
                    'nominal_bruto' => 3330000,
                    'total_pajak' => 375000,
                    'nominal_neto' => 2955000,
                    'is_sensored' => true,
                ],
            ]);
    }

    /**
     * Test data sensitif rekanan (PII) terbukti disensor/diredaksi demi perlindungan privasi.
     */
    public function test_data_sensitif_rekanan_terbukti_diredaksi_demi_privasi(): void
    {
        $response = $this->getJson("/verify/{$this->qrToken}");

        $response->assertStatus(200);

        $data = $response->json('data');

        // 1. Verifikasi sensor nama rekanan
        // Original: "CV Sinar Garut Mandiri" -> Redacted: "CV S**** G**** M******"
        $this->assertNotEquals('CV Sinar Garut Mandiri', $data['nama_penerima_redacted']);
        $this->assertStringContainsString('CV', $data['nama_penerima_redacted']);
        $this->assertStringContainsString('*', $data['nama_penerima_redacted']);
        $this->assertStringNotContainsString('Sinar', $data['nama_penerima_redacted']);

        // 2. Verifikasi sensor NPWP rekanan
        // Original: "01.234.567.8-443.000" -> Redacted: "01.***.***.*-***.***"
        $this->assertNotEquals('01.234.567.8-443.000', $data['npwp_penerima_redacted']);
        $this->assertStringStartsWith('01.', $data['npwp_penerima_redacted']);
        $this->assertStringContainsString('*', $data['npwp_penerima_redacted']);
        $this->assertStringNotContainsString('234', $data['npwp_penerima_redacted']);

        // 3. Verifikasi sensor NIP verifikator
        // Original: "198503142010011008" -> Redacted middle: "1985************08"
        $this->assertNotEquals('198503142010011008', $data['verifikator']['nip']);
        $this->assertStringStartsWith('1985', $data['verifikator']['nip']);
        $this->assertStringEndsWith('08', $data['verifikator']['nip']);
        $this->assertStringContainsString('*', $data['verifikator']['nip']);
    }

    /**
     * Test QR token yang tidak terdaftar menghasilkan response 404 NOT FOUND.
     */
    public function test_qr_token_yang_tidak_terdaftar_mengembalikan_404(): void
    {
        $nonExistentToken = 'b8293774-0000-0000-0000-nonexistent99';

        $response = $this->getJson("/verify/{$nonExistentToken}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'TOKEN_NOT_FOUND',
                ],
            ]);
    }

    /**
     * Test ControlSheetGeneratorService menghasilkan payload lengkap untuk Lembar Kendali cetak.
     */
    public function test_control_sheet_generator_service_menghasilkan_data_lengkap_untuk_lembar_kendali(): void
    {
        $service = app(ControlSheetGeneratorServiceInterface::class);

        $sheetData = $service->generateData((string) $this->kuitansiLolos->id);

        $this->assertEquals('KW-2026-06-0042', $sheetData['nomor_kuitansi']);
        $this->assertStringContainsString('LK-V2/KW-2026-06-0042/2026', $sheetData['nomor_register']);
        $this->assertCount(1, $sheetData['items']);
        $this->assertCount(1, $sheetData['pajak']);
        $this->assertEquals('Asep Kurniawan, S.IP', $sheetData['verifikator']['name']);
        $this->assertNotNull($sheetData['verification_url']);
    }

    public function test_public_qr_verification_is_rate_limited_after_30_requests_per_minute(): void
    {
        Cache::flush();

        for ($i = 0; $i < 30; $i++) {
            $this->getJson("/verify/{$this->qrToken}")->assertStatus(200);
        }

        $this->getJson("/verify/{$this->qrToken}")->assertStatus(429);

        Cache::flush();
    }
}
