<?php

namespace Tests\Feature\Receipt;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiLampiran;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\ReceiptService;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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

    public function test_duplicate_files_in_same_upload_batch_returns_422(): void
    {
        $operator = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'OPERATOR',
        ]);

        $fileA = UploadedFile::fake()->createWithContent('nota_1.pdf', '%PDF-1.4 same-bytes');
        $fileB = UploadedFile::fake()->createWithContent('nota_2.pdf', '%PDF-1.4 same-bytes');

        $res = $this->actingAs($operator)->post('/api/v1/receipts', [
            'anggaran_rekening_id' => $this->rekening->id,
            'bulan_anggaran' => 4,
            'tanggal_transaksi' => '2026-04-10',
            'uraian_belanja' => 'Uji duplikat batch',
            'nama_penerima' => 'Toko A',
            'items' => [
                ['nama_barang' => 'Kertas', 'volume' => 1, 'satuan' => 'Rim', 'harga_satuan' => 50000],
            ],
            'files' => [$fileA, $fileB],
            'checklist_codes' => ['B.1.1', 'B.1.2'],
            'nama_dokumen' => ['Doc 1', 'Doc 2'],
        ], ['Accept' => 'application/json']);

        $res->assertStatus(422)
            ->assertJsonPath('error.code', 'DUPLICATE_RECEIPT_ATTACHMENT');
    }

    public function test_updating_rekening_syncs_existing_attachments_and_rejects_collision(): void
    {
        $operator = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'OPERATOR',
        ]);

        $rekeningB = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Penyusunan Rencana Program',
            'kode_rekening' => '5.1.02.01.01.0099',
            'nama_rekening' => 'Belanja Penggandaan',
            'pagu_murni' => 10000000.00,
            'pagu_perubahan' => 10000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        $draft = Kuitansi::create([
            'nomor_kuitansi' => 'KW-MOVE-001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $operator->id,
            'bulan_anggaran' => 4,
            'tanggal_transaksi' => '2026-04-10',
            'uraian' => 'Draft pindah rekening',
            'nama_penerima' => 'Toko B',
            'nominal_bruto' => 100000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 100000.00,
            'status' => 'DRAFT',
        ]);

        $draftLampiran = KuitansiLampiran::create([
            'kuitansi_id' => $draft->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'checklist_code' => 'B.1.4',
            'nama_dokumen' => 'Nota Draft',
            'file_path' => 'receipts/2026/04/draft.pdf',
            'file_name' => 'draft.pdf',
            'file_size' => 100,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'unique-draft-file'),
        ]);

        $res = $this->actingAs($operator)->postJson("/api/v1/receipts/{$draft->id}/update", [
            'anggaran_rekening_id' => $rekeningB->id,
            'items' => [
                ['nama_barang' => 'Fotokopi', 'volume' => 1, 'satuan' => 'Paket', 'harga_satuan' => 100000],
            ],
        ]);

        $res->assertStatus(200);
        $this->assertEquals($rekeningB->id, $draftLampiran->fresh()->anggaran_rekening_id);

        // Sekarang buat bentrok hash: memindahkan $draft kembali ke rekening awal dengan hash yang sama milik $this->lampiran
        $draftLampiran->update(['file_hash' => $this->lampiran->file_hash]);

        $collisionRes = $this->actingAs($operator)->postJson("/api/v1/receipts/{$draft->id}/update", [
            'anggaran_rekening_id' => $this->rekening->id,
            'items' => [
                ['nama_barang' => 'Fotokopi', 'volume' => 1, 'satuan' => 'Paket', 'harga_satuan' => 100000],
            ],
        ]);

        $collisionRes->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_delete_attachment_preserves_physical_file_when_transaction_rolls_back(): void
    {
        $operator = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'OPERATOR',
        ]);

        $draft = Kuitansi::create([
            'nomor_kuitansi' => 'KW-ROLLBACK-001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $operator->id,
            'bulan_anggaran' => 4,
            'tanggal_transaksi' => '2026-04-10',
            'uraian' => 'Draft rollback',
            'nama_penerima' => 'Toko C',
            'nominal_bruto' => 100000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 100000.00,
            'status' => 'DRAFT',
        ]);

        $path = 'receipts/2026/04/keep_on_rollback.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 rollback-safe');

        $att = KuitansiLampiran::create([
            'kuitansi_id' => $draft->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'checklist_code' => 'B.1.4',
            'nama_dokumen' => 'Nota Rollback',
            'file_path' => $path,
            'file_name' => 'keep_on_rollback.pdf',
            'file_size' => 100,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'rollback-safe'),
        ]);

        try {
            DB::transaction(function () use ($draft, $att, $operator) {
                app(ReceiptService::class)->deleteAttachment($draft->id, $att->id, $operator);
                throw new RuntimeException('Simulated failure after deleteAttachment');
            });
        } catch (RuntimeException) {
            // Expected rollback
        }

        Storage::disk('local')->assertExists($path);
        $this->assertDatabaseHas('kuitansi_lampiran', ['id' => $att->id]);
    }
}
