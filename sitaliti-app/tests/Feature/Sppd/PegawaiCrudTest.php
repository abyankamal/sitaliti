<?php

namespace Tests\Feature\Sppd;

use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiSppdPeserta;
use App\Models\Pegawai;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PegawaiCrudTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $kelurahanA;

    private UnitKerja $kelurahanB;

    private User $verifikator;

    private User $bp;

    private User $bppA;

    private User $lurah;

    private User $pptk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $kelurahans = UnitKerja::where('tipe', 'KELURAHAN')->take(2)->get();
        $this->kelurahanA = $kelurahans[0];
        $this->kelurahanB = $kelurahans[1];

        $this->verifikator = User::factory()->create(['role' => 'VERIFIKATOR', 'unit_kerja_id' => $this->kelurahanA->id]);
        $this->bp = User::factory()->create(['role' => 'BP', 'unit_kerja_id' => $this->kelurahanA->id]);
        $this->bppA = User::factory()->create(['role' => 'BPP', 'unit_kerja_id' => $this->kelurahanA->id]);
        $this->lurah = User::factory()->create(['role' => 'LURAH', 'unit_kerja_id' => $this->kelurahanA->id]);
        $this->pptk = User::factory()->create(['role' => 'PPTK', 'unit_kerja_id' => $this->kelurahanA->id]);
    }

    public function test_verifikator_bp_and_bpp_can_manage_pegawai_while_other_roles_forbidden(): void
    {
        $this->actingAs($this->verifikator)
            ->postJson('/api/v1/pegawai', [
                'nip' => '198501012010011001',
                'nama' => 'Pegawai Satu',
                'jabatan' => 'Analis',
                'golongan' => 'III/c',
                'unit_kerja_id' => $this->kelurahanA->id,
            ])
            ->assertCreated();

        $this->actingAs($this->bp)
            ->postJson('/api/v1/pegawai', [
                'nip' => '198501012010011002',
                'nama' => 'Pegawai Dua',
                'unit_kerja_id' => $this->kelurahanB->id,
            ])
            ->assertCreated();

        $this->actingAs($this->bppA)
            ->postJson('/api/v1/pegawai', [
                'nip' => '198501012010011003',
                'nama' => 'Pegawai Tiga',
            ])
            ->assertCreated()
            ->assertJsonPath('data.unit_kerja_id', $this->kelurahanA->id);

        $this->actingAs($this->lurah)->getJson('/api/v1/pegawai')->assertForbidden();
        $this->actingAs($this->pptk)->postJson('/api/v1/pegawai', ['nip' => '123', 'nama' => 'X'])->assertForbidden();
    }

    public function test_bpp_kelurahan_is_scoped_to_own_unit_and_fail_closed_when_unit_null(): void
    {
        $pegawaiB = Pegawai::create([
            'unit_kerja_id' => $this->kelurahanB->id,
            'nip' => '199001012015011001',
            'nama' => 'Pegawai Kelurahan B',
        ]);

        // BPP A mencoba membuat pegawai untuk unit B -> server memaksa unit_kerja_id = kelurahanA
        $this->actingAs($this->bppA)
            ->postJson('/api/v1/pegawai', [
                'nip' => '199001012015011002',
                'nama' => 'Pegawai Paksa Unit A',
                'unit_kerja_id' => $this->kelurahanB->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.unit_kerja_id', $this->kelurahanA->id);

        // BPP A dilarang mengubah atau menghapus pegawai unit B
        $this->actingAs($this->bppA)
            ->putJson("/api/v1/pegawai/{$pegawaiB->id}", [
                'nip' => $pegawaiB->nip,
                'nama' => 'Ubah Nama',
            ])
            ->assertForbidden();

        $this->actingAs($this->bppA)
            ->deleteJson("/api/v1/pegawai/{$pegawaiB->id}")
            ->assertForbidden();

        // Fail-closed jika BPP tidak memiliki unit_kerja_id
        $orphanBpp = User::factory()->create(['role' => 'BPP', 'unit_kerja_id' => null]);
        $this->actingAs($orphanBpp)->getJson('/api/v1/pegawai')->assertForbidden();
    }

    public function test_nip_normalization_prevents_duplicate_pegawai(): void
    {
        $this->actingAs($this->verifikator)
            ->postJson('/api/v1/pegawai', [
                'nip' => '19850101 201001-1 001',
                'nama' => 'Pegawai Normalized',
            ])
            ->assertCreated()
            ->assertJsonPath('data.nip', '198501012010011001');

        $this->actingAs($this->verifikator)
            ->postJson('/api/v1/pegawai', [
                'nip' => '198501012010011001',
                'nama' => 'Pegawai Duplikat',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nip']);
    }

    public function test_cannot_delete_pegawai_already_used_in_sppd(): void
    {
        $pegawai = Pegawai::create([
            'unit_kerja_id' => $this->kelurahanA->id,
            'nip' => '198801012012011001',
            'nama' => 'Pegawai Terpakai',
        ]);

        $rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->kelurahanA->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Subkeg SPPD',
            'kode_rekening' => '5.1.02.04.01.0001',
            'nama_rekening' => 'Belanja Perjalanan Dinas',
            'pagu_murni' => 10000000,
            'pagu_perubahan' => 10000000,
            'kategori_belanja' => 'B.4',
            'is_leaf' => true,
        ]);

        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-2026-05-0001',
            'unit_kerja_id' => $this->kelurahanA->id,
            'anggaran_rekening_id' => $rekening->id,
            'user_id' => $this->bppA->id,
            'bulan_anggaran' => 5,
            'tanggal_transaksi' => '2026-05-10',
            'uraian' => 'Perjadin',
            'nama_penerima' => 'Pegawai Terpakai',
            'nominal_bruto' => 500000,
            'total_pajak' => 0,
            'nominal_neto' => 500000,
            'status' => 'DRAFT',
        ]);

        KuitansiSppdPeserta::create([
            'kuitansi_id' => $kuitansi->id,
            'pegawai_id' => $pegawai->id,
            'tanggal_berangkat' => '2026-05-10',
            'tanggal_pulang' => '2026-05-11',
        ]);

        $this->actingAs($this->verifikator)
            ->deleteJson("/api/v1/pegawai/{$pegawai->id}")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PEGAWAI_IN_USE');
    }
}
