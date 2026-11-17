<?php

namespace Tests\Feature\Budget;

use App\Exceptions\PaguOverlimitException;
use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiLampiran;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\BudgetReservationService;
use App\Services\ReceiptService;
use App\Services\SettlementService;
use Database\Seeders\MasterChecklistSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BudgetConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unit;

    private User $operator;

    private User $bpp;

    private AnggaranRekening $rekening;

    private ReceiptService $receiptService;

    private SettlementService $settlementService;

    private BudgetReservationService $budgetService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->seed(MasterChecklistSeeder::class);

        $this->unit = UnitKerja::firstOrFail();

        $this->operator = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'OPERATOR',
        ]);

        $this->bpp = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'BPP',
        ]);

        $this->rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Administrasi Umum',
            'kode_rekening' => '5.1.02.01.001.00024',
            'nama_rekening' => 'Belanja Alat Tulis Kantor',
            'pagu_murni' => 120000000.00,
            'pagu_perubahan' => 120000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        for ($b = 1; $b <= 12; $b++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $this->rekening->id,
                'bulan' => $b,
                'alokasi_pagu' => 10000000.00,
                'terkunci_bulan' => 0.00,
                'realisasi_bulan' => 0.00,
            ]);
        }

        $this->receiptService = app(ReceiptService::class);
        $this->settlementService = app(SettlementService::class);
        $this->budgetService = app(BudgetReservationService::class);
    }

    /**
     * Test 1: submitToPptk mengunci baris kuitansi dan mencegah pengajuan ganda secara simultan.
     */
    public function test_submit_to_pptk_mengunci_baris_kuitansi_dan_mencegah_status_tidak_valid(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-CONCUR-SUBMIT-1',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-10',
            'uraian' => 'Pembelian Kertas F4',
            'nama_penerima' => 'Toko ATK Garut',
            'nominal_bruto' => 3000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 3000000.00,
            'status' => 'DRAFT',
        ]);

        KuitansiLampiran::create([
            'kuitansi_id' => $kuitansi->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'nama_dokumen' => 'Nota Concur Submit',
            'file_name' => 'nota_concur_submit.pdf',
            'file_path' => 'lampiran/nota_concur_submit.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'nota-concur-submit-1'),
        ]);

        // Submit pertama kali berhasil
        $submitted = $this->receiptService->submitToPptk($kuitansi->id, $this->operator);
        $this->assertEquals('MENUNGGU_PPTK', $submitted->status);

        // Periksa bahwa pagu terkunci di database
        $rakRow = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekening->id)
            ->where('bulan', 2)
            ->first();
        $this->assertEquals(3000000.00, (float) $rakRow->terkunci_bulan);

        // Pengajuan kedua kalinya pada kuitansi yang sama wajib ditolak
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Hanya kuitansi berstatus DRAFT atau REVISI_MAYOR');

        $this->receiptService->submitToPptk($kuitansi->id, $this->operator);
    }

    /**
     * Test 2: recordSipdSp2d mengunci baris bundel dan menolak pembaruan jika status bundel sudah SYNCED_SIPD (Anti Double-Booking).
     */
    public function test_record_sp2d_idempotent_dan_menolak_eksekusi_ganda_pada_status_synced_sipd(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-CONCUR-SP2D-1',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-12',
            'uraian' => 'Pengadaan Tinta Printer',
            'nama_penerima' => 'CV Sumber Makmur',
            'nominal_bruto' => 5000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 5000000.00,
            'status' => 'LOLOS_VERIFIKASI',
        ]);

        // Kunci pagu terlebih dahulu (seperti alur verifikasi)
        $this->budgetService->reserveBudget((string) $this->rekening->id, 2, 5000000.00, 2);

        // Buat bundel
        $bundle = $this->settlementService->createBundle(
            [
                'tahun_anggaran' => 2026,
                'bulan_periode' => 2,
                'jenis_bundel' => 'SPP_GU',
                'nomor_spp_sipd' => 'SPP/001/GU/2026',
            ],
            [$kuitansi->id],
            $this->bpp
        );

        $this->assertEquals('DRAFT', $bundle->status_bundel);

        $verifikator = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'VERIFIKATOR',
        ]);
        $this->settlementService->submitForPpk($bundle->id, $this->bpp);
        $this->settlementService->approveByPpk($bundle->id, [], $verifikator);

        // Eksekusi record SP2D pertama kali (berhasil)
        $syncedBundle = $this->settlementService->recordSipdSp2d($bundle->id, 'SP2D/2026/001/KIDUL');
        $this->assertEquals('SYNCED_SIPD', $syncedBundle->status_bundel);
        $this->assertEquals('SP2D/2026/001/KIDUL', $syncedBundle->nomor_sp2d_sipd);

        // Periksa mutasi pagu di database: terkunci berkurang, realisasi bertambah
        $rakRow = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekening->id)
            ->where('bulan', 2)
            ->first();
        $this->assertEquals(0.00, (float) $rakRow->terkunci_bulan);
        $this->assertEquals(5000000.00, (float) $rakRow->realisasi_bulan);

        // Eksekusi record SP2D kedua kalinya wajib melempar InvalidArgumentException (idempotensi mutlak)
        try {
            $this->settlementService->recordSipdSp2d($bundle->id, 'SP2D/2026/001/KIDUL-DUPLICATE');
            $this->fail('Harus melempar InvalidArgumentException untuk bundel yang sudah SYNCED_SIPD');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('sudah berstatus SYNCED_SIPD', $e->getMessage());
        }

        // Pastikan realisasi tidak berlipat ganda (tetap 5.000.000, bukan 10.000.000)
        $rakRowCheck = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekening->id)
            ->where('bulan', 2)
            ->first();
        $this->assertEquals(5000000.00, (float) $rakRowCheck->realisasi_bulan, 'Realisasi dilarang bertambah ganda!');
    }

    /**
     * Test 3: createBundle mendukung pencarian kuitansi via primary key integer maupun nomor_kuitansi string.
     */
    public function test_create_bundle_mendukung_pencarian_via_id_integer_maupun_nomor_kuitansi_string(): void
    {
        $kuitansi1 = Kuitansi::create([
            'nomor_kuitansi' => 'KW-SEARCH-INT-1',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-14',
            'uraian' => 'Item 1',
            'nama_penerima' => 'Toko 1',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'LOLOS_VERIFIKASI',
        ]);

        $kuitansi2 = Kuitansi::create([
            'nomor_kuitansi' => 'KW-SEARCH-STR-2',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-15',
            'uraian' => 'Item 2',
            'nama_penerima' => 'Toko 2',
            'nominal_bruto' => 2000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 2000000.00,
            'status' => 'LOLOS_VERIFIKASI',
        ]);

        // Input campuran: integer ID untuk kuitansi 1, dan string nomor_kuitansi untuk kuitansi 2
        $mixedIds = [$kuitansi1->id, 'KW-SEARCH-STR-2'];

        $bundle = $this->settlementService->createBundle(
            [
                'tahun_anggaran' => 2026,
                'bulan_periode' => 2,
                'jenis_bundel' => 'SPP_GU',
            ],
            $mixedIds,
            $this->bpp
        );

        $this->assertCount(2, $bundle->activeItems);
        $this->assertEquals(3000000.00, (float) $bundle->total_bruto);
        $this->assertEquals('DIBUNDEL', $kuitansi1->fresh()->status);
        $this->assertEquals('DIBUNDEL', $kuitansi2->fresh()->status);
    }

    /**
     * Test 4: reserveBudget mencegah overcommit saat akumulasi pagu hampir habis.
     */
    public function test_reserve_budget_concurrency_mencegah_overcommit_saat_pagu_hampir_habis(): void
    {
        // Alokasi bulan 2 adalah Rp 10.000.000, carry-over bulan 1 adalah Rp 10.000.000. Total tersedia: Rp 20.000.000
        // Kunci pertama: Rp 18.000.000
        $this->budgetService->reserveBudget((string) $this->rekening->id, 2, 18000000.00, 2);

        $context = $this->budgetService->getPopupAllocationData((string) $this->rekening->id, 2, 2);
        $this->assertEquals(2000000.00, $context['sisa_dapat_digunakan']);

        // Kunci kedua mencoba Rp 5.000.000 (melebihi sisa 2jt) -> Wajib dilempar PaguOverlimitException
        $this->expectException(PaguOverlimitException::class);
        $this->budgetService->reserveBudget((string) $this->rekening->id, 2, 5000000.00, 2);
    }
}
