<?php

namespace Tests\Feature\Api;

use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiRiwayat;
use App\Models\KuitansiVerifikasiChecklist;
use App\Models\MasterChecklistItem;
use App\Models\PengajuanSpj;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\MasterChecklistSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDeleteConstraintTest extends TestCase
{
    use RefreshDatabase;

    private User $kpaAdmin;

    private UnitKerja $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->unit = UnitKerja::first();

        $this->kpaAdmin = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'KPA',
        ]);
    }

    public function test_cannot_delete_bpp_or_ppk_user_referenced_in_pengajuan_spj(): void
    {
        $bpp = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'BPP',
        ]);

        $ppk = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'VERIFIKATOR',
        ]);

        PengajuanSpj::create([
            'nomor_bundel' => 'SPJ-DEL-001',
            'unit_kerja_id' => $this->unit->id,
            'user_id' => $bpp->id,
            'ppk_skpd_id' => $ppk->id,
            'jenis_bundel' => 'SPP_GU',
            'tahun_anggaran' => 2026,
            'bulan_periode' => 4,
            'total_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'total_neto' => 1000000.00,
            'status_bundel' => 'DISETUJUI_PPK',
        ]);

        $this->actingAs($this->kpaAdmin)
            ->deleteJson("/api/v1/users/{$bpp->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->actingAs($this->kpaAdmin)
            ->deleteJson("/api/v1/users/{$ppk->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('users', ['id' => $bpp->id]);
        $this->assertDatabaseHas('users', ['id' => $ppk->id]);
    }

    public function test_cannot_delete_user_referenced_in_riwayat_or_checklist(): void
    {
        $operator = User::factory()->create(['unit_kerja_id' => $this->unit->id, 'role' => 'OPERATOR']);
        $reviewer = User::factory()->create(['unit_kerja_id' => $this->unit->id, 'role' => 'VERIFIKATOR']);
        $checker = User::factory()->create(['unit_kerja_id' => $this->unit->id, 'role' => 'VERIFIKATOR']);

        $rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Subkegiatan Audit',
            'kode_rekening' => '5.1.02.01.01.0001',
            'nama_rekening' => 'Belanja ATK',
            'pagu_murni' => 10000000.00,
            'pagu_perubahan' => 10000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-AUDIT-DEL-001',
            'unit_kerja_id' => $this->unit->id,
            'anggaran_rekening_id' => $rekening->id,
            'user_id' => $operator->id,
            'bulan_anggaran' => 4,
            'tanggal_transaksi' => '2026-04-10',
            'uraian' => 'Uji jejak audit user',
            'nama_penerima' => 'Toko A',
            'nominal_bruto' => 100000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 100000.00,
            'status' => 'DRAFT',
        ]);

        KuitansiRiwayat::create([
            'kuitansi_id' => $kuitansi->id,
            'user_id' => $reviewer->id,
            'action' => 'VERIFIED',
            'status_lama' => 'MENUNGGU_VERIFIKASI',
            'status_baru' => 'LOLOS_VERIFIKASI',
        ]);

        $this->seed(MasterChecklistSeeder::class);
        $checklistItem = MasterChecklistItem::firstOrFail();

        KuitansiVerifikasiChecklist::create([
            'kuitansi_id' => $kuitansi->id,
            'master_checklist_item_id' => $checklistItem->id,
            'status_kesesuaian' => 'SESUAI',
            'verified_by' => $checker->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($this->kpaAdmin)
            ->deleteJson("/api/v1/users/{$reviewer->id}")
            ->assertStatus(422);

        $this->actingAs($this->kpaAdmin)
            ->deleteJson("/api/v1/users/{$checker->id}")
            ->assertStatus(422);
    }

    public function test_unused_user_can_still_be_deleted(): void
    {
        $unused = User::factory()->create([
            'unit_kerja_id' => $this->unit->id,
            'role' => 'OPERATOR',
        ]);

        $this->actingAs($this->kpaAdmin)
            ->deleteJson("/api/v1/users/{$unused->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', ['id' => $unused->id]);
    }
}
