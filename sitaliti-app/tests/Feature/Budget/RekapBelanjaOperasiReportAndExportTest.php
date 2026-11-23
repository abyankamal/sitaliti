<?php

namespace Tests\Feature\Budget;

use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\MasterRekapBelanjaOperasi;
use App\Models\RekapBelanjaOperasiRekening;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class RekapBelanjaOperasiReportAndExportTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $kecamatan;

    private UnitKerja $kelurahan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kecamatan = UnitKerja::create([
            'kode_unit' => 'KEC-TK',
            'nama_unit' => 'Kecamatan Tarogong Kidul',
            'tipe' => 'KECAMATAN',
        ]);

        $this->kelurahan = UnitKerja::create([
            'kode_unit' => 'KEL-A',
            'nama_unit' => 'Kelurahan A',
            'tipe' => 'KELURAHAN',
        ]);
    }

    public function test_report_aggregates_leaf_only_unmapped_and_sp2d_realisasi_only(): void
    {
        $bp = User::factory()->create(['role' => 'BP', 'unit_kerja_id' => $this->kecamatan->id]);

        $pos = MasterRekapBelanjaOperasi::create([
            'kode' => 'OP-01',
            'nama' => '=SUM(A1:A2) Belanja ATK',
            'urutan' => 1,
        ]);

        RekapBelanjaOperasiRekening::create([
            'master_rekap_id' => $pos->id,
            'kode_rekening' => '5.1.02.01.01.0001',
        ]);

        // Header row (is_leaf = false) harus diabaikan
        AnggaranRekening::create([
            'unit_kerja_id' => $this->kecamatan->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.01',
            'nama_sub_kegiatan' => 'Sub Kegiatan A',
            'kode_rekening' => '5.1.02.01.01.0001',
            'nama_rekening' => 'Header ATK',
            'pagu_murni' => 99999999,
            'pagu_perubahan' => 99999999,
            'kategori_belanja' => 'B.1',
            'is_leaf' => false,
        ]);

        $rekMapped = AnggaranRekening::create([
            'unit_kerja_id' => $this->kecamatan->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.01',
            'nama_sub_kegiatan' => 'Sub Kegiatan A',
            'kode_rekening' => '5.1.02.01.01.0001',
            'nama_rekening' => 'Belanja ATK',
            'pagu_murni' => 10000000,
            'pagu_perubahan' => 10000000,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        // Rekening 5.1.* belum dipetakan -> masuk "Belum Dipetakan"
        AnggaranRekening::create([
            'unit_kerja_id' => $this->kelurahan->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.02',
            'nama_sub_kegiatan' => 'Sub Kegiatan Kel',
            'kode_rekening' => '5.1.02.02.01.0001',
            'nama_rekening' => 'Belanja Jasa',
            'pagu_murni' => 5000000,
            'pagu_perubahan' => 5000000,
            'kategori_belanja' => 'B.2',
            'is_leaf' => true,
        ]);

        // Kuitansi SELESAI_SP2D (masuk Realisasi)
        Kuitansi::create([
            'nomor_kuitansi' => 'KWT-001',
            'unit_kerja_id' => $this->kecamatan->id,
            'anggaran_rekening_id' => $rekMapped->id,
            'pptk_id' => $bp->id,
            'created_by' => $bp->id,
            'tanggal_transaksi' => '2026-02-10',
            'bulan_anggaran' => 2,
            'uraian' => 'Beli Kertas',
            'nominal_bruto' => 2500000,
            'nominal_neto' => 2250000,
            'status' => 'SELESAI_SP2D',
        ]);

        // Kuitansi LOLOS_VERIFIKASI (TIDAK masuk Realisasi)
        Kuitansi::create([
            'nomor_kuitansi' => 'KWT-002',
            'unit_kerja_id' => $this->kecamatan->id,
            'anggaran_rekening_id' => $rekMapped->id,
            'pptk_id' => $bp->id,
            'created_by' => $bp->id,
            'tanggal_transaksi' => '2026-02-15',
            'bulan_anggaran' => 2,
            'uraian' => 'Belum SP2D',
            'nominal_bruto' => 1000000,
            'nominal_neto' => 1000000,
            'status' => 'LOLOS_VERIFIKASI',
        ]);

        $res = $this->actingAs($bp)->getJson('/api/v1/rekap-belanja-operasi');
        $res->assertOk()
            ->assertJsonPath('data.summary.total_pagu_tahunan', 15000000)
            ->assertJsonPath('data.summary.total_realisasi_sp2d', 2500000)
            ->assertJsonPath('data.summary.total_sisa_anggaran', 12500000);

        $rows = collect($res->json('data.rows'));
        $this->assertCount(2, $rows);
        $this->assertNotNull($rows->firstWhere('kode', 'UNMAPPED'));

        // Drill-down pos
        $drillRes = $this->actingAs($bp)->getJson("/api/v1/rekap-belanja-operasi/drilldown?pos_key={$pos->id}");
        $drillRes->assertOk()
            ->assertJsonCount(1, 'data.rekenings')
            ->assertJsonCount(1, 'data.kuitansi');

        // Export Excel & sanitasi formula injection
        $xlsxRes = $this->actingAs($bp)->get('/api/v1/rekap-belanja-operasi/export?format=xlsx');
        $xlsxRes->assertOk();
        $tmpPath = sys_get_temp_dir().'/rekap-test-'.uniqid().'.xlsx';
        file_put_contents($tmpPath, $xlsxRes->streamedContent());
        $spreadsheet = IOFactory::load($tmpPath);
        @unlink($tmpPath);
        $this->assertSame("'=SUM(A1:A2) Belanja ATK", $spreadsheet->getActiveSheet()->getCell('C5')->getValue());

        // Export PDF
        $pdfRes = $this->actingAs($bp)->get('/api/v1/rekap-belanja-operasi/export?format=pdf');
        $pdfRes->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdfRes->getContent());
    }

    public function test_kelurahan_bpp_is_scoped_to_own_unit_kerja(): void
    {
        $bpp = User::factory()->create(['role' => 'BPP', 'unit_kerja_id' => $this->kelurahan->id]);

        AnggaranRekening::create([
            'unit_kerja_id' => $this->kecamatan->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.01',
            'nama_sub_kegiatan' => 'Sub Kec',
            'kode_rekening' => '5.1.02.01.01.0001',
            'nama_rekening' => 'ATK Kec',
            'pagu_murni' => 20000000,
            'pagu_perubahan' => 20000000,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        AnggaranRekening::create([
            'unit_kerja_id' => $this->kelurahan->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.02',
            'nama_sub_kegiatan' => 'Sub Kel',
            'kode_rekening' => '5.1.02.01.01.0002',
            'nama_rekening' => 'ATK Kel',
            'pagu_murni' => 3000000,
            'pagu_perubahan' => 3000000,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        // BPP mencoba filter unit_kerja_id milik kecamatan -> tetap terkunci ke kelurahan
        $res = $this->actingAs($bpp)->getJson("/api/v1/rekap-belanja-operasi?unit_kerja_id={$this->kecamatan->id}");
        $res->assertOk()
            ->assertJsonPath('data.summary.total_pagu_tahunan', 3000000);
    }
}
