<?php

namespace Tests\Feature\Dashboard;

use App\Contracts\VerificationServiceInterface;
use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\ReceiptService;
use Database\Seeders\MasterChecklistSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UnitKerja $unit1;

    private UnitKerja $unit2;

    private AnggaranRekening $rekening1;

    private AnggaranRekening $rekening2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->seed(MasterChecklistSeeder::class);

        $units = UnitKerja::where('is_active', true)->orderBy('id')->get();
        $this->unit1 = $units[0];
        $this->unit2 = $units[1];

        $this->user = User::factory()->create([
            'unit_kerja_id' => $this->unit1->id,
            'role' => 'VERIFIKATOR',
        ]);

        $this->rekening1 = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit1->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Penyusunan Rencana Program',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja ATK Kantor Unit 1',
            'pagu_murni' => 12000000.00,
            'pagu_perubahan' => 12000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        $this->rekening2 = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit2->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0002',
            'nama_sub_kegiatan' => 'Pelayanan Administrasi Kelurahan',
            'kode_rekening' => '5.1.02.01.01.0025',
            'nama_rekening' => 'Belanja Cetak Unit 2',
            'pagu_murni' => 12000000.00,
            'pagu_perubahan' => 12000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        for ($m = 1; $m <= 12; $m++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $this->rekening1->id,
                'bulan' => $m,
                'alokasi_pagu' => 1000000.00,
                'terkunci_bulan' => ($m === 2) ? 250000.00 : 0.00,
                'realisasi_bulan' => ($m === 1) ? 500000.00 : 0.00,
            ]);

            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $this->rekening2->id,
                'bulan' => $m,
                'alokasi_pagu' => 1000000.00,
                'terkunci_bulan' => ($m === 2) ? 150000.00 : 0.00,
                'realisasi_bulan' => ($m === 1) ? 300000.00 : 0.00,
            ]);
        }
    }

    public function test_dashboard_summary_mathematical_consistency_with_rak_and_kas_bulanan(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/budget/summary');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_pagu' => 24000000.00,
                    'total_terkunci' => 400000.00,
                    'total_realisasi' => 800000.00,
                    'sisa_pagu' => 22800000.00,
                ],
            ]);

        $data = $response->json('data');

        // Invarian Matematis 1: Sum(alokasi 12 bulan) == total_pagu
        $totalMonthlyTarget = array_sum(array_column($data['monthly_allocation'], 'target'));
        $this->assertEquals(24000000.00, $totalMonthlyTarget);

        // Invarian Matematis 2: Sum(pagu 6 unit) == total_pagu
        $totalUnitsPagu = array_sum(array_column($data['units'], 'pagu'));
        $this->assertEquals(24000000.00, $totalUnitsPagu);

        // Invarian Matematis 3: Sisa Pagu Bebas == Total Pagu - Total Terkunci - Total Realisasi
        $this->assertEquals(
            $data['total_pagu'] - $data['total_terkunci'] - $data['total_realisasi'],
            $data['sisa_pagu']
        );
    }

    public function test_dashboard_reflects_budget_reservation_and_queue_counts_dynamically(): void
    {
        // Kondisi awal: belum ada antrean
        $resInitial = $this->actingAs($this->user)->getJson('/api/v1/budget/summary');
        $this->assertEquals(0, $resInitial->json('data.pending_verification_count'));
        $this->assertEquals(0, $resInitial->json('data.active_revisions_count'));

        // 1. Kuitansi berstatus MENUNGGU_VERIFIKASI dibuat
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-DASH-TEST-001',
            'unit_kerja_id' => $this->unit1->id,
            'anggaran_rekening_id' => $this->rekening1->id,
            'user_id' => $this->user->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-15',
            'uraian' => 'Pengadaan ATK Rapat Koordinasi',
            'nama_penerima' => 'CV Sumber Makmur',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        $resWithPending = $this->actingAs($this->user)->getJson('/api/v1/budget/summary');
        $this->assertEquals(1, $resWithPending->json('data.pending_verification_count'));
        $this->assertEquals(0, $resWithPending->json('data.active_revisions_count'));

        // 2. Kuitansi dikembalikan untuk REVISI_MINOR
        /** @var VerificationServiceInterface $verifService */
        $verifService = app(VerificationServiceInterface::class);
        $verifService->processAction(
            (string) $kuitansi->id,
            'REVISION_MINOR',
            ['notes' => 'Nota toko perlu cap basah.'],
            (string) $this->user->id
        );

        $resWithRevision = $this->actingAs($this->user)->getJson('/api/v1/budget/summary');
        // Pending berkurang jadi 0, Revisi aktif bertambah jadi 1
        $this->assertEquals(0, $resWithRevision->json('data.pending_verification_count'));
        $this->assertEquals(1, $resWithRevision->json('data.active_revisions_count'));
    }

    public function test_dashboard_recent_activities_reflects_real_audit_trail_events(): void
    {
        /** @var ReceiptService $receiptService */
        $receiptService = app(ReceiptService::class);

        $kuitansi = $receiptService->createDraft([
            'anggaran_rekening_id' => $this->rekening1->id,
            'bulan_anggaran' => 2,
            'nomor_kuitansi' => 'KW-DASH-ACT-001',
            'nomor_bukti_toko' => 'INV-DASH-001',
            'tanggal_transaksi' => '2026-02-15',
            'uraian' => 'Pengadaan Kertas Uji Recent Activities',
            'nama_penerima' => 'CV Makmur',
            'kategori_belanja' => 'B1_ATK',
            'items' => [
                ['nama_barang' => 'Kertas A4', 'volume' => 10, 'satuan' => 'Rim', 'harga_satuan' => 50000],
            ],
        ], [], $this->user);

        $response = $this->actingAs($this->user)->getJson('/api/v1/budget/summary');
        $response->assertStatus(200);

        $activities = $response->json('data.recent_activities');
        $this->assertNotEmpty($activities);
        $latest = $activities[0];

        $this->assertEquals('KW-DASH-ACT-001', $latest['id']);
        $this->assertEquals($this->unit1->nama_unit, $latest['unit']);
        $this->assertEquals('DRAFT', $latest['status']);
        $this->assertEquals(500000.00, (float) $latest['nominal']);
    }
}

