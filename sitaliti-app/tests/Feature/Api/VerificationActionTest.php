<?php

namespace Tests\Feature\Api;

use App\Contracts\BudgetReservationServiceInterface;
use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiLampiran;
use App\Models\MasterChecklistItem;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\MasterChecklistSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VerificationActionTest extends TestCase
{
    use RefreshDatabase;

    private User $verifier;

    private UnitKerja $unit;

    private AnggaranRekening $rekening;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->seed(MasterChecklistSeeder::class);

        $this->unit = UnitKerja::first();

        $this->verifier = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'VERIFIKATOR',
        ]);

        $this->rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Penyusunan Rencana Operasional',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja Alat Tulis Kantor',
            'pagu_murni' => 50000000.00,
            'pagu_perubahan' => 50000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        for ($m = 1; $m <= 12; $m++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $this->rekening->id,
                'bulan' => $m,
                'alokasi_pagu' => 10000000.00,
                'terkunci_bulan' => 0.00,
                'realisasi_bulan' => 0.00,
            ]);
        }
    }

    /**
     * Test antrean verifikasi kuitansi dan filter status.
     */
    public function test_get_queue_returns_receipts_with_proper_filtering(): void
    {
        Kuitansi::create([
            'nomor_kuitansi' => 'KW-VERIF-001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->verifier->id,
            'bulan_anggaran' => 3,
            'tanggal_transaksi' => '2026-03-10',
            'uraian' => 'Kuitansi antrean verifikasi 1',
            'nama_penerima' => 'Toko ATK Berkah',
            'nominal_bruto' => 2000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 2000000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        Kuitansi::create([
            'nomor_kuitansi' => 'KW-VERIF-002',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->verifier->id,
            'bulan_anggaran' => 3,
            'tanggal_transaksi' => '2026-03-11',
            'uraian' => 'Kuitansi sudah lolos',
            'nama_penerima' => 'Toko ATK Berkah',
            'nominal_bruto' => 1500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1500000.00,
            'status' => 'LOLOS_VERIFIKASI',
        ]);

        $response = $this->actingAs($this->verifier)
            ->getJson('/api/v1/verification/queue?status=MENUNGGU_VERIFIKASI');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('KW-VERIF-001', $data[0]['id']);
        $this->assertEquals(2000000, $data[0]['nominal']);
    }

    /**
     * Test pengambilan detail kuitansi beserta template butir checklist B.1.
     */
    public function test_get_detail_returns_receipt_details_and_dynamic_checklist_items(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-DETAIL-001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->verifier->id,
            'bulan_anggaran' => 3,
            'tanggal_transaksi' => '2026-03-10',
            'uraian' => 'Belanja ATK Rapat Evaluasi',
            'nama_penerima' => 'CV Sumber Rezeki',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        $response = $this->actingAs($this->verifier)
            ->getJson("/api/v1/verification/{$kuitansi->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $kuitansi->id,
                    'nomor_kuitansi' => 'KW-DETAIL-001',
                    'uraian' => 'Belanja ATK Rapat Evaluasi',
                ],
            ]);

        $checklist = $response->json('data.checklist');
        $this->assertNotEmpty($checklist);
        $this->assertArrayHasKey('kode_item', $checklist[0]);
        $this->assertEquals('BELUM_DIPERIKSA', $checklist[0]['status_kesesuaian']);
    }

    /**
     * Test aksi verifikasi VERIFIED (Lolos) menerbitkan qr_token & status LOLOS_VERIFIKASI.
     */
    public function test_process_action_verified_marks_status_lolos_verifikasi_and_generates_qr_token(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-ACTION-001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->verifier->id,
            'bulan_anggaran' => 3,
            'tanggal_transaksi' => '2026-03-10',
            'uraian' => 'Pengadaan Map Arsip',
            'nama_penerima' => 'Toko Mitra',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        KuitansiLampiran::create([
            'kuitansi_id' => $kuitansi->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'checklist_code' => 'B.1.1',
            'nama_dokumen' => 'Nota Toko',
            'file_path' => 'receipts/2026/03/nota.pdf',
            'file_name' => 'nota.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'nota-valid-001'),
        ]);

        $requiredItems = MasterChecklistItem::where('kategori_belanja', 'B.1')
            ->where('is_active', true)
            ->where('is_required', true)
            ->get();

        $checklistItem = $requiredItems->first();

        $payload = [
            'action' => 'VERIFIED',
            'notes' => 'Semua bukti dukung lengkap dan sesuai.',
            'checklist_results' => $requiredItems->map(fn ($item) => [
                'item_id' => $item->id,
                'status' => 'SESUAI',
                'notes' => 'Faktur pajak dan nota fisik valid.',
            ])->values()->all(),
        ];

        $response = $this->actingAs($this->verifier)
            ->postJson("/api/v1/verification/{$kuitansi->id}/action", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'new_status' => 'LOLOS_VERIFIKASI',
                ],
            ]);

        $refreshed = $kuitansi->fresh();
        $this->assertEquals('LOLOS_VERIFIKASI', $refreshed->status);
        $this->assertNotNull($refreshed->qr_token);
        $this->assertEquals($this->verifier->id, $refreshed->verifikator_id);
        $this->assertNotNull($refreshed->tanggal_verifikasi);

        $this->assertDatabaseHas('kuitansi_verifikasi_checklist', [
            'kuitansi_id' => $kuitansi->id,
            'master_checklist_item_id' => $checklistItem->id,
            'is_verified' => true,
            'status_kesesuaian' => 'SESUAI',
        ]);
    }

    /**
     * Test aksi verifikasi REVISION_MINOR menetapkan SLA 2 hari kerja efektif (melewati akhir pekan).
     */
    public function test_process_action_revision_minor_calculates_two_weekdays_sla(): void
    {
        // Tetapkan waktu pada hari Jumat jam 14:00
        Carbon::setTestNow(Carbon::parse('2026-05-08 14:00:00')); // Friday

        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-SLA-001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->verifier->id,
            'bulan_anggaran' => 5,
            'tanggal_transaksi' => '2026-05-05',
            'uraian' => 'Kuitansi perlu revisi minor',
            'nama_penerima' => 'Toko Mitra',
            'nominal_bruto' => 750000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 750000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        $payload = [
            'action' => 'REVISION_MINOR',
            'notes' => 'Cap toko belum basah, mohon legalisir ulang.',
        ];

        $response = $this->actingAs($this->verifier)
            ->postJson("/api/v1/verification/{$kuitansi->id}/action", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'new_status' => 'REVISI_MINOR',
                ],
            ]);

        $refreshed = $kuitansi->fresh();
        $this->assertEquals('REVISI_MINOR', $refreshed->status);
        $this->assertEquals('Cap toko belum basah, mohon legalisir ulang.', $refreshed->catatan_verifikasi);

        // 2 hari kerja dari Jumat adalah Selasa jam 14:00 (Sabtu & Minggu dilewati)
        $expectedDeadline = Carbon::parse('2026-05-12 14:00:00');
        $this->assertEquals($expectedDeadline->toDateTimeString(), $refreshed->tenggat_sla->toDateTimeString());

        Carbon::setTestNow(); // Reset test time
    }

    /**
     * Test aksi verifikasi REVISION_MAJOR melepaskan pagu anggaran yang terkunci.
     */
    public function test_process_action_revision_major_releases_locked_budget(): void
    {
        // 1. Kunci pagu anggaran terlebih dahulu via BudgetReservationService
        $budgetService = app(BudgetReservationServiceInterface::class);
        $nominal = 4000000.00;
        $bulan = 4;

        $budgetService->reserveBudget((string) $this->rekening->id, $bulan, $nominal);

        // Verifikasi terkunci_bulan bertambah 4.000.000
        $kas = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekening->id)
            ->where('bulan', $bulan)
            ->first();
        $this->assertEquals(4000000.00, (float) $kas->terkunci_bulan);

        // 2. Buat kuitansi dengan nominal tersebut
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-MAJOR-001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->verifier->id,
            'bulan_anggaran' => $bulan,
            'tanggal_transaksi' => '2026-04-10',
            'uraian' => 'Kuitansi dengan salah kodifikasi rekening belanja',
            'nama_penerima' => 'Penyedia Jasa',
            'nominal_bruto' => $nominal,
            'total_pajak' => 0.00,
            'nominal_neto' => $nominal,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        $payload = [
            'action' => 'REVISION_MAJOR',
            'notes' => 'Salah kodifikasi rekening belanja, harus dibatalkan dan input ulang di subkegiatan lain.',
        ];

        $response = $this->actingAs($this->verifier)
            ->postJson("/api/v1/verification/{$kuitansi->id}/action", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'new_status' => 'REVISI_MAYOR',
                ],
            ]);

        $refreshed = $kuitansi->fresh();
        $this->assertEquals('REVISI_MAYOR', $refreshed->status);

        // Verifikasi pagu anggaran terkunci telah dipulihkan (kembali ke 0)
        $kasRefreshed = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekening->id)
            ->where('bulan', $bulan)
            ->first();
        $this->assertEquals(0.00, (float) $kasRefreshed->terkunci_bulan);
    }

    public function test_anti_bypass_guard_rejects_verification_on_menunggu_pptk_and_filters_from_queue(): void
    {
        $kuitansiPptk = Kuitansi::create([
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->verifier->id,
            'nomor_kuitansi' => 'KW-ANTI-BYPASS-01',
            'bulan_anggaran' => 3,
            'tanggal_transaksi' => '2026-03-01',
            'uraian' => 'Kuitansi belum lolos PPTK',
            'nama_penerima' => 'Toko Alat Tulis',
            'nominal_bruto' => 1500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1500000.00,
            'status' => 'MENUNGGU_PPTK',
        ]);

        // 1. Kuitansi berstatus MENUNGGU_PPTK tidak boleh muncul di antrean verifikator secara default
        $queueResponse = $this->actingAs($this->verifier)->getJson('/api/v1/verification/queue');
        $queueResponse->assertStatus(200);
        $queueItems = collect($queueResponse->json('data'));
        $this->assertFalse(
            $queueItems->contains('kuitansi_id', $kuitansiPptk->id),
            'Kuitansi berstatus MENUNGGU_PPTK tidak boleh tampil di antrean verifikator'
        );

        // 2. Jika verifikator mencoba memproses aksi verifikasi, sistem menolak dengan HTTP 422
        $actionResponse = $this->actingAs($this->verifier)->postJson("/api/v1/verification/{$kuitansiPptk->id}/action", [
            'action' => 'VERIFIED',
            'notes' => 'Mencoba memverifikasi tanpa izin PPTK',
        ]);

        $actionResponse->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_ACTION',
                    'message' => 'Kuitansi harus disetujui oleh PPTK terlebih dahulu sebelum dapat diverifikasi.',
                ],
            ]);
    }

    public function test_process_action_verified_rejects_when_no_attachments(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-NO-ATT-001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->verifier->id,
            'bulan_anggaran' => 3,
            'tanggal_transaksi' => '2026-03-10',
            'uraian' => 'Kuitansi tanpa lampiran',
            'nama_penerima' => 'Toko Mitra',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        $response = $this->actingAs($this->verifier)
            ->postJson("/api/v1/verification/{$kuitansi->id}/action", [
                'action' => 'VERIFIED',
                'notes' => 'Mencoba meloloskan tanpa lampiran',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_ACTION');
        $this->assertEquals('MENUNGGU_VERIFIKASI', $kuitansi->fresh()->status);
    }

    public function test_process_action_verified_rejects_when_required_checklist_incomplete(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-CHK-INC-001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->verifier->id,
            'bulan_anggaran' => 3,
            'tanggal_transaksi' => '2026-03-10',
            'uraian' => 'Kuitansi checklist belum lengkap',
            'nama_penerima' => 'Toko Mitra',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        KuitansiLampiran::create([
            'kuitansi_id' => $kuitansi->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'checklist_code' => 'B.1.1',
            'nama_dokumen' => 'Nota',
            'file_path' => 'receipts/2026/03/nota2.pdf',
            'file_name' => 'nota2.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'nota-incomplete-001'),
        ]);

        $firstItem = MasterChecklistItem::where('kategori_belanja', 'B.1')
            ->where('is_required', true)
            ->first();

        $response = $this->actingAs($this->verifier)
            ->postJson("/api/v1/verification/{$kuitansi->id}/action", [
                'action' => 'VERIFIED',
                'checklist_results' => [
                    ['item_id' => $firstItem->id, 'status' => 'SESUAI'],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_ACTION');
        $this->assertEquals('MENUNGGU_VERIFIKASI', $kuitansi->fresh()->status);
    }

    public function test_process_action_rejects_invalid_source_status_and_skips_double_release_on_sla_breach(): void
    {
        // 1. Kuitansi sudah REVISI_MAYOR tidak dapat diproses ulang (anti double-release)
        $revisiMayor = Kuitansi::create([
            'nomor_kuitansi' => 'KW-DOUBLE-001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->verifier->id,
            'bulan_anggaran' => 4,
            'tanggal_transaksi' => '2026-04-10',
            'uraian' => 'Sudah revisi mayor',
            'nama_penerima' => 'Toko Mitra',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'REVISI_MAYOR',
        ]);

        $repeatRes = $this->actingAs($this->verifier)
            ->postJson("/api/v1/verification/{$revisiMayor->id}/action", [
                'action' => 'REVISION_MAJOR',
                'notes' => 'Mencoba tolak ulang',
            ]);
        $repeatRes->assertStatus(422);

        // 2. Kuitansi REVISI_MINOR yang sudah melewati SLA (is_sla_breached) tidak memanggil releaseBudget() lagi
        $slaBreached = Kuitansi::create([
            'nomor_kuitansi' => 'KW-SLA-BREACH-001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->verifier->id,
            'bulan_anggaran' => 4,
            'tanggal_transaksi' => '2026-04-10',
            'uraian' => 'SLA sudah lewat dan pagu sudah dilepas',
            'nama_penerima' => 'Toko Mitra',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'REVISI_MINOR',
            'tenggat_sla' => now()->subDay(),
        ]);

        $breachRes = $this->actingAs($this->verifier)
            ->postJson("/api/v1/verification/{$slaBreached->id}/action", [
                'action' => 'REVISION_MAJOR',
                'notes' => 'Eskalasi ke revisi mayor setelah SLA lewat',
            ]);
        $breachRes->assertStatus(200)
            ->assertJsonPath('data.new_status', 'REVISI_MAYOR');
    }
}
