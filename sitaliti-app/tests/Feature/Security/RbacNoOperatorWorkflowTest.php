<?php

namespace Tests\Feature\Security;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiLampiran;
use App\Models\MasterChecklistItem;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MasterChecklistSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacNoOperatorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $kecamatan;

    private UnitKerja $kelurahanA;

    private UnitKerja $kelurahanB;

    private AnggaranRekening $rekKecamatan;

    private AnggaranRekening $rekKelurahanA;

    private AnggaranRekening $rekKelurahanB;

    private User $bppA;

    private User $bpKecamatan;

    private User $kasubag;

    private User $lurahA;

    private User $camat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->seed(MasterChecklistSeeder::class);

        $this->kecamatan = UnitKerja::where('tipe', 'KECAMATAN')->firstOrFail();
        $kelurahans = UnitKerja::where('tipe', 'KELURAHAN')->take(2)->get();
        $this->kelurahanA = $kelurahans[0];
        $this->kelurahanB = $kelurahans[1];

        $this->rekKecamatan = $this->createRekeningWithKas($this->kecamatan->id, '5.1.02.01.01.0101');
        $this->rekKelurahanA = $this->createRekeningWithKas($this->kelurahanA->id, '5.1.02.01.01.0102');
        $this->rekKelurahanB = $this->createRekeningWithKas($this->kelurahanB->id, '5.1.02.01.01.0103');

        $this->bppA = User::factory()->create(['role' => 'BPP', 'unit_kerja_id' => $this->kelurahanA->id]);
        $this->bpKecamatan = User::factory()->create(['role' => 'BP', 'unit_kerja_id' => $this->kecamatan->id]);
        $this->kasubag = User::factory()->create(['role' => 'VERIFIKATOR', 'unit_kerja_id' => $this->kecamatan->id]);
        $this->lurahA = User::factory()->create(['role' => 'LURAH', 'unit_kerja_id' => $this->kelurahanA->id]);
        $this->camat = User::factory()->create(['role' => 'KPA', 'unit_kerja_id' => $this->kecamatan->id]);
    }

    private function createRekeningWithKas(int $unitId, string $kodeRek): AnggaranRekening
    {
        $rek = AnggaranRekening::create([
            'unit_kerja_id' => $unitId,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Kegiatan Test RBAC',
            'kode_rekening' => $kodeRek,
            'nama_rekening' => 'Belanja ATK Test',
            'pagu_murni' => 12000000,
            'pagu_perubahan' => 12000000,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        for ($m = 1; $m <= 12; $m++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $rek->id,
                'bulan' => $m,
                'alokasi_pagu' => 1000000,
                'terkunci_bulan' => 0,
                'realisasi_bulan' => 0,
            ]);
        }

        return $rek;
    }

    private function createDraftWithAttachment(User $creator, AnggaranRekening $rek, string $nomor): Kuitansi
    {
        $k = Kuitansi::create([
            'nomor_kuitansi' => $nomor,
            'unit_kerja_id' => $rek->unit_kerja_id,
            'anggaran_rekening_id' => $rek->id,
            'user_id' => $creator->id,
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-10',
            'uraian' => 'Belanja Test',
            'nama_penerima' => 'Toko Test',
            'nominal_bruto' => 200000,
            'total_pajak' => 0,
            'nominal_neto' => 200000,
            'status' => 'DRAFT',
        ]);

        KuitansiLampiran::create([
            'kuitansi_id' => $k->id,
            'anggaran_rekening_id' => $rek->id,
            'nama_dokumen' => 'Nota Test',
            'file_name' => 'nota.pdf',
            'file_path' => 'lampiran/nota.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', $nomor),
        ]);

        return $k;
    }

    private function receiptPayload(int $rekeningId, string $nomor): array
    {
        return [
            'anggaran_rekening_id' => $rekeningId,
            'bulan_anggaran' => 2,
            'nomor_kuitansi' => $nomor,
            'tanggal_transaksi' => '2026-02-10',
            'uraian' => 'Belanja ATK',
            'nama_penerima' => 'Toko Mitra',
            'items' => [
                ['nama_barang' => 'Kertas A4', 'volume' => 2, 'harga_satuan' => 50000],
            ],
        ];
    }

    public function test_bpp_kelurahan_only_inputs_own_unit_and_submits_to_menunggu_verifikasi(): void
    {
        $this->actingAs($this->bppA)
            ->postJson('/api/v1/receipts', $this->receiptPayload($this->rekKelurahanB->id, 'KW-BPP-403'))
            ->assertStatus(403);

        $this->actingAs($this->bppA)
            ->postJson('/api/v1/receipts', $this->receiptPayload($this->rekKelurahanA->id, 'KW-BPP-201'))
            ->assertStatus(201);

        $draft = $this->createDraftWithAttachment($this->bppA, $this->rekKelurahanA, 'KW-BPP-SUB-01');
        $this->actingAs($this->bppA)
            ->postJson("/api/v1/receipts/{$draft->id}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'MENUNGGU_VERIFIKASI');
    }

    public function test_bp_kecamatan_is_unit_locked_for_input_and_auto_finalizes_own_unit(): void
    {
        // BP Kecamatan dilarang input pada rekening Kelurahan
        $this->actingAs($this->bpKecamatan)
            ->postJson('/api/v1/receipts', $this->receiptPayload($this->rekKelurahanA->id, 'KW-BP-403'))
            ->assertStatus(403);

        // BP Kecamatan dilarang mengedit atau men-submit draft milik BPP Kelurahan
        $draftBpp = $this->createDraftWithAttachment($this->bppA, $this->rekKelurahanA, 'KW-BPP-LOCK');
        $this->actingAs($this->bpKecamatan)
            ->postJson("/api/v1/receipts/{$draftBpp->id}/submit")
            ->assertStatus(403);

        // BP Kecamatan input & submit di Kecamatan -> langsung LOLOS_VERIFIKASI + verifikator_id + qr_token
        $draftBp = $this->createDraftWithAttachment($this->bpKecamatan, $this->rekKecamatan, 'KW-BP-AUTO');
        $res = $this->actingAs($this->bpKecamatan)
            ->postJson("/api/v1/receipts/{$draftBp->id}/submit");

        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'LOLOS_VERIFIKASI');

        $draftBp->refresh();
        $this->assertEquals($this->bpKecamatan->id, $draftBp->verifikator_id);
        $this->assertNotNull($draftBp->qr_token);
    }

    public function test_kasubag_keuangan_can_input_cross_unit_and_auto_finalizes(): void
    {
        $this->actingAs($this->kasubag)
            ->postJson('/api/v1/receipts', $this->receiptPayload($this->rekKelurahanB->id, 'KW-KS-CROSS'))
            ->assertStatus(201);

        $draftCross = $this->createDraftWithAttachment($this->kasubag, $this->rekKelurahanB, 'KW-KS-AUTO');
        $this->actingAs($this->kasubag)
            ->postJson("/api/v1/receipts/{$draftCross->id}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'LOLOS_VERIFIKASI');

        $draftCross->refresh();
        $this->assertEquals($this->kasubag->id, $draftCross->verifikator_id);
        $this->assertNotNull($draftCross->qr_token);
    }

    public function test_both_bp_and_kasubag_can_verify_bpp_kelurahan_with_first_come_first_served(): void
    {
        $draftBpp = $this->createDraftWithAttachment($this->bppA, $this->rekKelurahanA, 'KW-VERIF-BOTH');
        $this->actingAs($this->bppA)->postJson("/api/v1/receipts/{$draftBpp->id}/submit")->assertStatus(200);

        $checklist = MasterChecklistItem::where('kategori_belanja', 'B.1')
            ->where('is_active', true)
            ->where('is_required', true)
            ->get()
            ->map(fn ($item) => ['item_id' => $item->id, 'status' => 'SESUAI'])
            ->values()
            ->all();

        // BP Kecamatan memverifikasi duluan -> 200 LOLOS_VERIFIKASI
        $this->actingAs($this->bpKecamatan)
            ->postJson("/api/v1/verification/{$draftBpp->id}/action", [
                'action' => 'VERIFIED',
                'notes' => 'Diverifikasi BP Kecamatan',
                'checklist_results' => $checklist,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.new_status', 'LOLOS_VERIFIKASI');

        // Kasubag mencoba memverifikasi ulang kuitansi yang sama -> ditolak 422 (first-come-first-served)
        $this->actingAs($this->kasubag)
            ->postJson("/api/v1/verification/{$draftBpp->id}/action", [
                'action' => 'VERIFIED',
                'notes' => 'Double verify',
                'checklist_results' => $checklist,
            ])
            ->assertStatus(422);
    }

    public function test_lurah_and_camat_are_strictly_read_only(): void
    {
        foreach ([$this->lurahA, $this->camat] as $monitorUser) {
            $this->actingAs($monitorUser)->getJson('/api/v1/budget/summary')->assertStatus(200);
            $this->actingAs($monitorUser)->getJson('/api/v1/receipts')->assertStatus(200);
            $this->actingAs($monitorUser)->get('/api/v1/receipts/export')->assertStatus(200);
            $this->actingAs($monitorUser)->getJson('/api/v1/bundles')->assertStatus(200);

            $this->actingAs($monitorUser)
                ->postJson('/api/v1/receipts', $this->receiptPayload($this->rekKelurahanA->id, 'KW-RO-'.$monitorUser->id))
                ->assertStatus(403);
            $this->actingAs($monitorUser)
                ->postJson('/api/v1/bundles', ['jenis_bundel' => 'SPP_GU', 'kuitansi_ids' => [1]])
                ->assertStatus(403);
            $this->actingAs($monitorUser)
                ->getJson('/api/v1/users')
                ->assertStatus(403);
        }
    }

    public function test_operator_role_is_rejected_in_user_management_and_absent_after_seeding(): void
    {
        $this->actingAs($this->kasubag)
            ->postJson('/api/v1/users', [
                'name' => 'Illegal Operator',
                'username' => 'illegal.operator',
                'password' => 'Password123!',
                'role' => 'OPERATOR',
                'unit_kerja_id' => $this->kelurahanA->id,
            ])
            ->assertStatus(422);

        $this->seed(DatabaseSeeder::class);
        $this->assertFalse(Role::where('name', 'OPERATOR')->exists());
        $this->assertEquals(0, User::where('role', 'OPERATOR')->count());
    }
}
