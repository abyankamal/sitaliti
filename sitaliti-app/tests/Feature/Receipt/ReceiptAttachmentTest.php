<?php

namespace Tests\Feature\Receipt;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiLampiran;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UnitKerja $unit;

    private AnggaranRekening $rekening;

    private Kuitansi $kuitansi;

    private KuitansiLampiran $lampiran;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(UnitKerjaSeeder::class);
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
            'nomor_kuitansi' => 'KW-ATTACH-TEST-001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->user->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-15',
            'uraian' => 'Belanja ATK Uji Streaming Lampiran',
            'nama_penerima' => 'CV Mitra Sejati',
            'nominal_bruto' => 1500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1500000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        $filePath = 'receipts/2026/02/test_nota.pdf';
        Storage::disk('local')->put($filePath, '%PDF-1.4 dummy pdf content for testing attachment streaming');

        $this->lampiran = KuitansiLampiran::create([
            'kuitansi_id' => $this->kuitansi->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'checklist_code' => 'B.1.4',
            'nama_dokumen' => 'Nota Pembelian Toko',
            'file_path' => $filePath,
            'file_name' => 'nota_pembelian.pdf',
            'file_size' => 1234,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'dummy'),
        ]);
    }

    public function test_unauthenticated_user_cannot_stream_attachment(): void
    {
        // Web route: redirect to login
        $resWeb = $this->get("/receipts/{$this->kuitansi->id}/attachments/{$this->lampiran->id}/preview");
        $resWeb->assertRedirect('/login');

        // Api route: 401 Unauthorized
        $resApi = $this->getJson("/api/v1/receipts/{$this->kuitansi->id}/attachments/{$this->lampiran->id}/preview");
        $resApi->assertStatus(401);
    }

    public function test_authenticated_user_can_stream_attachment(): void
    {
        // 1. Web route
        $resWeb = $this->actingAs($this->user)
            ->get("/receipts/{$this->kuitansi->id}/attachments/{$this->lampiran->id}/preview");

        $resWeb->assertStatus(200);
        $resWeb->assertHeader('Content-Type', 'application/pdf');

        // 2. Api route
        $resApi = $this->actingAs($this->user)
            ->get("/api/v1/receipts/{$this->kuitansi->id}/attachments/{$this->lampiran->id}/preview");

        $resApi->assertStatus(200);
        $resApi->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_streaming_attachment_by_kuitansi_nomor(): void
    {
        $res = $this->actingAs($this->user)
            ->get("/receipts/{$this->kuitansi->nomor_kuitansi}/attachments/{$this->lampiran->id}/preview");

        $res->assertStatus(200);
        $res->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_streaming_missing_file_or_invalid_id_returns_404(): void
    {
        $resNotFound = $this->actingAs($this->user)
            ->get("/receipts/{$this->kuitansi->id}/attachments/99999/preview");

        $resNotFound->assertStatus(404);
    }

    public function test_lampiran_model_has_file_url_accessor(): void
    {
        $this->lampiran->refresh();
        $this->assertNotNull($this->lampiran->file_url);
        $this->assertStringContainsString("/receipts/{$this->kuitansi->id}/attachments/{$this->lampiran->id}/preview", $this->lampiran->file_url);

        $array = $this->lampiran->toArray();
        $this->assertArrayHasKey('file_url', $array);
    }
}

