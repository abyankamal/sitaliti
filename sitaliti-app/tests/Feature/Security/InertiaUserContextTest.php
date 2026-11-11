<?php

namespace Tests\Feature\Security;

use App\Models\PptkSubkegiatanAssignment;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaUserContextTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $kecamatan;

    private UnitKerja $pataruman;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);

        $this->kecamatan = UnitKerja::where('tipe', 'KECAMATAN')->firstOrFail();
        $this->pataruman = UnitKerja::where('nama_unit', 'Kelurahan Pataruman')->firstOrFail();
    }

    /**
     * Test unauthenticated request has null auth.user.
     */
    public function test_unauthenticated_request_has_null_auth_user(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', null)
        );
    }

    /**
     * Test Lurah Pataruman receives enriched scoping context.
     */
    public function test_lurah_pataruman_receives_enriched_scoping_context(): void
    {
        $lurah = User::factory()->create([
            'unit_kerja_id' => $this->pataruman->id,
            'role' => 'LURAH',
        ]);
        $lurah->assignRole('LURAH');

        $response = $this->actingAs($lurah)->get('/receipts');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $lurah->id)
            ->where('auth.user.role', 'LURAH')
            ->where('auth.user.is_kelurahan_scoped', true)
            ->where('auth.user.is_skpd_wide', false)
            ->where('auth.user.is_kelurahan_bpp', false)
            ->where('auth.user.unit_kerja.id', $this->pataruman->id)
            ->where('auth.user.unit_kerja.nama_unit', 'Kelurahan Pataruman')
            ->where('auth.user.unit_kerja.tipe', 'KELURAHAN')
            ->where('auth.user.assigned_sub_kegiatans', [])
        );
    }

    /**
     * Test PPTK Sarpras receives assigned subkegiatan list.
     */
    public function test_pptk_sarpras_receives_assigned_subkegiatan_list(): void
    {
        $pptk = User::factory()->create([
            'unit_kerja_id' => $this->pataruman->id,
            'role' => 'PPTK',
        ]);
        $pptk->assignRole('PPTK');

        PptkSubkegiatanAssignment::create([
            'unit_kerja_id' => $this->pataruman->id,
            'user_id' => $pptk->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0002',
        ]);

        $response = $this->actingAs($pptk)->get('/receipts');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $pptk->id)
            ->where('auth.user.is_kelurahan_scoped', true)
            ->where('auth.user.assigned_sub_kegiatans', ['7.01.03.2.02.0002'])
        );
    }

    /**
     * Test BPP Kelurahan has is_kelurahan_bpp set to true.
     */
    public function test_bpp_kelurahan_has_is_kelurahan_bpp_true(): void
    {
        $bppKel = User::factory()->create([
            'unit_kerja_id' => $this->pataruman->id,
            'role' => 'BPP',
        ]);
        $bppKel->assignRole('BPP');

        $response = $this->actingAs($bppKel)->get('/receipts');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.is_kelurahan_scoped', true)
            ->where('auth.user.is_kelurahan_bpp', true)
            ->where('auth.user.is_skpd_wide', false)
        );
    }

    /**
     * Test Camat Kecamatan has is_skpd_wide set to true.
     */
    public function test_camat_kecamatan_has_is_skpd_wide_true(): void
    {
        $camat = User::factory()->create([
            'unit_kerja_id' => $this->kecamatan->id,
            'role' => 'KPA',
        ]);
        $camat->assignRole('KPA');

        $response = $this->actingAs($camat)->get('/receipts');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.is_kelurahan_scoped', false)
            ->where('auth.user.is_kelurahan_bpp', false)
            ->where('auth.user.is_skpd_wide', true)
        );
    }
}
