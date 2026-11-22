<?php

namespace Tests\Feature\Export;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiPajak;
use App\Models\PengajuanSpj;
use App\Models\PengajuanSpjItem;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\MasterChecklistSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptExportTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    private AnggaranRekening $rekeningAtk;

    private AnggaranRekening $rekeningMamin;

    private User $operatorA;

    private User $operatorB;

    private User $verifikator;

    private User $kpa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->seed(MasterChecklistSeeder::class);

        $units = UnitKerja::take(2)->get();
        $this->unitA = $units[0];
        $this->unitB = $units[1];

        $this->operatorA = User::factory()->create([
            'unit_kerja_id' => $this->unitA->id,
            'role' => 'BPP',
        ]);

        $this->operatorB = User::factory()->create([
            'unit_kerja_id' => $this->unitB->id,
            'role' => 'BPP',
        ]);

        $this->verifikator = User::factory()->create([
            'unit_kerja_id' => $this->unitA->id,
            'role' => 'VERIFIKATOR',
        ]);

        $this->kpa = User::factory()->create([
            'unit_kerja_id' => $this->unitA->id,
            'role' => 'KPA',
        ]);

        // Rekening Belanja ATK (B.1)
        $this->rekeningAtktk = AnggaranRekening::create([
            'unit_kerja_id' => $this->unitA->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Administrasi Perkantoran',
            'kode_rekening' => '5.1.02.01.001.00024',
            'nama_rekening' => 'Belanja Alat Tulis Kantor',
            'pagu_murni' => 20000000.00,
            'pagu_perubahan' => 20000000.00,
            'kategori_belanja' => 'B.1',
        ]);

        // Rekening Belanja Mamin (B.2)
        $this->rekeningMamin = AnggaranRekening::create([
            'unit_kerja_id' => $this->unitB->id,
            'kode_sub_kegiatan' => '7.01.01.2.02.0002',
            'nama_sub_kegiatan' => 'Fasilitasi Rapat',
            'kode_rekening' => '5.1.02.01.001.00052',
            'nama_rekening' => 'Belanja Makanan dan Minuman',
            'pagu_murni' => 15000000.00,
            'pagu_perubahan' => 15000000.00,
            'kategori_belanja' => 'B.2',
        ]);

        for ($b = 1; $b <= 12; $b++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $this->rekeningAtktk->id,
                'bulan' => $b,
                'alokasi_pagu' => 1500000.00,
                'terkunci_bulan' => 0.00,
                'realisasi_bulan' => 0.00,
            ]);

            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $this->rekeningMamin->id,
                'bulan' => $b,
                'alokasi_pagu' => 1000000.00,
                'terkunci_bulan' => 0.00,
                'realisasi_bulan' => 0.00,
            ]);
        }
    }

    public function test_unauthenticated_export_is_rejected_with_401(): void
    {
        $response = $this->getJson('/api/v1/receipts/export');
        $response->assertStatus(401);
    }

    public function test_export_returns_streamed_csv_response_with_proper_headers_and_columns(): void
    {
        $kuitansi = Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningAtktk->id,
            'user_id' => $this->operatorA->id,
            'nomor_kuitansi' => 'KW-EXP-HEADERS',
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-15',
            'uraian' => 'Pembelian Kertas HVS',
            'nama_penerima' => 'Toko Alat Tulis Garut',
            'npwp_penerima' => '01.234.567.8-443.000',
            'nominal_bruto' => 1500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1500000.00,
            'status' => 'TERVERIFIKASI',
        ]);

        $response = $this->actingAs($this->verifikator)->get('/api/v1/receipts/export?format=csv');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('rekapitulasi-kuitansi-', $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();

        // Validasi header CSV
        $this->assertStringContainsString('Nomor Kuitansi', $content);
        $this->assertStringContainsString('Tanggal Transaksi', $content);
        $this->assertStringContainsString('Unit Kerja', $content);
        $this->assertStringContainsString('Kode Rekening', $content);
        $this->assertStringContainsString('Uraian Belanja', $content);
        $this->assertStringContainsString('Nominal Bruto', $content);
        $this->assertStringContainsString('DPP', $content);
        $this->assertStringContainsString('PPN', $content);
        $this->assertStringContainsString('PPh 21', $content);
        $this->assertStringContainsString('PPh 22', $content);
        $this->assertStringContainsString('PPh 23', $content);
        $this->assertStringContainsString('PBJT', $content);
        $this->assertStringContainsString('Bea Meterai', $content);
        $this->assertStringContainsString('Nominal Neto', $content);
        $this->assertStringContainsString('Nama Rekanan', $content);
        $this->assertStringContainsString('NPWP Rekanan', $content);
        $this->assertStringContainsString('Status', $content);
        $this->assertStringContainsString('Nomor SP2D', $content);

        // Validasi data baris kuitansi
        $this->assertStringContainsString('KW-EXP-HEADERS', $content);
        $this->assertStringContainsString('Toko Alat Tulis Garut', $content);
        $this->assertStringContainsString('01.234.567.8-443.000', $content);
    }

    public function test_export_statutory_compliance_pmk59_and_bea_meterai_uu10(): void
    {
        // 1. ATK <= 2.000.000 (PMK 59/2022: PPN=0, PPh 22=0, Neto=Bruto)
        $kuitansiKecil = Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningAtktk->id,
            'user_id' => $this->operatorA->id,
            'nomor_kuitansi' => 'KW-EXP-PMK59-UNDER',
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-10',
            'uraian' => 'Pembelian Map Kertas',
            'nama_penerima' => 'CV Sumber Makmur',
            'npwp_penerima' => '12.345.678.9-443.000',
            'nominal_bruto' => 1200000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1200000.00,
            'status' => 'TERVERIFIKASI',
        ]);

        // 2. ATK > 2.000.000 (PPN 11% Rp 220.000, PPh 22 Rp 30.000)
        $kuitansiBesar = Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningAtktk->id,
            'user_id' => $this->operatorA->id,
            'nomor_kuitansi' => 'KW-EXP-PMK59-TAX',
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-11',
            'uraian' => 'Pembelian Tinta Printer',
            'nama_penerima' => 'CV Maju Jaya',
            'npwp_penerima' => '98.765.432.1-443.000',
            'nominal_bruto' => 2220000.00,
            'total_pajak' => 250000.00,
            'nominal_neto' => 1970000.00,
            'status' => 'TERVERIFIKASI',
        ]);

        KuitansiPajak::create([
            'kuitansi_id' => $kuitansiBesar->id,
            'kode_pajak' => 'PPN_11',
            'nama_pajak' => 'PPN 11%',
            'tarif' => 0.1100,
            'tipe_tarif' => 'PERSENTASE_DPP',
            'dpp' => 2000000.00,
            'nominal' => 220000.00,
        ]);

        KuitansiPajak::create([
            'kuitansi_id' => $kuitansiBesar->id,
            'kode_pajak' => 'PPH_22',
            'nama_pajak' => 'PPh Pasal 22 (1.5%)',
            'tarif' => 0.0150,
            'tipe_tarif' => 'PERSENTASE_DPP',
            'dpp' => 2000000.00,
            'nominal' => 30000.00,
        ]);

        // 3. Transaksi > 5.000.000 dengan Bea Meterai Rp 10.000 (UU 10/2020: Bea Meterai TIDAK potong Neto)
        $kuitansiMeterai = Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningAtktk->id,
            'user_id' => $this->operatorA->id,
            'nomor_kuitansi' => 'KW-EXP-METERAI',
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-12',
            'uraian' => 'Pengadaan Laptop Kantor',
            'nama_penerima' => 'PT Komputer Mandiri',
            'npwp_penerima' => '55.666.777.8-443.000',
            'nominal_bruto' => 11100000.00, // DPP 10.000.000, PPN 1.100.000, PPh 22 150.000 -> Total Pajak 1.250.000 -> Neto 9.850.000
            'total_pajak' => 1250000.00,
            'nominal_neto' => 9850000.00,
            'status' => 'TERVERIFIKASI',
        ]);

        KuitansiPajak::create([
            'kuitansi_id' => $kuitansiMeterai->id,
            'kode_pajak' => 'PPN_11',
            'nama_pajak' => 'PPN 11%',
            'tarif' => 0.1100,
            'tipe_tarif' => 'PERSENTASE_DPP',
            'dpp' => 10000000.00,
            'nominal' => 1100000.00,
        ]);

        KuitansiPajak::create([
            'kuitansi_id' => $kuitansiMeterai->id,
            'kode_pajak' => 'PPH_22',
            'nama_pajak' => 'PPh Pasal 22 (1.5%)',
            'tarif' => 0.0150,
            'tipe_tarif' => 'PERSENTASE_DPP',
            'dpp' => 10000000.00,
            'nominal' => 1500000.00,
        ]);

        KuitansiPajak::create([
            'kuitansi_id' => $kuitansiMeterai->id,
            'kode_pajak' => 'BEA_METERAI',
            'nama_pajak' => 'Bea Meterai',
            'tarif' => 10000.0000,
            'tipe_tarif' => 'FIXED',
            'dpp' => 11100000.00,
            'nominal' => 10000.00,
        ]);

        // 4. Mamin Resto (PBJT 10%, PPN = 0)
        $kuitansiMamin = Kuitansi::create([
            'unit_kerja_id' => $this->unitB->id,
            'anggaran_rekening_id' => $this->rekeningMamin->id,
            'user_id' => $this->operatorB->id,
            'nomor_kuitansi' => 'KW-EXP-PBJT-RESTO',
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-15',
            'uraian' => 'Makan Minum Rapat',
            'nama_penerima' => 'RM Padang Sedap',
            'npwp_penerima' => null,
            'nominal_bruto' => 1100000.00, // DPP 1.000.000, PBJT 100.000, Neto 1.000.000
            'total_pajak' => 100000.00,
            'nominal_neto' => 1000000.00,
            'status' => 'TERVERIFIKASI',
        ]);

        KuitansiPajak::create([
            'kuitansi_id' => $kuitansiMamin->id,
            'kode_pajak' => 'PBJT_10',
            'nama_pajak' => 'PBJT Makanan/Minuman (10%)',
            'tarif' => 0.1000,
            'tipe_tarif' => 'PERSENTASE_DPP',
            'dpp' => 1000000.00,
            'nominal' => 100000.00,
        ]);

        // 5. Mamin dengan kode pajak PBJT_MAMIN_10 (UU HKPD & TaxCalculationService)
        $kuitansiMaminResmi = Kuitansi::create([
            'unit_kerja_id' => $this->unitB->id,
            'anggaran_rekening_id' => $this->rekeningMamin->id,
            'user_id' => $this->operatorB->id,
            'nomor_kuitansi' => 'KW-EXP-PBJT-MAMIN-RESMI',
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-16',
            'uraian' => 'Snack Rapat Koordinasi',
            'nama_penerima' => 'Katering Garut Rasa',
            'npwp_penerima' => null,
            'nominal_bruto' => 550000.00,
            'total_pajak' => 50000.00,
            'nominal_neto' => 500000.00,
            'status' => 'TERVERIFIKASI',
        ]);

        KuitansiPajak::create([
            'kuitansi_id' => $kuitansiMaminResmi->id,
            'kode_pajak' => 'PBJT_MAMIN_10',
            'nama_pajak' => 'PBJT Makanan dan/atau Minuman 10%',
            'tarif' => 0.1000,
            'tipe_tarif' => 'PERSENTASE_DPP',
            'dpp' => 500000.00,
            'nominal' => 50000.00,
        ]);

        $response = $this->actingAs($this->verifikator)->get('/api/v1/receipts/export?format=csv');
        $content = $response->streamedContent();

        // Verifikasi baris kuitansi kecil (Neto = Bruto)
        $this->assertStringContainsString('KW-EXP-PMK59-UNDER', $content);
        $this->assertStringContainsString('1200000', $content);

        // Verifikasi baris kuitansi meterai (Bea Meterai tercantum 10000, Neto tetap 9850000)
        $this->assertStringContainsString('KW-EXP-METERAI', $content);
        $this->assertStringContainsString('10000', $content);
        $this->assertStringContainsString('9850000', $content);

        // Verifikasi baris kuitansi mamin (PBJT 100000, PPN 0)
        $this->assertStringContainsString('KW-EXP-PBJT-RESTO', $content);
        $this->assertStringContainsString('100000', $content);

        // Verifikasi baris kuitansi mamin resmi PBJT_MAMIN_10 di kolom CSV
        $lines = explode("\n", trim($content));
        $maminResmiRow = null;
        $meteraiRow = null;

        foreach ($lines as $line) {
            $cols = str_getcsv($line);
            if (($cols[0] ?? '') === 'KW-EXP-PBJT-MAMIN-RESMI') {
                $maminResmiRow = $cols;
            }
            if (($cols[0] ?? '') === 'KW-EXP-METERAI') {
                $meteraiRow = $cols;
            }
        }

        $this->assertNotNull($maminResmiRow, 'Baris kuitansi PBJT_MAMIN_10 wajib ditemukan pada CSV');
        $this->assertEquals('50000.00', $maminResmiRow[12], 'Kolom PBJT wajib terisi 50000.00 untuk PBJT_MAMIN_10');
        $this->assertEquals('0.00', $maminResmiRow[8], 'Kolom PPN wajib 0.00 untuk belanja mamin');
        $this->assertEquals('500000.00', $maminResmiRow[14], 'Kolom Nominal Neto wajib 500000.00');

        $this->assertNotNull($meteraiRow, 'Baris kuitansi Bea Meterai wajib ditemukan pada CSV');
        $this->assertEquals('10000.00', $meteraiRow[13], 'Kolom Bea Meterai wajib terisi 10000.00');
        $this->assertEquals('9850000.00', $meteraiRow[14], 'Kolom Neto tidak boleh berkurang oleh Bea Meterai');
    }

    public function test_export_respects_role_scoping(): void
    {
        // Kuitansi 1: Unit A (Draft milik Operator A)
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningAtktk->id,
            'user_id' => $this->operatorA->id,
            'nomor_kuitansi' => 'KW-SCOPE-DRAFT-A',
            'bulan_anggaran' => 3,
            'tanggal_transaksi' => '2026-03-01',
            'uraian' => 'Draft A',
            'nama_penerima' => 'CV A',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'DRAFT',
        ]);

        // Kuitansi 2: Unit B (Submitted milik Operator B)
        Kuitansi::create([
            'unit_kerja_id' => $this->unitB->id,
            'anggaran_rekening_id' => $this->rekeningMamin->id,
            'user_id' => $this->operatorB->id,
            'nomor_kuitansi' => 'KW-SCOPE-SUB-B',
            'bulan_anggaran' => 3,
            'tanggal_transaksi' => '2026-03-02',
            'uraian' => 'Submitted B',
            'nama_penerima' => 'CV B',
            'nominal_bruto' => 800000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 800000.00,
            'status' => 'MENUNGGU_PPTK',
        ]);

        // Operator A mengekspor: HANYA boleh memuat kuitansi dari Unit A
        $resA = $this->actingAs($this->operatorA)->get('/api/v1/receipts/export?format=csv');
        $contentA = $resA->streamedContent();

        $this->assertStringContainsString('KW-SCOPE-DRAFT-A', $contentA);
        $this->assertStringNotContainsString('KW-SCOPE-SUB-B', $contentA);

        // Verifikator mengekspor: Melihat lintas Unit A dan Unit B
        $resVerif = $this->actingAs($this->verifikator)->get('/api/v1/receipts/export?format=csv');
        $contentVerif = $resVerif->streamedContent();

        $this->assertStringContainsString('KW-SCOPE-DRAFT-A', $contentVerif);
        $this->assertStringContainsString('KW-SCOPE-SUB-B', $contentVerif);
    }

    public function test_export_applies_active_filters(): void
    {
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningAtktk->id,
            'user_id' => $this->operatorA->id,
            'nomor_kuitansi' => 'KW-FILTER-MATCH',
            'bulan_anggaran' => 4,
            'tanggal_transaksi' => '2026-04-01',
            'uraian' => 'Matching Filter',
            'nama_penerima' => 'CV Berkah',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'TERVERIFIKASI',
        ]);

        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningAtktk->id,
            'user_id' => $this->operatorA->id,
            'nomor_kuitansi' => 'KW-FILTER-DIFF-STATUS',
            'bulan_anggaran' => 4,
            'tanggal_transaksi' => '2026-04-02',
            'uraian' => 'Different Status',
            'nama_penerima' => 'CV Berkah',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'MENUNGGU_PPTK',
        ]);

        $response = $this->actingAs($this->verifikator)->get('/api/v1/receipts/export?'.http_build_query([
            'format' => 'csv',
            'status' => 'TERVERIFIKASI',
            'bulan_anggaran' => 4,
        ]));

        $content = $response->streamedContent();

        $this->assertStringContainsString('KW-FILTER-MATCH', $content);
        $this->assertStringNotContainsString('KW-FILTER-DIFF-STATUS', $content);
    }

    public function test_export_includes_sp2d_number_if_receipt_bundled(): void
    {
        $kuitansi = Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningAtktk->id,
            'user_id' => $this->operatorA->id,
            'nomor_kuitansi' => 'KW-EXP-SP2D',
            'bulan_anggaran' => 5,
            'tanggal_transaksi' => '2026-05-15',
            'uraian' => 'Kuitansi Terbundel Cair',
            'nama_penerima' => 'CV Usaha Bersama',
            'nominal_bruto' => 2000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 2000000.00,
            'status' => 'TERVERIFIKASI',
        ]);

        $bundle = PengajuanSpj::create([
            'unit_kerja_id' => $this->unitA->id,
            'user_id' => $this->operatorA->id,
            'nomor_bundel' => 'SPP-GU-EXP-001',
            'jenis_bundel' => 'SPP_GU',
            'tahun_anggaran' => 2026,
            'bulan_periode' => 5,
            'total_bruto' => 2000000.00,
            'total_pajak' => 0.00,
            'total_neto' => 2000000.00,
            'status_bundel' => 'SYNCED_SIPD',
            'nomor_sp2d_sipd' => 'SP2D/2026/05/999888',
            'tanggal_sp2d' => '2026-05-20',
        ]);

        PengajuanSpjItem::create([
            'pengajuan_spj_id' => $bundle->id,
            'kuitansi_id' => $kuitansi->id,
            'no_urut_ordner' => 1,
            'status_item' => 'AKTIF',
        ]);

        $response = $this->actingAs($this->verifikator)->get('/api/v1/receipts/export?format=csv');
        $content = $response->streamedContent();

        $this->assertStringContainsString('KW-EXP-SP2D', $content);
        $this->assertStringContainsString('SP2D/2026/05/999888', $content);
    }

    public function test_default_export_returns_xlsx_with_headings_and_total_row(): void
    {
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => AnggaranRekening::where('unit_kerja_id', $this->unitA->id)->firstOrFail()->id,
            'user_id' => $this->operatorA->id,
            'nomor_kuitansi' => 'KW-XLSX-01',
            'tanggal_transaksi' => '2026-04-10',
            'bulan_anggaran' => 4,
            'uraian' => 'Belanja Kertas HVS',
            'nominal_bruto' => 1000000,
            'total_pajak' => 0,
            'nominal_neto' => 1000000,
            'nama_penerima' => 'Toko Kertas',
            'npwp_penerima' => '01.234.567.8-443.000',
            'status' => 'LOLOS_VERIFIKASI',
        ]);

        $response = $this->actingAs($this->verifikator)->get('/api/v1/receipts/export');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_test_').'.xlsx';
        file_put_contents($tmpFile, $response->streamedContent());

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpFile);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame('Nomor Kuitansi', $sheet->getCell('A4')->getValue());
            $this->assertSame('KW-XLSX-01', $sheet->getCell('A5')->getValue());
            $this->assertSame(
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,
                $sheet->getCell('Q5')->getDataType()
            );
            $this->assertSame('01.234.567.8-443.000', $sheet->getCell('Q5')->getValue());
            $this->assertSame('TOTAL', $sheet->getCell('A6')->getValue());
            $this->assertEquals(1000000.0, (float) $sheet->getCell('G6')->getValue());
            $this->assertEquals(1000000.0, (float) $sheet->getCell('O6')->getValue());
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_xlsx_export_prevents_formula_injection_and_preserves_dash(): void
    {
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => AnggaranRekening::where('unit_kerja_id', $this->unitA->id)->firstOrFail()->id,
            'user_id' => $this->operatorA->id,
            'nomor_kuitansi' => 'KW-INJ-01',
            'tanggal_transaksi' => '2026-04-11',
            'bulan_anggaran' => 4,
            'uraian' => '=SUM(1,1)',
            'nominal_bruto' => 250000,
            'total_pajak' => 0,
            'nominal_neto' => 250000,
            'nama_penerima' => '@CMD',
            'npwp_penerima' => '-',
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($this->verifikator)->get('/api/v1/receipts/export?format=xlsx');
        $response->assertStatus(200);

        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_inj_').'.xlsx';
        file_put_contents($tmpFile, $response->streamedContent());

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpFile);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame(
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,
                $sheet->getCell('F5')->getDataType()
            );
            $this->assertSame("'=SUM(1,1)", $sheet->getCell('F5')->getValue());
            $this->assertSame("'@CMD", $sheet->getCell('P5')->getValue());
            $this->assertSame('-', $sheet->getCell('Q5')->getValue());
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_pdf_export_returns_valid_pdf_response(): void
    {
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => AnggaranRekening::where('unit_kerja_id', $this->unitA->id)->firstOrFail()->id,
            'user_id' => $this->operatorA->id,
            'nomor_kuitansi' => 'KW-PDF-01',
            'tanggal_transaksi' => '2026-04-12',
            'bulan_anggaran' => 4,
            'uraian' => 'Belanja Cetak Dokumen',
            'nominal_bruto' => 500000,
            'total_pajak' => 0,
            'nominal_neto' => 500000,
            'nama_penerima' => 'Percetakan Garut',
            'status' => 'LOLOS_VERIFIKASI',
        ]);

        $response = $this->actingAs($this->verifikator)->get('/api/v1/receipts/export?format=pdf');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_export_supports_sub_kegiatan_kode_rekening_and_date_range_filters(): void
    {
        $rekeningSub2 = AnggaranRekening::create([
            'unit_kerja_id' => $this->unitA->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0099',
            'nama_sub_kegiatan' => 'Pemeliharaan Gedung',
            'kode_rekening' => '5.1.02.03.03.0001',
            'nama_rekening' => 'Belanja Pemeliharaan Bangunan',
            'pagu_murni' => 20000000,
            'pagu_perubahan' => 20000000,
            'kategori_belanja' => 'B.5',
            'is_leaf' => true,
        ]);

        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => AnggaranRekening::where('unit_kerja_id', $this->unitA->id)->firstOrFail()->id,
            'user_id' => $this->operatorA->id,
            'nomor_kuitansi' => 'KW-FLT-APRIL',
            'tanggal_transaksi' => '2026-04-15',
            'bulan_anggaran' => 4,
            'uraian' => 'ATK April',
            'nominal_bruto' => 100000,
            'total_pajak' => 0,
            'nominal_neto' => 100000,
            'nama_penerima' => 'Toko ATK April',
            'status' => 'LOLOS_VERIFIKASI',
        ]);

        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $rekeningSub2->id,
            'user_id' => $this->operatorA->id,
            'nomor_kuitansi' => 'KW-FLT-MEI',
            'tanggal_transaksi' => '2026-05-20',
            'bulan_anggaran' => 5,
            'uraian' => 'Pemeliharaan Mei',
            'nominal_bruto' => 200000,
            'total_pajak' => 0,
            'nominal_neto' => 200000,
            'nama_penerima' => 'CV Kontraktor Mei',
            'status' => 'DRAFT',
        ]);

        $res = $this->actingAs($this->verifikator)->get('/api/v1/receipts/export?'.http_build_query([
            'format' => 'csv',
            'kode_sub_kegiatan' => '7.01.01.2.01.0099',
            'kode_rekening' => '5.1.02.03.03.0001',
            'tanggal_dari' => '2026-05-01',
            'tanggal_sampai' => '2026-05-31',
        ]));

        $res->assertStatus(200);
        $csv = $res->streamedContent();
        $this->assertStringContainsString('KW-FLT-MEI', $csv);
        $this->assertStringNotContainsString('KW-FLT-APRIL', $csv);

        // Single-bound tanggal_sampai tanpa tanggal_dari tetap valid
        $singleBound = $this->actingAs($this->verifikator)->getJson('/api/v1/receipts/export?format=csv&tanggal_sampai=2026-04-30');
        $singleBound->assertStatus(200);
        $this->assertStringContainsString('KW-FLT-APRIL', $singleBound->streamedContent());
        $this->assertStringNotContainsString('KW-FLT-MEI', $singleBound->streamedContent());

        // tanggal_sampai < tanggal_dari ditolak 422
        $invalidRange = $this->actingAs($this->verifikator)->getJson('/api/v1/receipts/export?tanggal_dari=2026-05-10&tanggal_sampai=2026-05-01');
        $invalidRange->assertStatus(422);
    }

    public function test_empty_dataset_export_returns_200_for_xlsx_and_pdf(): void
    {
        $xlsx = $this->actingAs($this->verifikator)->get('/api/v1/receipts/export?format=xlsx&tanggal_dari=2099-01-01');
        $xlsx->assertStatus(200);

        $pdf = $this->actingAs($this->verifikator)->get('/api/v1/receipts/export?format=pdf&tanggal_dari=2099-01-01');
        $pdf->assertStatus(200);
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }
}
