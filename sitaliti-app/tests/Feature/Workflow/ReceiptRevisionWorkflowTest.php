<?php

namespace Tests\Feature\Workflow;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptRevisionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unit;

    private AnggaranRekening $rekening;

    private AnggaranKasBulanan $kasBulanan;

    private User $operator;

    private User $otherOperator;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->unit = UnitKerja::create([
            'kode_unit' => '7.01.01',
            'nama_unit' => 'Kelurahan Sukagalih',
            'tipe' => 'KELURAHAN',
            'is_active' => true,
        ]);

        $this->rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Administrasi Umum',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja ATK',
            'pagu_murni' => 50000000.00,
            'pagu_perubahan' => 50000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        $this->kasBulanan = AnggaranKasBulanan::create([
            'anggaran_rekening_id' => $this->rekening->id,
            'bulan' => 2,
            'alokasi_pagu' => 10000000.00,
            'terkunci_bulan' => 0.00,
            'realisasi_bulan' => 0.00,
        ]);

        $this->operator = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'OPERATOR',
        ]);
        $this->operator->assignRole('OPERATOR');

        $this->otherOperator = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'OPERATOR',
        ]);
        $this->otherOperator->assignRole('OPERATOR');
    }

    public function test_operator_dapat_mengedit_draft_dan_menghapus_lampiran(): void
    {
        $file1 = UploadedFile::fake()->create('nota_awal.pdf', 100, 'application/pdf');

        $createRes = $this->actingAs($this->operator)->post('/api/v1/receipts', [
            'anggaran_rekening_id' => $this->rekening->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-10',
            'uraian' => 'Belanja Kertas Awal',
            'nama_penerima' => 'Toko ATK Jaya',
            'items' => [
                ['nama_barang' => 'Kertas A4', 'volume' => 10, 'harga_satuan' => 50000],
            ],
            'files' => [$file1],
            'checklist_codes' => ['B.1.1'],
            'nama_dokumen' => ['Nota Pembelian Awal'],
        ]);

        $createRes->assertStatus(201);
        $kuitansiId = $createRes->json('data.id');
        $attachmentId = $createRes->json('data.kuitansi.lampiran.0.id');

        $this->assertDatabaseHas('kuitansi_lampiran', [
            'id' => $attachmentId,
            'checklist_code' => 'B.1.1',
            'nama_dokumen' => 'Nota Pembelian Awal',
        ]);

        // Hapus lampiran lama
        $delRes = $this->actingAs($this->operator)
            ->deleteJson("/api/v1/receipts/{$kuitansiId}/attachments/{$attachmentId}");
        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('kuitansi_lampiran', ['id' => $attachmentId]);

        // Update draft dengan nominal baru & lampiran baru
        $file2 = UploadedFile::fake()->create('nota_revisi.pdf', 120, 'application/pdf');
        $updateRes = $this->actingAs($this->operator)->post("/api/v1/receipts/{$kuitansiId}/update", [
            'uraian' => 'Belanja Kertas Revisi',
            'nama_penerima' => 'Toko ATK Jaya',
            'items' => [
                ['nama_barang' => 'Kertas A4', 'volume' => 20, 'harga_satuan' => 50000],
            ],
            'files' => [$file2],
            'checklist_codes' => ['B.1.2'],
            'nama_dokumen' => ['Faktur Resmi'],
        ]);

        $updateRes->assertStatus(200);
        $this->assertEquals(1000000.0, (float) $updateRes->json('data.nominal_bruto'));
        $this->assertDatabaseHas('kuitansi_lampiran', [
            'kuitansi_id' => $kuitansiId,
            'checklist_code' => 'B.1.2',
            'nama_dokumen' => 'Faktur Resmi',
        ]);
    }

    public function test_revisi_minor_menolak_perubahan_nominal_bruto_dan_resubmit_tanpa_mengunci_ulang_pagu(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-2026-02-0001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-12',
            'uraian' => 'Belanja ATK',
            'nama_penerima' => 'CV Sumber Makmur',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'REVISI_MINOR',
        ]);

        $this->kasBulanan->update(['terkunci_bulan' => 1000000.00]);

        // Mencoba mengubah nominal bruto saat REVISI_MINOR -> harus 422
        $badUpdate = $this->actingAs($this->operator)->putJson("/api/v1/receipts/{$kuitansi->id}", [
            'uraian' => 'Ubah nominal diam-diam',
            'items' => [
                ['nama_barang' => 'Kertas A4', 'volume' => 30, 'harga_satuan' => 50000],
            ],
        ]);
        $badUpdate->assertStatus(422);

        // Edit uraian/lampiran dengan nominal bruto tetap -> harus 200
        $filePerbaikan = UploadedFile::fake()->create('lampiran_lengkap.pdf', 80, 'application/pdf');
        $goodUpdate = $this->actingAs($this->operator)->post("/api/v1/receipts/{$kuitansi->id}/update", [
            'uraian' => 'Perbaikan dokumen lampiran',
            'items' => [
                ['nama_barang' => 'Kertas A4', 'volume' => 20, 'harga_satuan' => 50000],
            ],
            'files' => [$filePerbaikan],
        ]);
        $goodUpdate->assertStatus(200);

        // Resubmit revisi minor -> kembali ke MENUNGGU_VERIFIKASI tanpa menambah terkunci_bulan
        $resubmit = $this->actingAs($this->operator)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/resubmit-revision");
        $resubmit->assertStatus(200);
        $this->assertEquals('MENUNGGU_VERIFIKASI', $resubmit->json('data.status'));

        $this->kasBulanan->refresh();
        $this->assertEquals(1000000.00, (float) $this->kasBulanan->terkunci_bulan);
    }

    public function test_revisi_mayor_dapat_diedit_penuh_dan_operator_lain_ditolak_403(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-2026-02-0002',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-14',
            'uraian' => 'Belanja ATK Mayor',
            'nama_penerima' => 'CV Sumber Makmur',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'REVISI_MAYOR',
        ]);

        // Operator lain tidak boleh mengedit draft/revisi milik operator pembuat
        $forbidden = $this->actingAs($this->otherOperator)->putJson("/api/v1/receipts/{$kuitansi->id}", [
            'items' => [
                ['nama_barang' => 'Kertas A4', 'volume' => 5, 'harga_satuan' => 50000],
            ],
        ]);
        $forbidden->assertStatus(403);

        // Operator pembuat boleh ubah nominal pada REVISI_MAYOR
        $allowed = $this->actingAs($this->operator)->putJson("/api/v1/receipts/{$kuitansi->id}", [
            'items' => [
                ['nama_barang' => 'Kertas A4', 'volume' => 10, 'harga_satuan' => 50000],
            ],
        ]);
        $allowed->assertStatus(200);
        $this->assertEquals(500000.0, (float) $allowed->json('data.nominal_bruto'));
    }
}
