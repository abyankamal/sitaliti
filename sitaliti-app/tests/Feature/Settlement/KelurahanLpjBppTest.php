<?php

namespace Tests\Feature\Settlement;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\PengajuanSpj;
use App\Models\PengajuanSpjItem;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\MasterChecklistSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KelurahanLpjBppTest extends TestCase
{
    use RefreshDatabase;

    protected UnitKerja $kecamatan;

    protected UnitKerja $kelurahanPataruman;

    protected UnitKerja $kelurahanSukagalih;

    protected AnggaranRekening $rekeningPataruman;

    protected AnggaranRekening $rekeningSukagalih;

    protected User $bppPataruman;

    protected User $bppSukagalih;

    protected User $lurahPataruman;

    protected User $lurahSukagalih;

    protected User $bpKecamatan;

    protected Kuitansi $kuitansiPataruman;

    protected Kuitansi $kuitansiSukagalih;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterChecklistSeeder::class);

        $this->kecamatan = UnitKerja::create([
            'kode_unit' => '7.01.0.00.0.00.41.0000',
            'nama_unit' => 'Kecamatan Tarogong Kidul',
            'tipe' => 'KECAMATAN',
            'status_aktif' => true,
        ]);

        $this->kelurahanPataruman = UnitKerja::create([
            'kode_unit' => '7.01.0.00.0.00.41.0001',
            'nama_unit' => 'Kelurahan Pataruman',
            'tipe' => 'KELURAHAN',
            'status_aktif' => true,
        ]);

        $this->kelurahanSukagalih = UnitKerja::create([
            'kode_unit' => '7.01.0.00.0.00.41.0002',
            'nama_unit' => 'Kelurahan Sukagalih',
            'tipe' => 'KELURAHAN',
            'status_aktif' => true,
        ]);

        $this->rekeningPataruman = AnggaranRekening::create([
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0002',
            'nama_sub_kegiatan' => 'Pembangunan Sarana dan Prasarana Kelurahan',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja ATK Pataruman',
            'pagu_murni' => 50000000,
            'pagu_perubahan' => 50000000,
            'realisasi' => 0,
            'kategori_belanja' => 'OPERASIONAL',
            'tahun_anggaran' => 2026,
        ]);

        $this->rekeningSukagalih = AnggaranRekening::create([
            'unit_kerja_id' => $this->kelurahanSukagalih->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0002',
            'nama_sub_kegiatan' => 'Pembangunan Sarana dan Prasarana Kelurahan',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja ATK Sukagalih',
            'pagu_murni' => 50000000,
            'pagu_perubahan' => 50000000,
            'realisasi' => 0,
            'kategori_belanja' => 'OPERASIONAL',
            'tahun_anggaran' => 2026,
        ]);

        foreach ([$this->rekeningPataruman, $this->rekeningSukagalih] as $rek) {
            for ($b = 1; $b <= 12; $b++) {
                AnggaranKasBulanan::create([
                    'anggaran_rekening_id' => $rek->id,
                    'bulan' => $b,
                    'alokasi_pagu' => 10000000.00,
                    'terkunci_bulan' => 0.00,
                    'realisasi_bulan' => 0.00,
                ]);
            }
        }

        $this->bppPataruman = User::factory()->create([
            'role' => 'BPP',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);

        $this->bppSukagalih = User::factory()->create([
            'role' => 'BPP',
            'unit_kerja_id' => $this->kelurahanSukagalih->id,
        ]);

        $this->lurahPataruman = User::factory()->create([
            'role' => 'LURAH',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);

        $this->lurahSukagalih = User::factory()->create([
            'role' => 'LURAH',
            'unit_kerja_id' => $this->kelurahanSukagalih->id,
        ]);

        $this->bpKecamatan = User::factory()->create([
            'role' => 'BPP',
            'unit_kerja_id' => $this->kecamatan->id,
        ]);

        $this->kuitansiPataruman = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/PTR/SETTLE/01',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'anggaran_rekening_id' => $this->rekeningPataruman->id,
            'user_id' => $this->bppPataruman->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-20',
            'uraian' => 'Kuitansi Lolos Verifikasi Pataruman',
            'nama_penerima' => 'Toko ATK',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 1000000,
            'total_pajak' => 0,
            'nominal_neto' => 1000000,
            'status' => 'LOLOS_VERIFIKASI',
        ]);

        $this->kuitansiSukagalih = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/SKG/SETTLE/01',
            'unit_kerja_id' => $this->kelurahanSukagalih->id,
            'anggaran_rekening_id' => $this->rekeningSukagalih->id,
            'user_id' => $this->bppSukagalih->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-21',
            'uraian' => 'Kuitansi Lolos Verifikasi Sukagalih',
            'nama_penerima' => 'Toko Sukagalih',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 800000,
            'total_pajak' => 0,
            'nominal_neto' => 800000,
            'status' => 'LOLOS_VERIFIKASI',
        ]);
    }

    public function test_bpp_kelurahan_can_create_lpj_bpp_but_cannot_create_spp_gu(): void
    {
        // 1. Sukses buat bundel LPJ_BPP untuk kuitansi kelurahannya
        $responseLpj = $this->actingAs($this->bppPataruman)->postJson('/api/v1/bundles', [
            'kuitansi_ids' => [$this->kuitansiPataruman->id],
            'jenis_bundel' => 'LPJ_BPP',
            'tahun_anggaran' => 2026,
            'bulan_periode' => 1,
        ]);

        $responseLpj->assertStatus(201)
            ->assertJsonPath('data.jenis_bundel', 'LPJ_BPP')
            ->assertJsonPath('data.unit_kerja_id', $this->kelurahanPataruman->id);

        // Kuitansi berubah status jadi DIBUNDEL
        $this->kuitansiPataruman->refresh();
        $this->assertEquals('DIBUNDEL', $this->kuitansiPataruman->status);

        // 2. Buat kuitansi baru lagi untuk uji penolakan SPP_GU
        $kuitansiBaru = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/PTR/SETTLE/02',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'anggaran_rekening_id' => $this->rekeningPataruman->id,
            'user_id' => $this->bppPataruman->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-22',
            'uraian' => 'Kuitansi Pataruman 2',
            'nama_penerima' => 'Toko ATK 2',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 500000,
            'total_pajak' => 0,
            'nominal_neto' => 500000,
            'status' => 'LOLOS_VERIFIKASI',
        ]);

        // BPP Kelurahan dilarang membuat bundel jenis SPP_GU (harus 403 atau 422)
        $responseGu = $this->actingAs($this->bppPataruman)->postJson('/api/v1/bundles', [
            'kuitansi_ids' => [$kuitansiBaru->id],
            'jenis_bundel' => 'SPP_GU',
            'tahun_anggaran' => 2026,
            'bulan_periode' => 1,
        ]);

        $responseGu->assertStatus(403);
    }

    public function test_bpp_kelurahan_cannot_bundle_receipts_from_another_unit(): void
    {
        // BPP Pataruman mencoba membundel kuitansi Sukagalih -> harus 403
        $response = $this->actingAs($this->bppPataruman)->postJson('/api/v1/bundles', [
            'kuitansi_ids' => [$this->kuitansiSukagalih->id],
            'jenis_bundel' => 'LPJ_BPP',
            'tahun_anggaran' => 2026,
            'bulan_periode' => 1,
        ]);

        $response->assertStatus(403);
    }

    public function test_bpp_kelurahan_and_lurah_cannot_record_sp2d(): void
    {
        // Buat bundel LPJ Pataruman
        $bundle = PengajuanSpj::create([
            'nomor_bundel' => 'BND-LPJ/2026/01/0001',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'user_id' => $this->bppPataruman->id,
            'jenis_bundel' => 'LPJ_BPP',
            'tahun_anggaran' => 2026,
            'bulan_periode' => 1,
            'status_bundel' => 'VERIFIED',
        ]);

        PengajuanSpjItem::create([
            'pengajuan_spj_id' => $bundle->id,
            'kuitansi_id' => $this->kuitansiPataruman->id,
            'no_urut_ordner' => 1,
            'status_item' => 'AKTIF',
        ]);

        // BPP Kelurahan mencoba catat SP2D -> harus 403
        $this->actingAs($this->bppPataruman)
            ->postJson("/api/v1/bundles/{$bundle->id}/sp2d", [
                'nomor_sp2d' => 'SP2D/2026/001',
            ])
            ->assertStatus(403);

        // Lurah Pataruman mencoba catat SP2D -> harus 403
        $this->actingAs($this->lurahPataruman)
            ->postJson("/api/v1/bundles/{$bundle->id}/sp2d", [
                'nomor_sp2d' => 'SP2D/2026/001',
            ])
            ->assertStatus(403);
    }

    public function test_lurah_has_read_only_access_strictly_scoped_to_own_unit_bundles(): void
    {
        // Bundel 1 di Pataruman
        $bundlePataruman = PengajuanSpj::create([
            'nomor_bundel' => 'BND-LPJ/PTR/2026/01',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'user_id' => $this->bppPataruman->id,
            'jenis_bundel' => 'LPJ_BPP',
            'tahun_anggaran' => 2026,
            'bulan_periode' => 1,
            'status_bundel' => 'DRAFT',
        ]);

        // Bundel 2 di Sukagalih
        $bundleSukagalih = PengajuanSpj::create([
            'nomor_bundel' => 'BND-LPJ/SKG/2026/01',
            'unit_kerja_id' => $this->kelurahanSukagalih->id,
            'user_id' => $this->bppSukagalih->id,
            'jenis_bundel' => 'LPJ_BPP',
            'tahun_anggaran' => 2026,
            'bulan_periode' => 1,
            'status_bundel' => 'DRAFT',
        ]);

        // 1. Lurah Pataruman melihat daftar bundel: hanya melihat Pataruman
        $resList = $this->actingAs($this->lurahPataruman)->getJson('/api/v1/bundles');
        $resList->assertOk();
        $nomorBundels = collect($resList->json('data'))->pluck('nomor_bundel')->all();
        $this->assertContains('BND-LPJ/PTR/2026/01', $nomorBundels);
        $this->assertNotContains('BND-LPJ/SKG/2026/01', $nomorBundels);

        // 2. Lurah Pataruman show detail bundel Pataruman -> 200
        $this->actingAs($this->lurahPataruman)
            ->getJson("/api/v1/bundles/{$bundlePataruman->id}")
            ->assertOk();

        // 3. Lurah Pataruman show detail bundel Sukagalih -> 403
        $this->actingAs($this->lurahPataruman)
            ->getJson("/api/v1/bundles/{$bundleSukagalih->id}")
            ->assertStatus(403);

        // 4. Lurah dilarang membuat bundel (read-only) -> 403
        $this->actingAs($this->lurahPataruman)
            ->postJson('/api/v1/bundles', [
                'kuitansi_ids' => [$this->kuitansiPataruman->id],
                'jenis_bundel' => 'LPJ_BPP',
            ])
            ->assertStatus(403);

        // 5. Lurah dilarang unbundle kuitansi (read-only) -> 403
        $this->actingAs($this->lurahPataruman)
            ->postJson("/api/v1/bundles/{$bundlePataruman->id}/unbundle/{$this->kuitansiPataruman->id}")
            ->assertStatus(403);
    }
}

