<?php

namespace Tests\Feature\Workflow;

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

class ReceiptLifecycleEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unit;

    private AnggaranRekening $rekening;

    private User $operator;

    private User $pptk;

    private User $verifikator;

    private User $bpp;

    private User $kpa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->seed(MasterChecklistSeeder::class);

        $this->unit = UnitKerja::first();

        $this->operator = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'OPERATOR',
        ]);

        $this->pptk = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'PPTK',
        ]);

        $this->verifikator = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'VERIFIKATOR',
        ]);

        $this->bpp = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'BPP',
        ]);

        $this->kpa = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'KPA',
        ]);

        $this->rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Penyusunan Rencana Operasional Kantor',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja ATK dan Penggandaan',
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
     * Alur Ujung-ke-Ujung Lengkap:
     * 1. Operator membuat DRAFT kuitansi beserta rincian item & pajak.
     * 2. Kuitansi tampak di Web Controller (/receipts) & API Controller.
     * 3. Operator melakukan submit -> status MENUNGGU_PPTK & pagu kas terkunci.
     * 4. PPTK menginspeksi via drawer API show dan menyetujui -> status MENUNGGU_VERIFIKASI.
     * 5. Verifikator melakukan verifikasi -> status LOLOS_VERIFIKASI.
     * 6. BPP membundel ke dalam SPP-GU -> status DALAM_PROSES_SPJ.
     * 7. Pencatatan SP2D definitif -> status SELESAI_SP2D & pagu berpindah ke realisasi.
     * 8. Ekspor rekapitulasi streaming CSV memuat rincian kuitansi, pajak, dan nomor SP2D secara presisi.
     */
    public function test_complete_receipt_lifecycle_from_draft_to_settled_sp2d_and_export_reconciliation(): void
    {
        $bulan = 6;
        $bruto = 3300000.00;
        $ppn = 330000.00; // PPN 11%
        $pph = 45000.00;  // PPh 22 1.5%
        $neto = $bruto - ($ppn + $pph);

        // Langkah 1: Operator membuat kuitansi DRAFT
        $kuitansi = Kuitansi::create([
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'nomor_kuitansi' => 'KW-E2E-FULL-001',
            'bulan_anggaran' => $bulan,
            'tanggal_transaksi' => '2026-06-10',
            'uraian' => 'Pengadaan Kertas HVS dan Spidol Whiteboard',
            'nama_penerima' => 'CV Mandiri Stationery',
            'npwp_penerima' => '01.234.567.8-443.000',
            'nominal_bruto' => $bruto,
            'total_pajak' => $ppn + $pph,
            'nominal_neto' => $neto,
            'status' => 'DRAFT',
        ]);

        KuitansiItem::create([
            'kuitansi_id' => $kuitansi->id,
            'nama_barang' => 'Kertas HVS A4 80gr',
            'volume' => 50,
            'satuan' => 'Rim',
            'harga_satuan' => 60000.00,
            'total_harga' => 3000000.00,
        ]);

        KuitansiPajak::create([
            'kuitansi_id' => $kuitansi->id,
            'kode_pajak' => 'PPN_11',
            'nama_pajak' => 'PPN 11%',
            'tarif' => 0.1100,
            'tipe_tarif' => 'PERSENTASE_DPP',
            'dpp' => 3000000.00,
            'nominal' => $ppn,
        ]);

        KuitansiPajak::create([
            'kuitansi_id' => $kuitansi->id,
            'kode_pajak' => 'PPH_22',
            'nama_pajak' => 'PPh 22 (1.5%)',
            'tarif' => 0.0150,
            'tipe_tarif' => 'PERSENTASE_DPP',
            'dpp' => 3000000.00,
            'nominal' => $pph,
        ]);

        // Langkah 2: Kuitansi tampil di Web Index dan API Index
        $webRes = $this->actingAs($this->operator)->get('/receipts?tab=DRAFT');
        $webRes->assertStatus(200);

        $apiListRes = $this->actingAs($this->operator)->getJson('/api/v1/receipts?status=DRAFT');
        $apiListRes->assertStatus(200)
            ->assertJsonPath('data.data.0.nomor_kuitansi', 'KW-E2E-FULL-001');

        // Langkah 3: Operator submit kuitansi ke PPTK
        $submitRes = $this->actingAs($this->operator)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/submit");
        $submitRes->assertStatus(200)
            ->assertJsonPath('data.status', 'MENUNGGU_PPTK');

        $kasBulan = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekening->id)
            ->where('bulan', $bulan)
            ->first();
        $this->assertEquals($bruto, (float) $kasBulan->terkunci_bulan);
        $this->assertEquals(0.00, (float) $kasBulan->realisasi_bulan);

        // Langkah 4: PPTK menginspeksi via API show drawer & menyetujui
        $showRes = $this->actingAs($this->pptk)
            ->getJson("/api/v1/receipts/{$kuitansi->id}");
        $showRes->assertStatus(200)
            ->assertJsonPath('data.nomor_kuitansi', 'KW-E2E-FULL-001')
            ->assertJsonCount(1, 'data.items');

        $approveRes = $this->actingAs($this->pptk)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/pptk-approve", [
                'catatan' => 'Barang telah diterima lengkap oleh pengurus barang.',
            ]);
        $approveRes->assertStatus(200)
            ->assertJsonPath('data.status', 'MENUNGGU_VERIFIKASI')
            ->assertJsonPath('data.pptk_user_id', $this->pptk->id);

        // Langkah 5: Verifikator menyetujui kuitansi
        $verifRes = $this->actingAs($this->verifikator)
            ->postJson("/api/v1/verification/{$kuitansi->id}/action", [
                'action' => 'VERIFIED',
                'notes' => 'Kelengkapan administrasi dan bukti faktur terverifikasi sah.',
            ]);
        $verifRes->assertStatus(200)
            ->assertJsonPath('data.new_status', 'LOLOS_VERIFIKASI');

        // Langkah 6: BPP membundel kuitansi ke dalam SPP-GU
        $bundleRes = $this->actingAs($this->bpp)
            ->postJson('/api/v1/bundles', [
                'kuitansi_ids' => [$kuitansi->id],
                'jenis_bundel' => 'SPP_GU',
            ]);
        $bundleRes->assertStatus(201);
        $bundleId = $bundleRes->json('data.id');

        $this->assertEquals('DIBUNDEL', $kuitansi->fresh()->status);

        // Langkah 7: Penerbitan SP2D definitif
        $nomorSp2d = '0890/SP2D-GU/4.01.01/2026';
        $sp2dRes = $this->actingAs($this->bpp)
            ->postJson("/api/v1/bundles/{$bundleId}/sp2d", [
                'nomor_sp2d' => $nomorSp2d,
                'nomor_spp' => '010/SPP-GU/4.01.01/2026',
                'tanggal_sp2d' => '2026-06-25',
            ]);
        $sp2dRes->assertStatus(200)
            ->assertJsonPath('data.status_bundel', 'SYNCED_SIPD');

        $this->assertEquals('SELESAI_SP2D', $kuitansi->fresh()->status);

        // Verifikasi alokasi pagu kas: terkunci kembali 0, realisasi bertambah definitif
        $kasBulanSettled = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekening->id)
            ->where('bulan', $bulan)
            ->first();
        $this->assertEquals(0.00, (float) $kasBulanSettled->terkunci_bulan);
        $this->assertEquals($bruto, (float) $kasBulanSettled->realisasi_bulan);

        // Langkah 8: Ekspor Rekapitulasi CSV dan Rekonsiliasi Data
        $exportRes = $this->actingAs($this->bpp)
            ->getJson("/api/v1/receipts/export?month={$bulan}");
        $exportRes->assertStatus(200);

        $csvContent = $exportRes->streamedContent();

        // Verifikasi BOM UTF-8
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvContent);

        // Verifikasi data kuitansi dalam baris CSV
        $this->assertStringContainsString('KW-E2E-FULL-001', $csvContent);
        $this->assertStringContainsString('CV Mandiri Stationery', $csvContent);
        $this->assertStringContainsString('3300000', $csvContent); // Bruto
        $this->assertStringContainsString('330000', $csvContent);  // PPN
        $this->assertStringContainsString('45000', $csvContent);   // PPh 22
        $this->assertStringContainsString('2925000', $csvContent); // Neto
        $this->assertStringContainsString($nomorSp2d, $csvContent); // Nomor SP2D definitif
    }

    /**
     * Penolakan PPTK melepaskan kuncian pagu kas dan mengembalikan status ke DRAFT dengan catatan.
     */
    public function test_pptk_rejection_from_workspace_releases_budget_reservation_cleanly(): void
    {
        $bulan = 7;
        $bruto = 1750000.00;

        $kuitansi = Kuitansi::create([
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'nomor_kuitansi' => 'KW-E2E-REJECT-001',
            'bulan_anggaran' => $bulan,
            'tanggal_transaksi' => '2026-07-05',
            'uraian' => 'Konsumsi Rapat Koordinasi Wilayah',
            'nama_penerima' => 'RM Sederhana',
            'nominal_bruto' => $bruto,
            'total_pajak' => 0.00,
            'nominal_neto' => $bruto,
            'status' => 'DRAFT',
        ]);

        // Submit ke PPTK -> Kunci pagu
        $this->actingAs($this->operator)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/submit")
            ->assertStatus(200);

        $kasBefore = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekening->id)
            ->where('bulan', $bulan)
            ->first();
        $this->assertEquals($bruto, (float) $kasBefore->terkunci_bulan);

        // PPTK menolak dari drawer dengan catatan valid (>= 5 karakter)
        $rejectRes = $this->actingAs($this->pptk)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/pptk-reject", [
                'alasan' => 'Daftar hadir peserta rapat belum dilampirkan.',
            ]);

        $rejectRes->assertStatus(200)
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.catatan_pptk', 'Daftar hadir peserta rapat belum dilampirkan.');

        // Kuncian pagu kas harus kembali ke 0
        $kasAfter = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekening->id)
            ->where('bulan', $bulan)
            ->first();
        $this->assertEquals(0.00, (float) $kasAfter->terkunci_bulan);
        $this->assertEquals(0.00, (float) $kasAfter->realisasi_bulan);
    }

    /**
     * Kuitansi yang sudah masuk bundel SPJ tidak dapat ditolak oleh PPTK (Integritas Bundel).
     */
    public function test_bundled_receipt_cannot_be_rejected_by_pptk_preserving_audit_integrity(): void
    {
        $kuitansi = Kuitansi::create([
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'nomor_kuitansi' => 'KW-E2E-LOCKED-001',
            'bulan_anggaran' => 8,
            'tanggal_transaksi' => '2026-08-01',
            'uraian' => 'Sewa Tenda Kegiatan HUT RI',
            'nama_penerima' => 'CV Tenda Berkah',
            'nominal_bruto' => 4500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 4500000.00,
            'status' => 'LOLOS_VERIFIKASI',
        ]);

        // Bundel ke SPJ
        $this->actingAs($this->bpp)
            ->postJson('/api/v1/bundles', [
                'kuitansi_ids' => [$kuitansi->id],
                'jenis_bundel' => 'SPP_GU',
            ])
            ->assertStatus(201);

        // PPTK mencoba menolak kuitansi yang aktif di bundel -> ditolak sistem (422)
        $rejectRes = $this->actingAs($this->pptk)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/pptk-reject", [
                'alasan' => 'Dibatalkan sepihak oleh PPTK.',
            ]);

        $rejectRes->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATE');
    }
}
