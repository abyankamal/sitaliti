<?php

namespace Tests\Feature\Settlement;

use App\Contracts\BudgetReservationServiceInterface;
use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\PengajuanSpj;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementBundlingTest extends TestCase
{
    use RefreshDatabase;

    private User $bppUser;

    private UnitKerja $unit;

    private AnggaranRekening $rekening;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->unit = UnitKerja::first();

        $this->bppUser = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'BPP',
        ]);

        $this->rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Koordinasi dan Penyusunan Rencana Operasional',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja Alat Tulis Kantor',
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

    private function createKuitansiLolos(string $nomor, float $bruto, float $pajak = 0.0): Kuitansi
    {
        return Kuitansi::create([
            'nomor_kuitansi' => $nomor,
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->bppUser->id,
            'bulan_anggaran' => 5,
            'tanggal_transaksi' => '2026-05-10',
            'uraian' => "Belanja untuk kuitansi {$nomor}",
            'nama_penerima' => 'CV Sukses Abadi',
            'nominal_bruto' => $bruto,
            'total_pajak' => $pajak,
            'nominal_neto' => $bruto - $pajak,
            'status' => 'LOLOS_VERIFIKASI',
        ]);
    }

    /**
     * Test pembuatan bundel SPJ menggabungkan kuitansi lolos verifikasi dan menghitung total secara akurat.
     */
    public function test_create_bundle_berhasil_menggabungkan_kuitansi_lolos_verifikasi_dan_menghitung_total(): void
    {
        $k1 = $this->createKuitansiLolos('KW-LOLOS-001', 4750000.00, 250000.00);
        $k2 = $this->createKuitansiLolos('KW-LOLOS-002', 2150000.00, 0.00);

        $payload = [
            'kuitansi_ids' => [$k1->id, $k2->id],
            'jenis_bundel' => 'SPP_GU',
            'tahun_anggaran' => 2026,
            'bulan_periode' => 5,
        ];

        $response = $this->actingAs($this->bppUser)
            ->postJson('/api/v1/bundles', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'jenis_bundel' => 'SPP_GU',
                    'total_bruto' => 6900000,
                    'total_pajak' => 250000,
                    'total_neto' => 6650000,
                    'status_bundel' => 'DRAFT',
                ],
            ]);

        $this->assertEquals('DIBUNDEL', $k1->fresh()->status);
        $this->assertEquals('DIBUNDEL', $k2->fresh()->status);

        $this->assertDatabaseHas('pengajuan_spj_item', [
            'kuitansi_id' => $k1->id,
            'status_item' => 'AKTIF',
        ]);
    }

    /**
     * Test penolakan kuitansi yang belum lolos verifikasi saat dimasukkan ke dalam bundel.
     */
    public function test_menolak_kuitansi_yang_belum_lolos_verifikasi(): void
    {
        $kDraft = Kuitansi::create([
            'nomor_kuitansi' => 'KW-MASIH-DRAFT',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->bppUser->id,
            'bulan_anggaran' => 5,
            'tanggal_transaksi' => '2026-05-10',
            'uraian' => 'Kuitansi draft belum diverifikasi',
            'nama_penerima' => 'Toko ATK',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        $payload = [
            'kuitansi_ids' => [$kDraft->id],
            'jenis_bundel' => 'SPP_GU',
        ];

        $response = $this->actingAs($this->bppUser)
            ->postJson('/api/v1/bundles', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'BUNDLE_CREATION_FAILED',
                ],
            ]);
    }

    /**
     * Test proteksi anti-klaim ganda menolak kuitansi yang sudah aktif di bundel SPJ lain.
     */
    public function test_anti_double_claim_menolak_kuitansi_aktif_yang_sama_pada_dua_bundel(): void
    {
        $k1 = $this->createKuitansiLolos('KW-DOUBLE-001', 3000000.00);

        // Bundel 1 berhasil dibuat
        $res1 = $this->actingAs($this->bppUser)->postJson('/api/v1/bundles', [
            'kuitansi_ids' => [$k1->id],
            'jenis_bundel' => 'SPP_GU',
        ]);
        $res1->assertStatus(201);

        // Bundel 2 mencoba memasukkan kuitansi yang sama -> DITOLAK
        $res2 = $this->actingAs($this->bppUser)->postJson('/api/v1/bundles', [
            'kuitansi_ids' => [$k1->id],
            'jenis_bundel' => 'SPP_GU',
        ]);

        $res2->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'BUNDLE_CREATION_FAILED',
                ],
            ]);
    }

    /**
     * Test fitur unbundle mengeluarkan kuitansi dan mengembalikan status ke LOLOS_VERIFIKASI.
     */
    public function test_unbundle_mengeluarkan_kuitansi_dan_mengembalikan_status_lolos_verifikasi(): void
    {
        $k1 = $this->createKuitansiLolos('KW-UNBUNDLE-001', 4000000.00);
        $k2 = $this->createKuitansiLolos('KW-UNBUNDLE-002', 2000000.00);

        $resCreate = $this->actingAs($this->bppUser)->postJson('/api/v1/bundles', [
            'kuitansi_ids' => [$k1->id, $k2->id],
            'jenis_bundel' => 'SPP_GU',
        ]);
        $bundleId = $resCreate->json('data.id');

        // Unbundle k1
        $resUnbundle = $this->actingAs($this->bppUser)
            ->postJson("/api/v1/bundles/{$bundleId}/unbundle/{$k1->id}", [
                'reason' => 'Perlu pengecekan faktur ulang',
            ]);

        $resUnbundle->assertStatus(200);

        // Verifikasi status k1 kembali LOLOS_VERIFIKASI
        $this->assertEquals('LOLOS_VERIFIKASI', $k1->fresh()->status);

        // Verifikasi total bundel berkurang (hanya tinggal k2 = 2.000.000)
        $bundleRefreshed = PengajuanSpj::find($bundleId);
        $this->assertEquals(2000000.00, (float) $bundleRefreshed->total_bruto);

        // Sekarang k1 dapat dimasukkan ke bundel baru lain tanpa kendala
        $resNewBundle = $this->actingAs($this->bppUser)->postJson('/api/v1/bundles', [
            'kuitansi_ids' => [$k1->id],
            'jenis_bundel' => 'SPP_GU',
        ]);
        $resNewBundle->assertStatus(201);
    }

    /**
     * Test pencatatan SP2D SIPD-RI mengeksekusi settleBudget() dan mengubah status menjadi SYNCED_SIPD.
     */
    public function test_record_sp2d_mengeksekusi_settle_budget_dan_sinkronisasi_sipd(): void
    {
        $budgetService = app(BudgetReservationServiceInterface::class);
        $nominal = 5000000.00;
        $bulan = 5;

        // 1. Kunci pagu anggaran
        $budgetService->reserveBudget((string) $this->rekening->id, $bulan, $nominal);

        // Verifikasi terkunci_bulan = 5.000.000, realisasi_bulan = 0
        $kas = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekening->id)
            ->where('bulan', $bulan)
            ->first();
        $this->assertEquals(5000000.00, (float) $kas->terkunci_bulan);
        $this->assertEquals(0.00, (float) $kas->realisasi_bulan);

        // 2. Buat kuitansi dan bundel
        $k = $this->createKuitansiLolos('KW-SIPD-001', $nominal);
        $resCreate = $this->actingAs($this->bppUser)->postJson('/api/v1/bundles', [
            'kuitansi_ids' => [$k->id],
            'jenis_bundel' => 'SPP_GU',
        ]);
        $bundleId = $resCreate->json('data.id');

        // 3. SP2D wajib ditolak (422) jika bundel masih DRAFT atau MENUNGGU_PPK
        $this->actingAs($this->bppUser)->postJson("/api/v1/bundles/{$bundleId}/sp2d", [
            'nomor_sp2d' => '0451/SP2D-GU/4.01.01/2026',
        ])->assertStatus(422);

        $this->actingAs($this->bppUser)->postJson("/api/v1/bundles/{$bundleId}/submit-ppk")
            ->assertStatus(200)
            ->assertJsonPath('data.status_bundel', 'MENUNGGU_PPK');

        $this->actingAs($this->bppUser)->postJson("/api/v1/bundles/{$bundleId}/sp2d", [
            'nomor_sp2d' => '0451/SP2D-GU/4.01.01/2026',
        ])->assertStatus(422);

        // BPP dan LURAH ditolak (403) saat mencoba approve-ppk
        $lurahUser = User::factory()->create(['unit_kerja_id' => $this->unit->id, 'role' => 'LURAH']);
        $this->actingAs($this->bppUser)->postJson("/api/v1/bundles/{$bundleId}/approve-ppk")->assertStatus(403);
        $this->actingAs($lurahUser)->postJson("/api/v1/bundles/{$bundleId}/approve-ppk")->assertStatus(403);

        // VERIFIKATOR (PPK) menyetujui 9 dokumen Bagian A
        $verifikatorUser = User::factory()->create(['unit_kerja_id' => $this->unit->id, 'role' => 'VERIFIKATOR']);
        $this->actingAs($verifikatorUser)->postJson("/api/v1/bundles/{$bundleId}/approve-ppk")
            ->assertStatus(200)
            ->assertJsonPath('data.status_bundel', 'DISETUJUI_PPK');

        // 4. Catat SP2D SIPD-RI setelah DISETUJUI_PPK
        $resSp2d = $this->actingAs($this->bppUser)->postJson("/api/v1/bundles/{$bundleId}/sp2d", [
            'nomor_sp2d' => '0451/SP2D-GU/4.01.01/2026',
            'nomor_spp' => '001/SPP-GU/4.01.01/2026',
            'tanggal_sp2d' => '2026-06-15',
        ]);

        $resSp2d->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status_bundel' => 'SYNCED_SIPD',
                    'nomor_sp2d_sipd' => '0451/SP2D-GU/4.01.01/2026',
                ],
            ]);

        // Verifikasi status kuitansi menjadi SELESAI_SP2D
        $this->assertEquals('SELESAI_SP2D', $k->fresh()->status);

        // Verifikasi kuncian pagu berpindah ke realisasi definitif di tabel anggaran_kas_bulanan
        $kasRefreshed = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekening->id)
            ->where('bulan', $bulan)
            ->first();
        $this->assertEquals(0.00, (float) $kasRefreshed->terkunci_bulan);
        $this->assertEquals(5000000.00, (float) $kasRefreshed->realisasi_bulan);
    }

    /**
     * Test unbundle ditolak (422) pada bundel non-DRAFT dan mengembalikan 404 bila bundel tidak ada.
     */
    public function test_unbundle_ditolak_jika_status_bukan_draft_dan_404_jika_bundel_tidak_ada(): void
    {
        $k = $this->createKuitansiLolos('KW-UNBUNDLE-GUARD', 2500000.00);

        $resCreate = $this->actingAs($this->bppUser)->postJson('/api/v1/bundles', [
            'kuitansi_ids' => [$k->id],
            'jenis_bundel' => 'SPP_GU',
        ]);
        $resCreate->assertStatus(201);
        $bundleId = $resCreate->json('data.id');

        // Ajukan ke PPK -> status menjadi MENUNGGU_PPK
        $this->actingAs($this->bppUser)->postJson("/api/v1/bundles/{$bundleId}/submit-ppk")
            ->assertStatus(200);

        $this->actingAs($this->bppUser)
            ->postJson("/api/v1/bundles/{$bundleId}/unbundle/{$k->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'UNBUNDLE_FAILED');

        $this->assertEquals('DIBUNDEL', $k->fresh()->status);

        // Bundel tidak ditemukan -> 404
        $this->actingAs($this->bppUser)
            ->postJson("/api/v1/bundles/999999/unbundle/{$k->id}")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'BUNDLE_NOT_FOUND');

        // Unit kerja tidak valid pada store -> 422
        $k2 = $this->createKuitansiLolos('KW-UNIT-INVALID', 1000000.00);
        $this->actingAs($this->bppUser)->postJson('/api/v1/bundles', [
            'kuitansi_ids' => [$k2->id],
            'jenis_bundel' => 'SPP_GU',
            'unit_kerja_id' => 999999,
        ])->assertStatus(422);
    }
}
