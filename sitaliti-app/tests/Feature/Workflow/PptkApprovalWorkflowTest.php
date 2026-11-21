<?php

namespace Tests\Feature\Workflow;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiLampiran;
use App\Models\MasterChecklistItem;
use App\Models\PengajuanSpj;
use App\Models\PengajuanSpjItem;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\MasterChecklistSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PptkApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unit;

    private AnggaranRekening $rekening;

    private User $operator;

    private User $pptk;

    private User $verifikator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->seed(MasterChecklistSeeder::class);

        $this->unit = UnitKerja::first();

        $this->operator = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'BPP',
        ]);

        $this->pptk = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'PPTK',
        ]);

        $this->verifikator = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'VERIFIKATOR',
        ]);

        $this->rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Penyusunan Perencanaan',
            'kode_rekening' => '5.1.02.01.001.00024',
            'nama_rekening' => 'Belanja ATK Kantor',
            'pagu_murni' => 50000000.00,
            'pagu_perubahan' => 50000000.00,
            'kategori_belanja' => 'B.1',
        ]);

        for ($b = 1; $b <= 12; $b++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $this->rekening->id,
                'bulan' => $b,
                'alokasi_pagu' => 4000000.00,
                'terkunci_bulan' => 0.00,
                'realisasi_bulan' => 0.00,
            ]);
        }
    }

    public function test_full_two_tier_approval_flow_from_operator_to_pptk_and_verifikator(): void
    {
        $bulan = 2;
        $nominal = 2500000.00;

        // 1. Operator membuat draft kuitansi
        $kuitansi = Kuitansi::create([
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'nomor_kuitansi' => 'KW-PPTK-FLOW-01',
            'bulan_anggaran' => $bulan,
            'tanggal_transaksi' => '2026-02-10',
            'uraian' => 'Pembelian Kertas & Tinta Printer',
            'nama_penerima' => 'CV Sumber Makmur',
            'nominal_bruto' => $nominal,
            'total_pajak' => 0.00,
            'nominal_neto' => $nominal,
            'status' => 'DRAFT',
        ]);

        KuitansiLampiran::create([
            'kuitansi_id' => $kuitansi->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'nama_dokumen' => 'Nota Pembelian',
            'file_name' => 'nota_pptk_flow.pdf',
            'file_path' => 'lampiran/nota_pptk_flow.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'nota-pptk-flow-01'),
        ]);

        // 2. Operator mengajukan kuitansi ke PPTK (POST /submit)
        $resSubmit = $this->actingAs($this->operator)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/submit");

        $resSubmit->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'MENUNGGU_VERIFIKASI',
                ],
            ]);

        $kuitansi->update(['status' => 'MENUNGGU_PPTK']);

        // Pagu anggaran bulan 2 terkunci Rp 2.500.000
        $kas = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekening->id)
            ->where('bulan', $bulan)
            ->first();
        $this->assertEquals($nominal, (float) $kas->terkunci_bulan);

        // 3. Operator atau Verifikator TIDAK BERHAK menyetujui PPTK (403 Forbidden)
        $resIllegal = $this->actingAs($this->operator)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/pptk-approve", ['catatan' => 'Ilegal']);
        $resIllegal->assertStatus(403);

        // 4. PPTK menyetujui kuitansi (POST /pptk-approve)
        $resApprove = $this->actingAs($this->pptk)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/pptk-approve", [
                'catatan' => 'Sesuai dengan RKA dan spesifikasi teknis.',
            ]);

        $resApprove->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'MENUNGGU_VERIFIKASI',
                    'pptk_user_id' => $this->pptk->id,
                    'catatan_pptk' => 'Sesuai dengan RKA dan spesifikasi teknis.',
                ],
            ]);

        $kuitansiRefreshed = $kuitansi->fresh();
        $this->assertEquals('MENUNGGU_VERIFIKASI', $kuitansiRefreshed->status);
        $this->assertNotNull($kuitansiRefreshed->tanggal_pptk_approve);

        // Verifikasi Zero-Double-Lock: Pagu terkunci tetap persis Rp 2.500.000 (tidak berlipat ganda)
        $kasAfterApprove = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekening->id)
            ->where('bulan', $bulan)
            ->first();
        $this->assertEquals($nominal, (float) $kasAfterApprove->terkunci_bulan);

        // 5. Verifikator sekarang DAPAT memproses kuitansi tersebut (dengan lampiran & checklist wajib terpenuhi)
        KuitansiLampiran::create([
            'kuitansi_id' => $kuitansi->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'checklist_code' => 'B1-01',
            'nama_dokumen' => 'Faktur / Nota Pembelian',
            'file_name' => 'nota-atk.pdf',
            'file_path' => 'lampiran/2026/02/nota-atk.pdf',
            'file_hash' => hash('sha256', 'pptk-flow-nota-atk'),
            'mime_type' => 'application/pdf',
            'file_size' => 20480,
        ]);

        $checklistResults = MasterChecklistItem::where('kategori_belanja', 'B.1')
            ->where('is_required', true)
            ->get()
            ->map(fn ($item) => [
                'item_id' => $item->id,
                'status' => 'SESUAI',
                'notes' => 'Sesuai',
            ])
            ->values()
            ->all();

        $resVerif = $this->actingAs($this->verifikator)
            ->postJson("/api/v1/verification/{$kuitansi->id}/action", [
                'action' => 'VERIFIED',
                'notes' => 'Dokumen lengkap dan sah.',
                'checklist_results' => $checklistResults,
            ]);
        $resVerif->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'new_status' => 'LOLOS_VERIFIKASI',
                ],
            ]);
    }

    public function test_pptk_rejection_restores_locked_budget_and_returns_to_draft(): void
    {
        $bulan = 3;
        $nominal = 1750000.00;

        // 1. Buat kuitansi dan submit ke PPTK
        $kuitansi = Kuitansi::create([
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'nomor_kuitansi' => 'KW-PPTK-REJ-01',
            'bulan_anggaran' => $bulan,
            'tanggal_transaksi' => '2026-03-05',
            'uraian' => 'Konsumsi Rapat Internal',
            'nama_penerima' => 'Katering Ibu Sari',
            'nominal_bruto' => $nominal,
            'total_pajak' => 0.00,
            'nominal_neto' => $nominal,
            'status' => 'DRAFT',
        ]);

        KuitansiLampiran::create([
            'kuitansi_id' => $kuitansi->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'nama_dokumen' => 'Nota Konsumsi',
            'file_name' => 'nota_pptk_rej.pdf',
            'file_path' => 'lampiran/nota_pptk_rej.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'nota-pptk-rej-01'),
        ]);

        $this->actingAs($this->operator)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/submit");
        $kuitansi->update(['status' => 'MENUNGGU_PPTK']);

        // Pagu anggaran bulan 3 terkunci
        $kas = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekening->id)
            ->where('bulan', $bulan)
            ->first();
        $this->assertEquals($nominal, (float) $kas->terkunci_bulan);

        // 2. Operator TIDAK BERHAK menolak sebagai PPTK (403 Forbidden)
        $resIllegal = $this->actingAs($this->operator)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/pptk-reject", [
                'alasan' => 'Tolak sendiri',
            ]);
        $resIllegal->assertStatus(403);

        // 3. PPTK menolak kuitansi dengan alasan
        $resReject = $this->actingAs($this->pptk)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/pptk-reject", [
                'alasan' => 'Daftar hadir peserta rapat belum dilampirkan.',
            ]);

        $resReject->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'DRAFT',
                    'catatan_pptk' => 'Daftar hadir peserta rapat belum dilampirkan.',
                ],
            ]);

        $refreshed = $kuitansi->fresh();
        $this->assertEquals('DRAFT', $refreshed->status);
        $this->assertEquals('Daftar hadir peserta rapat belum dilampirkan.', $refreshed->catatan_pptk);

        // 4. Pastikan pagu anggaran yang terkunci telah dipulihkan (kembali ke 0.00)
        $kasAfterReject = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekening->id)
            ->where('bulan', $bulan)
            ->first();
        $this->assertEquals(0.00, (float) $kasAfterReject->terkunci_bulan);
    }

    public function test_cannot_approve_or_reject_receipt_not_in_menunggu_pptk(): void
    {
        // Kuitansi masih berstatus DRAFT (belum disubmit ke PPTK)
        $kuitansi = Kuitansi::create([
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'nomor_kuitansi' => 'KW-INVALID-STATE-01',
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-12',
            'uraian' => 'Sample kuitansi draft',
            'nama_penerima' => 'CV Rekanan',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'DRAFT',
        ]);

        // Coba approve -> 422 INVALID_STATE
        $resApprove = $this->actingAs($this->pptk)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/pptk-approve", [
                'catatan' => 'Approve langsung',
            ]);
        $resApprove->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_STATE',
                ],
            ]);

        // Coba reject -> 422 INVALID_STATE
        $resReject = $this->actingAs($this->pptk)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/pptk-reject", [
                'alasan' => 'Reject langsung',
            ]);
        $resReject->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_STATE',
                ],
            ]);
    }

    public function test_pptk_rejection_requires_minimum_5_characters_reason(): void
    {
        $kuitansi = Kuitansi::create([
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'nomor_kuitansi' => 'KW-REASON-LEN-01',
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-14',
            'uraian' => 'Sample kuitansi pending',
            'nama_penerima' => 'CV Rekanan',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'MENUNGGU_PPTK',
        ]);

        // Alasan hanya 4 karakter -> 422 Unprocessable Entity
        $response = $this->actingAs($this->pptk)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/pptk-reject", [
                'alasan' => 'Tdk!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['alasan']);
    }

    public function test_cannot_reject_or_approve_receipt_that_is_bundled_into_active_spj(): void
    {
        $kuitansi = Kuitansi::create([
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'nomor_kuitansi' => 'KW-BUNDLED-01',
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-15',
            'uraian' => 'Sample kuitansi bundled',
            'nama_penerima' => 'CV Rekanan',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'MENUNGGU_PPTK',
        ]);

        $bundle = PengajuanSpj::create([
            'unit_kerja_id' => $this->unit->id,
            'user_id' => $this->pptk->id,
            'nomor_bundel' => 'SPP-GU-TEST-001',
            'jenis_bundel' => 'SPP_GU',
            'tahun_anggaran' => 2026,
            'bulan_periode' => 2,
            'status_bundel' => 'DRAFT',
            'total_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'total_neto' => 500000.00,
        ]);

        PengajuanSpjItem::create([
            'pengajuan_spj_id' => $bundle->id,
            'kuitansi_id' => $kuitansi->id,
            'no_urut_ordner' => 1,
            'status_item' => 'AKTIF',
        ]);

        // Coba reject kuitansi terbundel -> 422 INVALID_STATE
        $resReject = $this->actingAs($this->pptk)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/pptk-reject", [
                'alasan' => 'Ditolak karena berkas kurang lengkap',
            ]);
        $resReject->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_STATE',
                ],
            ]);

        // Coba approve kuitansi terbundel -> 422 INVALID_STATE
        $resApprove = $this->actingAs($this->pptk)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/pptk-approve", [
                'catatan' => 'Disetujui kembali',
            ]);
        $resApprove->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_STATE',
                ],
            ]);
    }
}
