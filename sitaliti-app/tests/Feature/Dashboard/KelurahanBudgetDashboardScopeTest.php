<?php

namespace Tests\Feature\Dashboard;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiRiwayat;
use App\Models\PptkSubkegiatanAssignment;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KelurahanBudgetDashboardScopeTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $kecamatan;

    private UnitKerja $pataruman;

    private UnitKerja $sukagalih;

    private User $kpaKecamatan;

    private User $lurahPataruman;

    private User $pptkSarpras;

    private User $pptkPemberdayaan;

    private AnggaranRekening $rekKecamatan;

    private AnggaranRekening $rekSarprasPataruman;

    private AnggaranRekening $rekPemberdayaanPataruman;

    private AnggaranRekening $rekSukagalih;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);

        $this->kecamatan = UnitKerja::where('tipe', 'KECAMATAN')->first();
        $this->pataruman = UnitKerja::where('nama_unit', 'Kelurahan Pataruman')->first();
        $this->sukagalih = UnitKerja::where('nama_unit', 'Kelurahan Sukagalih')->first();

        // Roles & Permissions
        foreach (['OPERATOR', 'BPP', 'PPTK', 'VERIFIKATOR', 'LURAH', 'KPA'] as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            foreach (User::defaultRolePermissions($roleName) as $permName) {
                $p = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
                if (! $role->hasPermissionTo($p)) {
                    $role->givePermissionTo($p);
                }
            }
        }

        // Users
        $this->kpaKecamatan = User::factory()->create([
            'unit_kerja_id' => $this->kecamatan->id,
            'role' => 'KPA',
        ]);
        $this->kpaKecamatan->assignRole('KPA');

        $this->lurahPataruman = User::factory()->create([
            'unit_kerja_id' => $this->pataruman->id,
            'role' => 'LURAH',
        ]);
        $this->lurahPataruman->assignRole('LURAH');

        $this->pptkSarpras = User::factory()->create([
            'unit_kerja_id' => $this->pataruman->id,
            'role' => 'PPTK',
        ]);
        $this->pptkSarpras->assignRole('PPTK');

        $this->pptkPemberdayaan = User::factory()->create([
            'unit_kerja_id' => $this->pataruman->id,
            'role' => 'PPTK',
        ]);
        $this->pptkPemberdayaan->assignRole('PPTK');

        // Subkegiatan Assignments
        PptkSubkegiatanAssignment::create([
            'unit_kerja_id' => $this->pataruman->id,
            'user_id' => $this->pptkSarpras->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0002',
            'tahun_anggaran' => (int) date('Y'),
        ]);

        PptkSubkegiatanAssignment::create([
            'unit_kerja_id' => $this->pataruman->id,
            'user_id' => $this->pptkPemberdayaan->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0003',
            'tahun_anggaran' => (int) date('Y'),
        ]);

        // Anggaran Rekening
        $this->rekKecamatan = AnggaranRekening::create([
            'unit_kerja_id' => $this->kecamatan->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Perencanaan Program Kecamatan',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja ATK Kecamatan',
            'pagu_murni' => 50000000.00,
            'pagu_perubahan' => 50000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        $this->rekSarprasPataruman = AnggaranRekening::create([
            'unit_kerja_id' => $this->pataruman->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0002',
            'nama_sub_kegiatan' => 'Pembangunan Sarpras Kelurahan',
            'kode_rekening' => '5.2.02.01.01.0001',
            'nama_rekening' => 'Belanja Modal Bangunan Sarpras',
            'pagu_murni' => 30000000.00,
            'pagu_perubahan' => 30000000.00,
            'kategori_belanja' => 'B.2',
            'is_leaf' => true,
        ]);

        $this->rekPemberdayaanPataruman = AnggaranRekening::create([
            'unit_kerja_id' => $this->pataruman->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0003',
            'nama_sub_kegiatan' => 'Pemberdayaan Masyarakat Kelurahan',
            'kode_rekening' => '5.1.02.02.01.0010',
            'nama_rekening' => 'Belanja Pelatihan Pemberdayaan',
            'pagu_murni' => 20000000.00,
            'pagu_perubahan' => 20000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        $this->rekSukagalih = AnggaranRekening::create([
            'unit_kerja_id' => $this->sukagalih->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0002',
            'nama_sub_kegiatan' => 'Pembangunan Sarpras Kelurahan Sukagalih',
            'kode_rekening' => '5.2.02.01.01.0001',
            'nama_rekening' => 'Belanja Sarpras Sukagalih',
            'pagu_murni' => 40000000.00,
            'pagu_perubahan' => 40000000.00,
            'kategori_belanja' => 'B.2',
            'is_leaf' => true,
        ]);

        // Kas Bulanan
        foreach ([$this->rekKecamatan, $this->rekSarprasPataruman, $this->rekPemberdayaanPataruman, $this->rekSukagalih] as $rek) {
            for ($m = 1; $m <= 12; $m++) {
                AnggaranKasBulanan::create([
                    'anggaran_rekening_id' => $rek->id,
                    'bulan' => $m,
                    'alokasi_pagu' => $rek->pagu_perubahan / 12,
                    'terkunci_bulan' => 0.00,
                    'realisasi_bulan' => ($m === 1) ? 1000000.00 : 0.00,
                ]);
            }
        }
    }

    /**
     * Test Kelurahan user summary is strictly scoped to own unit.
     */
    public function test_kelurahan_user_summary_is_strictly_scoped_to_own_unit(): void
    {
        // Kuitansi di Pataruman
        $kwPataruman = Kuitansi::create([
            'nomor_kuitansi' => 'KW-PAT-001',
            'unit_kerja_id' => $this->pataruman->id,
            'anggaran_rekening_id' => $this->rekSarprasPataruman->id,
            'user_id' => $this->lurahPataruman->id,
            'bulan_anggaran' => (int) date('n'),
            'tanggal_transaksi' => '2026-02-10',
            'uraian' => 'Semen dan Pasir Drainase',
            'nama_penerima' => 'Toko Bangunan Pataruman',
            'nominal_bruto' => 5000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 5000000.00,
            'status' => 'MENUNGGU_LURAH',
        ]);

        KuitansiRiwayat::create([
            'kuitansi_id' => $kwPataruman->id,
            'user_id' => $this->lurahPataruman->id,
            'action' => 'PPTK_APPROVED',
            'status_sebelumnya' => 'MENUNGGU_PPTK',
            'status_baru' => 'MENUNGGU_LURAH',
            'catatan' => 'Disetujui PPTK Sarpras',
        ]);

        // Kuitansi di Kecamatan
        $kwKecamatan = Kuitansi::create([
            'nomor_kuitansi' => 'KW-KEC-001',
            'unit_kerja_id' => $this->kecamatan->id,
            'anggaran_rekening_id' => $this->rekKecamatan->id,
            'user_id' => $this->kpaKecamatan->id,
            'bulan_anggaran' => (int) date('n'),
            'tanggal_transaksi' => '2026-02-12',
            'uraian' => 'Konsumsi Rapat Koordinasi',
            'nama_penerima' => 'Katering Kecamatan',
            'nominal_bruto' => 1500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1500000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        KuitansiRiwayat::create([
            'kuitansi_id' => $kwKecamatan->id,
            'user_id' => $this->kpaKecamatan->id,
            'action' => 'SUBMITTED',
            'status_sebelumnya' => 'DRAFT',
            'status_baru' => 'MENUNGGU_PPTK',
            'catatan' => 'Diajukan kuitansi kecamatan',
        ]);

        $response = $this->actingAs($this->lurahPataruman)->getJson('/api/v1/budget/summary');

        $response->assertStatus(200)->assertJson(['success' => true]);

        $data = $response->json('data');

        // Total pagu Pataruman = 30jt + 20jt = 50jt (tidak termasuk 50jt Kecamatan dan 40jt Sukagalih)
        $this->assertEquals(50000000.00, (float) $data['total_pagu']);

        // Units array hanya 1 (Pataruman)
        $this->assertCount(1, $data['units']);
        $this->assertEquals($this->pataruman->id, $data['units'][0]['id']);

        // Sub activities ada 2 (Sarpras & Pemberdayaan)
        $this->assertCount(2, $data['sub_activities']);
        $codes = collect($data['sub_activities'])->pluck('code')->all();
        $this->assertContains('7.01.03.2.02.0002', $codes);
        $this->assertContains('7.01.03.2.02.0003', $codes);

        // Queue counts Pataruman
        $this->assertEquals(1, $data['queue_counts']['menunggu_lurah']);
        $this->assertEquals(0, $data['queue_counts']['menunggu_verifikasi']); // Kuitansi verifikasi di kecamatan tidak boleh terhitung

        // Recent activities hanya mencatat kuitansi Pataruman
        $this->assertCount(1, $data['recent_activities']);
        $this->assertEquals('KW-PAT-001', $data['recent_activities'][0]['id']);

        // Meta info
        $this->assertEquals(1, $data['meta']['unit_count']);
        $this->assertEquals(2, $data['meta']['sub_activity_count']);
        $this->assertEquals(2, $data['meta']['rekening_count']);
    }

    /**
     * Test Kecamatan user summary maintains full SKPD aggregation.
     */
    public function test_kecamatan_user_summary_maintains_full_skpd_aggregation(): void
    {
        $response = $this->actingAs($this->kpaKecamatan)->getJson('/api/v1/budget/summary');

        $response->assertStatus(200)->assertJson(['success' => true]);

        $data = $response->json('data');

        // Total pagu seluruh SKPD: 50jt (kecamatan) + 50jt (pataruman) + 40jt (sukagalih) = 140jt
        $this->assertEquals(140000000.00, (float) $data['total_pagu']);

        // Units count seluruhnya 6 unit kerja
        $this->assertCount(6, $data['units']);
        $this->assertEquals(6, $data['meta']['unit_count']);
    }

    /**
     * Test GET /api/v1/budget/units returns only own unit for Kelurahan and 6 units for Kecamatan.
     */
    public function test_budget_units_endpoint_scoping(): void
    {
        // Kelurahan user
        $resKel = $this->actingAs($this->lurahPataruman)->getJson('/api/v1/budget/units');
        $resKel->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(1, $resKel->json('data'));
        $this->assertEquals($this->pataruman->id, $resKel->json('data.0.id'));

        // Kecamatan user
        $resKec = $this->actingAs($this->kpaKecamatan)->getJson('/api/v1/budget/units');
        $resKec->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(6, $resKec->json('data'));
    }

    /**
     * Test PPTK subActivities respects assigned subkegiatan.
     */
    public function test_pptk_subactivities_respects_assigned_subkegiatan(): void
    {
        // PPTK Sarpras Pataruman hanya melihat Sarpras
        $resSarpras = $this->actingAs($this->pptkSarpras)->getJson('/api/v1/budget/sub-activities');
        $resSarpras->assertStatus(200);
        $dataSarpras = $resSarpras->json('data');
        $this->assertCount(1, $dataSarpras);
        $this->assertEquals('7.01.03.2.02.0002', $dataSarpras[0]['code']);

        // PPTK Pemberdayaan Pataruman hanya melihat Pemberdayaan
        $resPemberdayaan = $this->actingAs($this->pptkPemberdayaan)->getJson('/api/v1/budget/sub-activities');
        $resPemberdayaan->assertStatus(200);
        $dataPemberdayaan = $resPemberdayaan->json('data');
        $this->assertCount(1, $dataPemberdayaan);
        $this->assertEquals('7.01.03.2.02.0003', $dataPemberdayaan[0]['code']);

        // Lurah Pataruman (non-PPTK) melihat semua subkegiatan di Pataruman (2 subkegiatan)
        $resLurah = $this->actingAs($this->lurahPataruman)->getJson('/api/v1/budget/sub-activities');
        $resLurah->assertStatus(200);
        $this->assertCount(2, $resLurah->json('data'));
    }

    /**
     * Test export receipts streaming CSV is strictly scoped.
     */
    public function test_export_receipts_is_strictly_scoped(): void
    {
        // 1. Kuitansi Sarpras Pataruman
        Kuitansi::create([
            'nomor_kuitansi' => 'KW-PAT-SARPRAS-01',
            'unit_kerja_id' => $this->pataruman->id,
            'anggaran_rekening_id' => $this->rekSarprasPataruman->id,
            'user_id' => $this->lurahPataruman->id,
            'bulan_anggaran' => (int) date('n'),
            'tanggal_transaksi' => '2026-02-05',
            'uraian' => 'Material Paving Blok',
            'nama_penerima' => 'UD Paving',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'DRAFT',
        ]);

        // 2. Kuitansi Pemberdayaan Pataruman
        Kuitansi::create([
            'nomor_kuitansi' => 'KW-PAT-PEMBERDAYAAN-01',
            'unit_kerja_id' => $this->pataruman->id,
            'anggaran_rekening_id' => $this->rekPemberdayaanPataruman->id,
            'user_id' => $this->lurahPataruman->id,
            'bulan_anggaran' => (int) date('n'),
            'tanggal_transaksi' => '2026-02-06',
            'uraian' => 'Honor Narasumber',
            'nama_penerima' => 'Dr. Budi',
            'nominal_bruto' => 2000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 2000000.00,
            'status' => 'DRAFT',
        ]);

        // 3. Kuitansi Kecamatan
        Kuitansi::create([
            'nomor_kuitansi' => 'KW-KEC-OPERASIONAL-01',
            'unit_kerja_id' => $this->kecamatan->id,
            'anggaran_rekening_id' => $this->rekKecamatan->id,
            'user_id' => $this->kpaKecamatan->id,
            'bulan_anggaran' => (int) date('n'),
            'tanggal_transaksi' => '2026-02-07',
            'uraian' => 'Kertas HVS Kantor',
            'nama_penerima' => 'Toko ATK Sejahtera',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'DRAFT',
        ]);

        // Lurah Pataruman export: berisi kuitansi Pataruman, TIDAK ada kuitansi Kecamatan
        $responseLurah = $this->actingAs($this->lurahPataruman)->get('/api/v1/receipts/export');
        $responseLurah->assertStatus(200);
        $contentLurah = $responseLurah->streamedContent();
        $this->assertStringContainsString('KW-PAT-SARPRAS-01', $contentLurah);
        $this->assertStringContainsString('KW-PAT-PEMBERDAYAAN-01', $contentLurah);
        $this->assertStringNotContainsString('KW-KEC-OPERASIONAL-01', $contentLurah);

        // PPTK Sarpras export: HANYA berisi Sarpras Pataruman
        $responsePptk = $this->actingAs($this->pptkSarpras)->get('/api/v1/receipts/export');
        $responsePptk->assertStatus(200);
        $contentPptk = $responsePptk->streamedContent();
        $this->assertStringContainsString('KW-PAT-SARPRAS-01', $contentPptk);
        $this->assertStringNotContainsString('KW-PAT-PEMBERDAYAAN-01', $contentPptk);
        $this->assertStringNotContainsString('KW-KEC-OPERASIONAL-01', $contentPptk);
    }

    /**
     * Test ReceiptWebController::index passes scoped unitKerjaList and locked unit filter.
     */
    public function test_receipt_web_index_passes_scoped_unit_list_and_filters(): void
    {
        $response = $this->actingAs($this->lurahPataruman)->get('/receipts');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Receipts/Index')
            ->has('unitKerjaList', 1)
            ->where('unitKerjaList.0.id', $this->pataruman->id)
            ->where('filters.unit_kerja_id', $this->pataruman->id)
            ->has('tabCounts.menunggu_lurah')
        );
    }
}
