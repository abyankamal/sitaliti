<?php

namespace Tests\Feature\Sppd;

use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiSppdPeserta;
use App\Models\Pegawai;
use App\Models\PptkSubkegiatanAssignment;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SppdReportAndExportTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    private User $verifikator;

    private User $bppA;

    private User $pptkA;

    private Kuitansi $kuitansiA;

    private Kuitansi $kuitansiB;

    private KuitansiSppdPeserta $pesertaA;

    private KuitansiSppdPeserta $pesertaB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(UnitKerjaSeeder::class);
        $units = UnitKerja::where('tipe', 'KELURAHAN')->take(2)->get();
        $this->unitA = $units[0];
        $this->unitB = $units[1];

        $this->verifikator = User::factory()->create(['role' => 'VERIFIKATOR', 'unit_kerja_id' => $this->unitA->id]);
        $this->bppA = User::factory()->create(['role' => 'BPP', 'unit_kerja_id' => $this->unitA->id]);
        $this->pptkA = User::factory()->create(['role' => 'PPTK', 'unit_kerja_id' => $this->unitA->id]);

        PptkSubkegiatanAssignment::create([
            'user_id' => $this->pptkA->id,
            'unit_kerja_id' => $this->unitA->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
        ]);

        $rekA = AnggaranRekening::create([
            'unit_kerja_id' => $this->unitA->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Subkeg A',
            'kode_rekening' => '5.1.02.04.01.0001',
            'nama_rekening' => 'Belanja SPPD A',
            'pagu_murni' => 50000000,
            'pagu_perubahan' => 50000000,
            'kategori_belanja' => 'B.4',
            'is_leaf' => true,
        ]);

        $rekB = AnggaranRekening::create([
            'unit_kerja_id' => $this->unitB->id,
            'kode_sub_kegiatan' => '7.01.01.2.02.0001',
            'nama_sub_kegiatan' => 'Subkeg B',
            'kode_rekening' => '5.1.02.04.01.0001',
            'nama_rekening' => 'Belanja SPPD B',
            'pagu_murni' => 50000000,
            'pagu_perubahan' => 50000000,
            'kategori_belanja' => 'B.4',
            'is_leaf' => true,
        ]);

        $pegawaiA = Pegawai::create([
            'unit_kerja_id' => $this->unitA->id,
            'nip' => '198501012010011001',
            'nama' => 'Pegawai A',
        ]);

        $pegawaiB = Pegawai::create([
            'unit_kerja_id' => $this->unitB->id,
            'nip' => '198501012010011002',
            'nama' => 'Pegawai B',
        ]);

        $photoPath = UploadedFile::fake()->image('berangkat.jpg')->storeAs('sppd-photos/2026/05', 'berangkat.jpg', 'local');

        $this->kuitansiA = Kuitansi::create([
            'nomor_kuitansi' => 'KW-2026-05-0101',
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $rekA->id,
            'user_id' => $this->bppA->id,
            'bulan_anggaran' => 5,
            'tanggal_transaksi' => '2026-05-10',
            'uraian' => 'SPPD A',
            'nama_penerima' => 'Pegawai A',
            'nominal_bruto' => 300000,
            'total_pajak' => 0,
            'nominal_neto' => 300000,
            'status' => 'LOLOS_VERIFIKASI',
            'sppd_foto_path' => $photoPath,
            'sppd_foto_name' => 'berangkat.jpg',
            'sppd_foto_size' => 1024,
            'sppd_foto_mime' => 'image/jpeg',
        ]);

        $this->kuitansiB = Kuitansi::create([
            'nomor_kuitansi' => 'KW-2026-05-0102',
            'unit_kerja_id' => $this->unitB->id,
            'anggaran_rekening_id' => $rekB->id,
            'user_id' => $this->verifikator->id,
            'bulan_anggaran' => 5,
            'tanggal_transaksi' => '2026-06-15',
            'uraian' => 'SPPD B',
            'nama_penerima' => 'Pegawai B',
            'nominal_bruto' => 400000,
            'total_pajak' => 0,
            'nominal_neto' => 400000,
            'status' => 'DRAFT',
        ]);

        $this->pesertaA = KuitansiSppdPeserta::create([
            'kuitansi_id' => $this->kuitansiA->id,
            'pegawai_id' => $pegawaiA->id,
            'tanggal_berangkat' => '2026-05-10',
            'tanggal_pulang' => '2026-05-12',
            'tujuan' => '=cmd|"/C calc"!A0',
        ]);

        $this->pesertaB = KuitansiSppdPeserta::create([
            'kuitansi_id' => $this->kuitansiB->id,
            'pegawai_id' => $pegawaiB->id,
            'tanggal_berangkat' => '2026-06-15',
            'tanggal_pulang' => '2026-06-16',
            'tujuan' => 'Jakarta',
        ]);
    }

    public function test_report_scoping_and_filters_work_accurately(): void
    {
        // Verifikator melihat semua (2 entri)
        $this->actingAs($this->verifikator)
            ->getJson('/api/v1/sppd/report')
            ->assertOk()
            ->assertJsonPath('data.kpi.total_perjalanan', 2);

        // Filter tanggal hanya Mei 2026 -> 1 entri
        $this->actingAs($this->verifikator)
            ->getJson('/api/v1/sppd/report?tanggal_dari=2026-05-01&tanggal_sampai=2026-05-31')
            ->assertOk()
            ->assertJsonPath('data.kpi.total_perjalanan', 1)
            ->assertJsonPath('data.report.data.0.nomor_kuitansi', 'KW-2026-05-0101');

        // BPP Kelurahan A & PPTK A hanya melihat unit A
        $this->actingAs($this->bppA)
            ->getJson('/api/v1/sppd/report')
            ->assertOk()
            ->assertJsonPath('data.kpi.total_perjalanan', 1);

        $this->actingAs($this->pptkA)
            ->getJson('/api/v1/sppd/report')
            ->assertOk()
            ->assertJsonPath('data.kpi.total_perjalanan', 1);
    }

    public function test_exports_xlsx_csv_with_formula_protection_and_pdf(): void
    {
        // CSV export & formula injection protection
        $csvRes = $this->actingAs($this->verifikator)
            ->get('/api/v1/sppd/report/export?format=csv')
            ->assertOk();
        $csvContent = $csvRes->streamedContent();
        $this->assertStringContainsString("'=cmd|", $csvContent);

        // XLSX export readable by PhpSpreadsheet
        $xlsxRes = $this->actingAs($this->verifikator)
            ->get('/api/v1/sppd/report/export?format=xlsx')
            ->assertOk();
        $tmpFile = tempnam(sys_get_temp_dir(), 'sppd_xlsx_');
        file_put_contents($tmpFile, $xlsxRes->streamedContent());
        $spreadsheet = IOFactory::load($tmpFile);
        unlink($tmpFile);
        $this->assertSame('Rekap Laporan SPPD', $spreadsheet->getActiveSheet()->getTitle());

        // PDF export
        $pdfRes = $this->actingAs($this->verifikator)
            ->get('/api/v1/sppd/report/export?format=pdf')
            ->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdfRes->getContent());
    }

    public function test_photo_preview_enforces_unit_scoping_and_anti_idor(): void
    {
        // User berwenang dapat melihat foto
        $this->actingAs($this->bppA)
            ->get("/sppd/photo/{$this->kuitansiA->id}/{$this->pesertaA->id}")
            ->assertOk();

        // IDOR: pesertaB tidak dimiliki oleh kuitansiA -> 404
        $this->actingAs($this->verifikator)
            ->get("/sppd/photo/{$this->kuitansiA->id}/{$this->pesertaB->id}")
            ->assertNotFound();

        // User unit lain ditolak 403
        $bppB = User::factory()->create(['role' => 'BPP', 'unit_kerja_id' => $this->unitB->id]);
        $this->actingAs($bppB)
            ->get("/sppd/photo/{$this->kuitansiA->id}")
            ->assertForbidden();
    }
}
