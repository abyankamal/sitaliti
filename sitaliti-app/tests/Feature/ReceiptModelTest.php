<?php

namespace Tests\Feature;

use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiItem;
use App\Models\KuitansiLampiran;
use App\Models\KuitansiPajak;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptModelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UnitKerja $unit;

    private AnggaranRekening $rekening;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
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
            'pagu_murni' => 10000000.00,
            'pagu_perubahan' => 10000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);
    }

    /**
     * Test pembuatan kuitansi beserta relasi items, pajak, dan lampiran
     */
    public function test_kuitansi_dapat_disimpan_beserta_relasi_lengkap(): void
    {
        $bruto = 2220000.00;
        $totalPajak = 250000.00;
        $neto = 1970000.00; // 2.220.000 - 250.000

        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-2026-06-0001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->user->id,
            'bulan_anggaran' => 6,
            'nomor_bukti_toko' => 'INV-2026-06-888',
            'tanggal_transaksi' => '2026-06-15',
            'uraian' => 'Pembelian Kertas HVS dan Map Arsip Kegiatan Rapat',
            'nama_penerima' => 'CV Garut Jaya Mandiri',
            'npwp_penerima' => '01.234.567.8-443.000',
            'metode_pembayaran' => 'NON_TUNAI_IBC',
            'nominal_bruto' => $bruto,
            'total_pajak' => $totalPajak,
            'nominal_neto' => $neto,
            'status' => 'DRAFT',
        ]);

        // Simpan Item
        $item = KuitansiItem::create([
            'kuitansi_id' => $kuitansi->id,
            'nama_barang' => 'Kertas HVS A4 80gr',
            'volume' => 40.00,
            'satuan' => 'Rim',
            'harga_satuan' => 55500.00,
            'total_harga' => $bruto,
        ]);

        // Simpan Pajak
        $pajakPpn = KuitansiPajak::create([
            'kuitansi_id' => $kuitansi->id,
            'kode_pajak' => 'PPN_11',
            'nama_pajak' => 'PPN 11%',
            'tarif' => 0.1100,
            'tipe_tarif' => 'PERSENTASE_DPP',
            'dpp' => 2000000.00,
            'nominal' => 220000.00,
        ]);

        $pajakPph = KuitansiPajak::create([
            'kuitansi_id' => $kuitansi->id,
            'kode_pajak' => 'PPH_22',
            'nama_pajak' => 'PPh Pasal 22 (1.5%)',
            'tarif' => 0.0150,
            'tipe_tarif' => 'PERSENTASE_DPP',
            'dpp' => 2000000.00,
            'nominal' => 30000.00,
        ]);

        // Simpan Lampiran
        $lampiran = KuitansiLampiran::create([
            'kuitansi_id' => $kuitansi->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'checklist_code' => 'B.1.4',
            'nama_dokumen' => 'Nota Pembelian Toko',
            'file_path' => 'receipts/2026/06/nota_toko_888.pdf',
            'file_name' => 'nota_toko_888.pdf',
            'file_size' => 245600,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'nota_toko_888_content'),
        ]);

        // Verifikasi Relasi
        $this->assertCount(1, $kuitansi->items);
        $this->assertCount(2, $kuitansi->pajak);
        $this->assertCount(1, $kuitansi->lampiran);
        $this->assertEquals($this->unit->id, $kuitansi->unitKerja->id);
        $this->assertEquals($this->rekening->id, $kuitansi->rekening->id);
        $this->assertEquals($this->user->id, $kuitansi->creator->id);
    }

    /**
     * Test constraint anti-duplikasi nota kembar:
     * Dua berkas dengan file_hash yang sama pada rekening yang sama wajib ditolak database
     */
    public function test_penolakan_nota_kembar_berdasarkan_sha256_pada_rekening_sama(): void
    {
        $kuitansi1 = Kuitansi::create([
            'nomor_kuitansi' => 'KW-2026-06-0001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->user->id,
            'bulan_anggaran' => 6,
            'tanggal_transaksi' => '2026-06-15',
            'uraian' => 'Pengadaan ATK Nota 1',
            'nama_penerima' => 'CV Toko ATK',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'DRAFT',
        ]);

        $kuitansi2 = Kuitansi::create([
            'nomor_kuitansi' => 'KW-2026-06-0002',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->user->id,
            'bulan_anggaran' => 6,
            'tanggal_transaksi' => '2026-06-16',
            'uraian' => 'Pengadaan ATK Nota 2 (Mencoba unggah nota kembar)',
            'nama_penerima' => 'CV Toko ATK',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'DRAFT',
        ]);

        $fileHashIdentik = hash('sha256', 'konten_nota_identik_12345');

        // Unggah pertama -> Berhasil
        KuitansiLampiran::create([
            'kuitansi_id' => $kuitansi1->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'checklist_code' => 'B.1.4',
            'nama_dokumen' => 'Nota Pertama.pdf',
            'file_path' => 'receipts/2026/06/nota1.pdf',
            'file_name' => 'nota1.pdf',
            'file_size' => 120000,
            'mime_type' => 'application/pdf',
            'file_hash' => $fileHashIdentik,
        ]);

        // Unggah kedua dengan file_hash yang persis sama pada rekening yang sama -> Wajib Lempar QueryException (Unique Constraint Violation)
        $this->expectException(QueryException::class);

        KuitansiLampiran::create([
            'kuitansi_id' => $kuitansi2->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'checklist_code' => 'B.1.4',
            'nama_dokumen' => 'Nota Duplikat.pdf',
            'file_path' => 'receipts/2026/06/nota_duplikat.pdf',
            'file_name' => 'nota_duplikat.pdf',
            'file_size' => 120000,
            'mime_type' => 'application/pdf',
            'file_hash' => $fileHashIdentik,
        ]);
    }
}
