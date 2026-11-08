<?php

namespace Tests\Feature\Pdf;

use App\Contracts\ControlSheetGeneratorServiceInterface;
use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiItem;
use App\Models\KuitansiPajak;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\MasterChecklistSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlSheetPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UnitKerja $unit;

    private AnggaranRekening $rekening;

    private Kuitansi $kuitansi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->seed(MasterChecklistSeeder::class);

        $this->unit = UnitKerja::first();

        $this->user = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'VERIFIKATOR',
        ]);

        $this->rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Penyusunan Rencana Program',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja ATK Kantor',
            'pagu_murni' => 20000000.00,
            'pagu_perubahan' => 20000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        for ($m = 1; $m <= 12; $m++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $this->rekening->id,
                'bulan' => $m,
                'alokasi_pagu' => 1500000.00,
                'terkunci_bulan' => 0.00,
                'realisasi_bulan' => 0.00,
            ]);
        }

        $this->kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-PDF-TEST-001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->user->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-15',
            'uraian' => 'Pengadaan Kertas & Tinta Cetak Lembar Kendali',
            'nama_penerima' => 'CV Garut Jaya Mandiri',
            'npwp_penerima' => '01.234.567.8-443.000',
            'nominal_bruto' => 2220000.00,
            'total_pajak' => 250000.00,
            'nominal_neto' => 1970000.00,
            'status' => 'LOLOS_VERIFIKASI',
            'qr_token' => 'qr-test-uuid-12345678',
            'catatan_verifikasi' => 'Dokumen lengkap dan sah sesuai ketentuan.',
            'verifikator_id' => $this->user->id,
            'tanggal_verifikasi' => now(),
        ]);

        KuitansiItem::create([
            'kuitansi_id' => $this->kuitansi->id,
            'nama_barang' => 'Kertas HVS A4 80gr',
            'volume' => 40.00,
            'satuan' => 'Rim',
            'harga_satuan' => 55500.00,
            'total_harga' => 2220000.00,
        ]);

        KuitansiPajak::create([
            'kuitansi_id' => $this->kuitansi->id,
            'kode_pajak' => 'PPN_11',
            'nama_pajak' => 'PPN 11%',
            'tarif' => 0.1100,
            'tipe_tarif' => 'PERSENTASE_DPP',
            'dpp' => 2000000.00,
            'nominal' => 220000.00,
        ]);
    }

    public function test_service_generates_valid_pdf_instance_with_offline_svg_qr(): void
    {
        /** @var ControlSheetGeneratorServiceInterface $service */
        $service = app(ControlSheetGeneratorServiceInterface::class);

        $pdf = $service->generatePdf($this->kuitansi->id);
        $this->assertNotNull($pdf);

        $output = $pdf->output();
        // Validasi header biner PDF resmi
        $this->assertStringStartsWith('%PDF-', $output);
        $this->assertGreaterThan(1000, strlen($output));
    }

    public function test_api_download_route_returns_pdf_stream_with_proper_headers(): void
    {
        // 1. Unauthenticated -> 401
        $resUnauth = $this->getJson("/api/v1/control-sheet/{$this->kuitansi->id}/pdf");
        $resUnauth->assertStatus(401);

        // 2. Authenticated -> 200 with application/pdf header
        $resAuth = $this->actingAs($this->user)->get("/api/v1/control-sheet/{$this->kuitansi->id}/pdf");
        $resAuth->assertStatus(200);
        $resAuth->assertHeader('Content-Type', 'application/pdf');

        $contentDisposition = $resAuth->headers->get('Content-Disposition');
        $this->assertNotNull($contentDisposition);
        $this->assertStringContainsString('Lembar-Kendali-', $contentDisposition);
        $this->assertStringContainsString('.pdf', $contentDisposition);

        // Validasi biner PDF
        $this->assertStringStartsWith('%PDF-', $resAuth->getContent());
    }

    public function test_web_download_route_requires_auth_and_serves_pdf(): void
    {
        // 1. Unauthenticated -> Redirect to login
        $resUnauth = $this->get("/control-sheet/{$this->kuitansi->id}/download-pdf");
        $resUnauth->assertRedirect('/login');

        // 2. Authenticated -> 200 with PDF content
        $resAuth = $this->actingAs($this->user)->get("/control-sheet/{$this->kuitansi->id}/download-pdf");
        $resAuth->assertStatus(200);
        $resAuth->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_non_existent_receipt_throws_model_not_found(): void
    {
        $response = $this->actingAs($this->user)->get('/api/v1/control-sheet/999999/pdf');
        $response->assertStatus(404);
    }
}
