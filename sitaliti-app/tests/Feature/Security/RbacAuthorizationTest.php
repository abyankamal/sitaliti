<?php

namespace Tests\Feature\Security;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiLampiran;
use App\Models\MasterChecklistItem;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\MasterChecklistSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unit;

    private AnggaranRekening $rekening;

    private User $operator;

    private User $bpp;

    private User $pptk;

    private User $verifikator;

    private User $kpa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->seed(MasterChecklistSeeder::class);

        $this->unit = UnitKerja::first();

        // 1. Buat User untuk peran aktif
        $this->operator = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'BP',
        ]);

        $this->bpp = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'BP',
        ]);

        $this->pptk = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'PPTK',
        ]);

        $this->verifikator = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'VERIFIKATOR',
        ]);

        $this->kpa = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'KPA',
        ]);

        // Rekening belanja sample
        $this->rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Penyusunan Perencanaan',
            'kode_rekening' => '5.1.02.01.001.00024',
            'nama_rekening' => 'Belanja ATK',
            'pagu_murni' => 50000000.00,
            'pagu_perubahan' => 50000000.00,
            'kategori_belanja' => 'B.1',
        ]);

        for ($b = 1; $b <= 12; $b++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $this->rekening->id,
                'bulan' => $b,
                'alokasi_bulan' => 4000000.00,
                'terkunci_bulan' => 0.00,
                'realisasi_bulan' => 0.00,
            ]);
        }
    }

    public function test_unauthenticated_request_to_protected_api_is_rejected_with_401(): void
    {
        // Tanpa actingAs, akses ke endpoint API v1 wajib mengembalikan 401 Unauthorized
        $resBudget = $this->getJson('/api/v1/budget/units');
        $resBudget->assertStatus(401);

        $resReceipt = $this->postJson('/api/v1/receipts', []);
        $resReceipt->assertStatus(401);

        $resQueue = $this->getJson('/api/v1/verification/queue');
        $resQueue->assertStatus(401);

        $resBundles = $this->getJson('/api/v1/bundles');
        $resBundles->assertStatus(401);
    }

    public function test_authenticated_users_have_open_access_to_read_budget_data(): void
    {
        // Open Access Terpusat: Seluruh 5 peran dapat membaca data anggaran
        $roles = [$this->operator, $this->bpp, $this->pptk, $this->verifikator, $this->kpa];

        foreach ($roles as $user) {
            $response = $this->actingAs($user)->getJson('/api/v1/budget/units');
            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $resSummary = $this->actingAs($user)->getJson('/api/v1/budget/summary');
            $resSummary->assertStatus(200)
                ->assertJson(['success' => true]);
        }
    }

    public function test_operator_can_create_receipt_but_cannot_access_verification_or_bundles(): void
    {
        // 1. Operator BISA membuat kuitansi
        $payload = [
            'anggaran_rekening_id' => $this->rekening->id,
            'nomor_kuitansi' => 'KW-RBAC-001',
            'nomor_bukti_toko' => 'INV-001',
            'tanggal_transaksi' => '2026-02-10',
            'bulan_anggaran' => 2,
            'uraian' => 'Pengadaan Kertas HVS',
            'nama_penerima' => 'Toko Alat Tulis',
            'npwp_penerima' => '12.345.678.9-012.000',
            'metode_pembayaran' => 'NON_TUNAI_IBC',
            'items' => [
                [
                    'nama_barang' => 'Kertas A4',
                    'volume' => 10,
                    'satuan' => 'Rim',
                    'harga_satuan' => 50000,
                ],
            ],
        ];

        $resCreate = $this->actingAs($this->operator)->postJson('/api/v1/receipts', $payload);
        $resCreate->assertStatus(201);

        // 2. KPA (read-only) DITOLAK 403 saat mencoba mengakses antrean verifikasi
        $resQueue = $this->actingAs($this->kpa)->getJson('/api/v1/verification/queue');
        $resQueue->assertStatus(403);

        // 3. KPA (read-only) DITOLAK 403 saat mencoba membuat bundel SPP
        $resBundle = $this->actingAs($this->kpa)->postJson('/api/v1/bundles', [
            'jenis_pengajuan' => 'SPP-GU',
            'kuitansi_ids' => [1],
        ]);
        $resBundle->assertStatus(403);
    }

    public function test_verifikator_can_access_queue_and_verify_but_cannot_create_receipt(): void
    {
        // Buat kuitansi sample
        $kuitansi = Kuitansi::create([
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'nomor_kuitansi' => 'KW-RBAC-VERIF-01',
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-15',
            'uraian' => 'Sample kuitansi untuk verifikator',
            'nama_penerima' => 'CV Rekanan',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        KuitansiLampiran::create([
            'kuitansi_id' => $kuitansi->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'checklist_code' => 'B.1.1',
            'nama_dokumen' => 'Nota RBAC',
            'file_path' => 'receipts/2026/02/nota_rbac.pdf',
            'file_name' => 'nota_rbac.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'nota-rbac-001'),
        ]);

        $requiredChecklists = MasterChecklistItem::where('kategori_belanja', 'B.1')
            ->where('is_active', true)
            ->where('is_required', true)
            ->get()
            ->map(fn ($item) => [
                'item_id' => $item->id,
                'status' => 'SESUAI',
                'notes' => 'Lengkap',
            ])
            ->values()
            ->all();

        // 1. Verifikator BISA melihat antrean verifikasi
        $resQueue = $this->actingAs($this->verifikator)->getJson('/api/v1/verification/queue');
        $resQueue->assertStatus(200);

        // 2. Verifikator BISA memproses tindakan verifikasi
        $resAction = $this->actingAs($this->verifikator)->postJson("/api/v1/verification/{$kuitansi->id}/action", [
            'action' => 'VERIFIED',
            'notes' => 'Semua bukti fisik valid',
            'checklist_results' => $requiredChecklists,
        ]);
        $resAction->assertStatus(200);

        // 3. Verifikator BISA membuat kuitansi (validasi 422 jika payload tidak lengkap, bukan 403)
        $resCreate = $this->actingAs($this->verifikator)->postJson('/api/v1/receipts', [
            'anggaran_rekening_id' => $this->rekening->id,
            'nomor_kuitansi' => 'KW-VALID-CHECK',
        ]);
        $resCreate->assertStatus(422);

        // 4. KPA (read-only) DITOLAK 403 saat mencoba membuat kuitansi
        $resKpaCreate = $this->actingAs($this->kpa)->postJson('/api/v1/receipts', [
            'anggaran_rekening_id' => $this->rekening->id,
            'nomor_kuitansi' => 'KW-ILLEGAL',
        ]);
        $resKpaCreate->assertStatus(403);
    }

    public function test_bpp_can_create_bundle_and_kpa_can_record_sp2d(): void
    {
        // 1. Buat kuitansi lolos verifikasi
        $kuitansi = Kuitansi::create([
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->operator->id,
            'nomor_kuitansi' => 'KW-RBAC-BUNDLE-01',
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-18',
            'uraian' => 'Sample bundel SPP',
            'nama_penerima' => 'CV Mitra Sejahtera',
            'nominal_bruto' => 5000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 5000000.00,
            'status' => 'LOLOS_VERIFIKASI',
        ]);

        // 2. BPP BISA membuat bundel SPP
        $resBundle = $this->actingAs($this->bpp)->postJson('/api/v1/bundles', [
            'jenis_pengajuan' => 'SPP-GU',
            'kuitansi_ids' => [$kuitansi->id],
            'keterangan' => 'Pengajuan SPP-GU Bulan Februari',
        ]);
        $resBundle->assertStatus(201);
        $bundleId = $resBundle->json('data.id');

        // 3. BP mengajukan ke PPK dan Verifikator menyetujui Bagian A, lalu KPA (read-only) ditolak 403 dan BP mencatat SP2D
        $this->actingAs($this->bpp)->postJson("/api/v1/bundles/{$bundleId}/submit-ppk")->assertStatus(200);
        $this->actingAs($this->verifikator)->postJson("/api/v1/bundles/{$bundleId}/approve-ppk")->assertStatus(200);

        $this->actingAs($this->kpa)->postJson("/api/v1/bundles/{$bundleId}/sp2d", [
            'nomor_sp2d' => 'SP2D/TARKID/2026/02/0099',
            'tanggal_sp2d' => '2026-02-28',
        ])->assertStatus(403);

        $resSp2d = $this->actingAs($this->bpp)->postJson("/api/v1/bundles/{$bundleId}/sp2d", [
            'nomor_sp2d' => 'SP2D/TARKID/2026/02/0099',
            'tanggal_sp2d' => '2026-02-28',
        ]);
        $resSp2d->assertStatus(200);

        // 4. Verifikator DITOLAK 403 saat mencoba membuat bundel SPP
        $resIllegalBundle = $this->actingAs($this->verifikator)->postJson('/api/v1/bundles', [
            'jenis_pengajuan' => 'SPP-GU',
            'kuitansi_ids' => [$kuitansi->id],
        ]);
        $resIllegalBundle->assertStatus(403);
    }
}
