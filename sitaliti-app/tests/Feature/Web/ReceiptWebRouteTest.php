<?php

namespace Tests\Feature\Web;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\MasterChecklistSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReceiptWebRouteTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $verifikator;

    private User $bpp;

    private Kuitansi $kuitansi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->seed(MasterChecklistSeeder::class);

        $unit = UnitKerja::first();

        $this->operator = User::factory()->create([
            'unit_kerja_id' => $unit->id,
            'role' => 'OPERATOR',
        ]);

        $this->verifikator = User::factory()->create([
            'unit_kerja_id' => $unit->id,
            'role' => 'VERIFIKATOR',
        ]);

        $this->bpp = User::factory()->create([
            'unit_kerja_id' => $unit->id,
            'role' => 'BPP',
        ]);

        $rekening = AnggaranRekening::create([
            'unit_kerja_id' => $unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Penyusunan Rencana Program',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja ATK Kantor',
            'pagu_murni' => 20000000.00,
            'pagu_perubahan' => 20000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        for ($m = 1; $m <= 12; $m++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $rekening->id,
                'bulan' => $m,
                'alokasi_pagu' => 1500000.00,
                'terkunci_bulan' => 0.00,
                'realisasi_bulan' => 0.00,
            ]);
        }

        $this->kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-WEB-001',
            'unit_kerja_id' => $unit->id,
            'anggaran_rekening_id' => $rekening->id,
            'user_id' => $this->operator->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-15',
            'uraian' => 'Kuitansi Web Route Test',
            'nama_penerima' => 'Toko Mitra',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'LOLOS_VERIFIKASI',
            'qr_token' => 'qr-test-token-web-001',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_internal_web_routes_and_is_redirected_to_login(): void
    {
        $protectedUrls = [
            '/dashboard',
            '/receipts',
            '/receipts/create',
            '/verification',
            "/verification/{$this->kuitansi->id}/workspace",
            "/control-sheet/{$this->kuitansi->id}",
            '/bundles',
        ];

        foreach ($protectedUrls as $url) {
            $response = $this->get($url);
            $response->assertStatus(302);
            $response->assertRedirect('/login');
        }
    }

    public function test_public_qr_verification_route_is_accessible_without_auth(): void
    {
        $response = $this->get("/verify/{$this->kuitansi->qr_token}");
        $response->assertStatus(200);
    }

    public function test_authenticated_operator_can_access_receipts_and_create(): void
    {
        $resIndex = $this->actingAs($this->operator)->get('/receipts');
        $resIndex->assertStatus(200);
        $resIndex->assertInertia(fn (Assert $page) => $page
            ->component('Receipts/Index')
            ->has('receipts')
            ->has('tabCounts')
            ->has('filters')
            ->has('unitKerjaList')
        );

        $resCreate = $this->actingAs($this->operator)->get('/receipts/create');
        $resCreate->assertStatus(200);
        $resCreate->assertInertia(fn (Assert $page) => $page->component('Receipts/Create'));
    }

    public function test_operator_cannot_access_verification_queue_or_bundles(): void
    {
        $resVerif = $this->actingAs($this->operator)->get('/verification');
        $resVerif->assertStatus(403);

        $resBundle = $this->actingAs($this->operator)->get('/bundles');
        $resBundle->assertStatus(403);
    }

    public function test_verifikator_can_access_verification_workspace(): void
    {
        $resVerif = $this->actingAs($this->verifikator)->get('/verification');
        $resVerif->assertStatus(200);
        $resVerif->assertInertia(fn (Assert $page) => $page->component('Verification/Index'));

        $resWorkspace = $this->actingAs($this->verifikator)->get("/verification/{$this->kuitansi->id}/workspace");
        $resWorkspace->assertStatus(200);
        $resWorkspace->assertInertia(fn (Assert $page) => $page->component('Verification/Workspace'));
    }

    public function test_bpp_can_access_bundles_page(): void
    {
        $resBundle = $this->actingAs($this->bpp)->get('/bundles');
        $resBundle->assertStatus(200);
        $resBundle->assertInertia(fn (Assert $page) => $page->component('Bundles/Index'));
    }
}
