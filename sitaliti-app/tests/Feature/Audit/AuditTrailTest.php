<?php

namespace Tests\Feature\Audit;

use App\Contracts\SettlementServiceInterface;
use App\Contracts\VerificationServiceInterface;
use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiRiwayat;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\ReceiptService;
use Database\Seeders\MasterChecklistSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unit;

    private AnggaranRekening $rekening;

    private User $operator;

    private User $pptk;

    private User $verifikator;

    private User $bpp;

    private User $kpa;

    private ReceiptService $receiptService;

    private VerificationServiceInterface $verificationService;

    private SettlementServiceInterface $settlementService;

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
            'nama_sub_kegiatan' => 'Penyusunan Perencanaan',
            'kode_rekening' => '5.1.02.01.001.00025',
            'nama_rekening' => 'Belanja ATK Kantor Uji Audit',
            'pagu_murni' => 50000000.00,
            'pagu_perubahan' => 50000000.00,
            'kategori_belanja' => 'B.1',
        ]);

        for ($b = 1; $b <= 12; $b++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $this->rekening->id,
                'bulan' => $b,
                'alokasi_pagu' => 5000000.00,
                'terkunci_bulan' => 0.00,
                'realisasi_bulan' => 0.00,
            ]);
        }

        $this->receiptService = app(ReceiptService::class);
        $this->verificationService = app(VerificationServiceInterface::class);
        $this->settlementService = app(SettlementServiceInterface::class);
    }

    public function test_full_lifecycle_audit_trail_logging(): void
    {
        $bulan = 2;
        $nominal = 1500000.00;

        // 1. DRAFT_CREATED
        $kuitansi = $this->receiptService->createDraft([
            'anggaran_rekening_id' => $this->rekening->id,
            'bulan_anggaran' => $bulan,
            'nomor_kuitansi' => 'KW-AUDIT-001',
            'nomor_bukti_toko' => 'INV-AUDIT-001',
            'tanggal_transaksi' => '2026-02-15',
            'uraian' => 'Pengadaan ATK Kegiatan Audit',
            'nama_penerima' => 'CV Sumber Makmur',
            'kategori_belanja' => 'B1_ATK',
            'items' => [
                ['nama_barang' => 'Kertas F4', 'volume' => 30, 'satuan' => 'Rim', 'harga_satuan' => 50000],
            ],
        ], [], $this->operator);

        $riwayat1 = KuitansiRiwayat::where('kuitansi_id', $kuitansi->id)->latest('id')->first();
        $this->assertNotNull($riwayat1);
        $this->assertEquals('DRAFT_CREATED', $riwayat1->action);
        $this->assertEquals('DRAFT', $riwayat1->status_baru);
        $this->assertEquals($this->operator->id, $riwayat1->user_id);

        // 2. SUBMITTED_TO_PPTK
        $this->receiptService->submitToPptk($kuitansi->id, $this->operator);
        $riwayat2 = KuitansiRiwayat::where('kuitansi_id', $kuitansi->id)->latest('id')->first();
        $this->assertEquals('SUBMITTED_TO_PPTK', $riwayat2->action);
        $this->assertEquals('DRAFT', $riwayat2->status_lama);
        $this->assertEquals('MENUNGGU_PPTK', $riwayat2->status_baru);

        // 3. PPTK_APPROVED
        $this->receiptService->approveByPptk($kuitansi->id, 'Disetujui PPTK untuk verifikasi.', $this->pptk);
        $riwayat3 = KuitansiRiwayat::where('kuitansi_id', $kuitansi->id)->latest('id')->first();
        $this->assertEquals('PPTK_APPROVED', $riwayat3->action);
        $this->assertEquals('MENUNGGU_PPTK', $riwayat3->status_lama);
        $this->assertEquals('MENUNGGU_VERIFIKASI', $riwayat3->status_baru);
        $this->assertEquals($this->pptk->id, $riwayat3->user_id);

        // 4. VERIFIED
        $verifResult = $this->verificationService->processAction(
            (string) $kuitansi->id,
            'VERIFIED',
            ['notes' => 'Dokumen sah dan terverifikasi akuntabel.'],
            (string) $this->verifikator->id
        );
        $riwayat4 = KuitansiRiwayat::where('kuitansi_id', $kuitansi->id)->latest('id')->first();
        $this->assertEquals('VERIFIED', $riwayat4->action);
        $this->assertEquals('MENUNGGU_VERIFIKASI', $riwayat4->status_lama);
        $this->assertEquals('LOLOS_VERIFIKASI', $riwayat4->status_baru);
        $this->assertEquals($this->verifikator->id, $riwayat4->user_id);
        $this->assertNotEmpty($riwayat4->metadata['qr_token'] ?? null);

        // 5. BUNDLED
        $bundle = $this->settlementService->createBundle([
            'unit_kerja_id' => $this->unit->id,
            'tahun_anggaran' => 2026,
            'bulan_periode' => $bulan,
            'jenis_bundel' => 'SPP_GU',
        ], [$kuitansi->id], $this->bpp);

        $riwayat5 = KuitansiRiwayat::where('kuitansi_id', $kuitansi->id)->latest('id')->first();
        $this->assertEquals('BUNDLED', $riwayat5->action);
        $this->assertEquals('LOLOS_VERIFIKASI', $riwayat5->status_lama);
        $this->assertEquals('DIBUNDEL', $riwayat5->status_baru);
        $this->assertEquals($this->bpp->id, $riwayat5->user_id);

        // 6. UNBUNDLED
        $this->settlementService->unbundleReceipt($bundle->id, $kuitansi->id, 'Kuitansi dikeluarkan untuk koreksi.');
        $riwayat6 = KuitansiRiwayat::where('kuitansi_id', $kuitansi->id)->latest('id')->first();
        $this->assertEquals('UNBUNDLED', $riwayat6->action);
        $this->assertEquals('DIBUNDEL', $riwayat6->status_lama);
        $this->assertEquals('LOLOS_VERIFIKASI', $riwayat6->status_baru);

        // Bundel kembali untuk tes SP2D_SETTLED
        $bundle2 = $this->settlementService->createBundle([
            'unit_kerja_id' => $this->unit->id,
            'tahun_anggaran' => 2026,
            'bulan_periode' => $bulan,
            'jenis_bundel' => 'SPP_GU',
        ], [$kuitansi->id], $this->bpp);

        // 7. SP2D_SETTLED
        $this->settlementService->recordSipdSp2d($bundle2->id, 'SP2D-2026-00099', 'SPP-2026-00099', '2026-02-28');
        $riwayat7 = KuitansiRiwayat::where('kuitansi_id', $kuitansi->id)->latest('id')->first();
        $this->assertEquals('SP2D_SETTLED', $riwayat7->action);
        $this->assertEquals('DIBUNDEL', $riwayat7->status_lama);
        $this->assertEquals('SELESAI_SP2D', $riwayat7->status_baru);
        $this->assertEquals('SP2D-2026-00099', $riwayat7->metadata['nomor_sp2d']);

        // Pastikan urutan timeline lengkap tercatat
        $allLogs = $kuitansi->riwayat()->orderBy('id', 'asc')->get();
        $this->assertGreaterThanOrEqual(7, $allLogs->count());
    }

    public function test_rejection_and_revision_audit_trails(): void
    {
        $bulan = 2;

        // 1. PPTK_REJECTED
        $kuitansi1 = Kuitansi::create([
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'nomor_kuitansi' => 'KW-AUDIT-REJECT-01',
            'bulan_anggaran' => $bulan,
            'tanggal_transaksi' => '2026-02-10',
            'uraian' => 'Uji PPTK Reject',
            'nama_penerima' => 'CV Mitra',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'DRAFT',
        ]);

        $this->receiptService->submitToPptk($kuitansi1->id, $this->operator);
        $this->receiptService->rejectByPptk($kuitansi1->id, 'Spesifikasi teknis tidak sesuai RKA.', $this->pptk);

        $logReject = KuitansiRiwayat::where('kuitansi_id', $kuitansi1->id)->latest('id')->first();
        $this->assertEquals('PPTK_REJECTED', $logReject->action);
        $this->assertEquals('MENUNGGU_PPTK', $logReject->status_lama);
        $this->assertEquals('DRAFT', $logReject->status_baru);
        $this->assertEquals('Spesifikasi teknis tidak sesuai RKA.', $logReject->catatan);

        // 2. REVISION_MINOR
        $kuitansi2 = Kuitansi::create([
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'nomor_kuitansi' => 'KW-AUDIT-REV-02',
            'bulan_anggaran' => $bulan,
            'tanggal_transaksi' => '2026-02-10',
            'uraian' => 'Uji Revisi Minor',
            'nama_penerima' => 'CV Mitra',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        $this->verificationService->processAction(
            (string) $kuitansi2->id,
            'REVISION_MINOR',
            ['notes' => 'Cap stempel rekanan kurang jelas.'],
            (string) $this->verifikator->id
        );

        $logRevMinor = KuitansiRiwayat::where('kuitansi_id', $kuitansi2->id)->latest('id')->first();
        $this->assertEquals('REVISION_MINOR', $logRevMinor->action);
        $this->assertEquals('REVISI_MINOR', $logRevMinor->status_baru);
        $this->assertNotEmpty($logRevMinor->metadata['tenggat_sla'] ?? null);

        // 3. REVISION_MAJOR
        $kuitansi3 = Kuitansi::create([
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'nomor_kuitansi' => 'KW-AUDIT-REV-03',
            'bulan_anggaran' => $bulan,
            'tanggal_transaksi' => '2026-02-10',
            'uraian' => 'Uji Revisi Mayor',
            'nama_penerima' => 'CV Mitra',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        $this->verificationService->processAction(
            (string) $kuitansi3->id,
            'REVISION_MAJOR',
            ['notes' => 'Salah kode rekening belanja, pagu dipulihkan.'],
            (string) $this->verifikator->id
        );

        $logRevMajor = KuitansiRiwayat::where('kuitansi_id', $kuitansi3->id)->latest('id')->first();
        $this->assertEquals('REVISION_MAJOR', $logRevMajor->action);
        $this->assertEquals('REVISI_MAYOR', $logRevMajor->status_baru);
    }
}
