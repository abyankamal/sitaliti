<?php

namespace Tests\Feature;

use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiVerifikasiChecklist;
use App\Models\MasterChecklistItem;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\MasterChecklistSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->seed(MasterChecklistSeeder::class);
    }

    /**
     * Test bahwa MasterChecklistSeeder mengisi seluruh 35 item untuk 6 kategori belanja
     */
    public function test_seeder_mengisi_35_checklist_items_secara_lengkap(): void
    {
        $this->assertEquals(35, MasterChecklistItem::count());

        $expectedPerCategory = [
            'B.1' => 6,
            'B.2' => 6,
            'B.3' => 6,
            'B.4' => 6,
            'B.5' => 5,
            'B.6' => 6,
        ];

        foreach ($expectedPerCategory as $cat => $expectedCount) {
            $count = MasterChecklistItem::where('kategori_belanja', $cat)->count();
            $this->assertEquals(
                $expectedCount,
                $count,
                "Kategori $cat harus memiliki tepat $expectedCount butir checklist"
            );
        }

        // Verifikasi dokumen kunci
        $this->assertDatabaseHas('master_checklist_item', [
            'kode_item' => 'DOC_B1_02',
            'nama_item' => 'Kuitansi Pembayaran Bermaterai',
            'is_required' => true,
        ]);

        $this->assertDatabaseHas('master_checklist_item', [
            'kode_item' => 'DOC_B2_04',
            'nama_item' => 'Kuitansi dan Nota Pembelian Konsumsi',
            'is_required' => true,
        ]);

        // Verifikasi keselarasan B.3 (Honorarium) dan B.4 (Perjalanan Dinas)
        $this->assertDatabaseHas('master_checklist_item', [
            'kategori_belanja' => 'B.3',
            'kode_item' => 'DOC_B3_01',
            'nama_item' => 'Surat Keputusan (SK) Tim / Narasumber',
            'is_required' => true,
        ]);

        $this->assertDatabaseHas('master_checklist_item', [
            'kategori_belanja' => 'B.3',
            'kode_item' => 'DOC_B3_05',
            'nama_item' => 'Bukti Potong PPh Pasal 21',
            'is_required' => true,
        ]);

        $this->assertDatabaseHas('master_checklist_item', [
            'kategori_belanja' => 'B.4',
            'kode_item' => 'DOC_B4_01',
            'nama_item' => 'Surat Tugas (ST) Resmi',
            'is_required' => true,
        ]);

        $this->assertDatabaseHas('master_checklist_item', [
            'kategori_belanja' => 'B.4',
            'kode_item' => 'DOC_B4_02',
            'nama_item' => 'Surat Perintah Perjalanan Dinas (SPPD) Lembar 1 & 2',
            'is_required' => true,
        ]);
    }

    /**
     * Test integrasi verifikasi checklist pada kuitansi dan penegakan constraint unik
     */
    public function test_kuitansi_verifikasi_checklist_dapat_disimpan_dan_menolak_duplikasi(): void
    {
        $unit = UnitKerja::first();
        $user = User::factory()->create(['unit_kerja_id' => $unit->id]);

        $rekening = AnggaranRekening::create([
            'unit_kerja_id' => $unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Penyusunan Dokumen Perencanaan Perangkat Daerah',
            'kode_rekening' => '5.1.02.01.001.00026',
            'nama_rekening' => 'Belanja Alat/Bahan untuk Kegiatan Kantor- Bahan Cetak',
            'pagu_murni' => 5000000.00,
            'pagu_perubahan' => 5000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-2026-06-0005',
            'unit_kerja_id' => $unit->id,
            'anggaran_rekening_id' => $rekening->id,
            'user_id' => $user->id,
            'bulan_anggaran' => 6,
            'tanggal_transaksi' => '2026-06-20',
            'uraian' => 'Pengadaan Kertas Arsip Kantor',
            'nama_penerima' => 'CV Toko ATK',
            'nominal_bruto' => 1500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1500000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        $checkItem = MasterChecklistItem::where('kode_item', 'DOC_B1_01')->firstOrFail();

        // Verifikator memeriksa item checklist
        $verifChecklist = KuitansiVerifikasiChecklist::create([
            'kuitansi_id' => $kuitansi->id,
            'master_checklist_item_id' => $checkItem->id,
            'is_verified' => true,
            'status_kesesuaian' => 'SESUAI',
            'catatan' => 'Surat pesanan lengkap bertandatangan PPTK dan Penyedia',
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        $this->assertDatabaseHas('kuitansi_verifikasi_checklist', [
            'kuitansi_id' => $kuitansi->id,
            'master_checklist_item_id' => $checkItem->id,
            'status_kesesuaian' => 'SESUAI',
        ]);

        $this->assertEquals($checkItem->nama_item, $verifChecklist->checklistItem->nama_item);
        $this->assertEquals($kuitansi->nomor_kuitansi, $verifChecklist->kuitansi->nomor_kuitansi);

        // Duplikasi butir checklist yang sama pada 1 kuitansi wajib ditolak
        $this->expectException(QueryException::class);

        KuitansiVerifikasiChecklist::create([
            'kuitansi_id' => $kuitansi->id,
            'master_checklist_item_id' => $checkItem->id,
            'is_verified' => false,
            'status_kesesuaian' => 'TIDAK_SESUAI',
        ]);
    }
}
