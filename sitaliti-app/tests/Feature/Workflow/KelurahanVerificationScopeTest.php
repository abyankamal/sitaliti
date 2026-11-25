<?php

namespace Tests\Feature\Workflow;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\PptkSubkegiatanAssignment;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\MasterChecklistSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KelurahanVerificationScopeTest extends TestCase
{
    use RefreshDatabase;

    protected UnitKerja $kecamatan;

    protected UnitKerja $kelurahanPataruman;

    protected UnitKerja $kelurahanSukagalih;

    protected AnggaranRekening $rekeningSarprasPataruman;

    protected AnggaranRekening $rekeningPemberdayaanPataruman;

    protected AnggaranRekening $rekeningSukagalih;

    protected User $operatorPataruman;

    protected User $operatorSukagalih;

    protected User $pptkSarprasPataruman;

    protected User $pptkPemberdayaanPataruman;

    protected User $lurahPataruman;

    protected User $lurahSukagalih;

    protected User $verifikatorKecamatan;

    protected Kuitansi $kuitansiSarprasPataruman;

    protected Kuitansi $kuitansiPemberdayaanPataruman;

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

        $this->rekeningSarprasPataruman = AnggaranRekening::create([
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0002',
            'nama_sub_kegiatan' => 'Pembangunan Sarana dan Prasarana Kelurahan',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja ATK Sarpras Pataruman',
            'pagu_murni' => 50000000,
            'pagu_perubahan' => 50000000,
            'realisasi' => 0,
            'kategori_belanja' => 'OPERASIONAL',
            'tahun_anggaran' => 2026,
        ]);

        $this->rekeningPemberdayaanPataruman = AnggaranRekening::create([
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0003',
            'nama_sub_kegiatan' => 'Pemberdayaan Masyarakat di Kelurahan',
            'kode_rekening' => '5.1.02.02.01.0026',
            'nama_rekening' => 'Belanja Tenaga Kerja Pemberdayaan Pataruman',
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
            'nama_rekening' => 'Belanja ATK Sarpras Sukagalih',
            'pagu_murni' => 50000000,
            'pagu_perubahan' => 50000000,
            'realisasi' => 0,
            'kategori_belanja' => 'OPERASIONAL',
            'tahun_anggaran' => 2026,
        ]);

        foreach ([$this->rekeningSarprasPataruman, $this->rekeningPemberdayaanPataruman, $this->rekeningSukagalih] as $rek) {
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

        // Users
        $this->operatorPataruman = User::factory()->create([
            'role' => 'BPP',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);

        $this->operatorSukagalih = User::factory()->create([
            'role' => 'BPP',
            'unit_kerja_id' => $this->kelurahanSukagalih->id,
        ]);

        $this->pptkSarprasPataruman = User::factory()->create([
            'role' => 'PPTK',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);
        PptkSubkegiatanAssignment::create([
            'user_id' => $this->pptkSarprasPataruman->id,
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0002',
        ]);

        $this->pptkPemberdayaanPataruman = User::factory()->create([
            'role' => 'PPTK',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);
        PptkSubkegiatanAssignment::create([
            'user_id' => $this->pptkPemberdayaanPataruman->id,
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0003',
        ]);

        $this->lurahPataruman = User::factory()->create([
            'role' => 'LURAH',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);

        $this->lurahSukagalih = User::factory()->create([
            'role' => 'LURAH',
            'unit_kerja_id' => $this->kelurahanSukagalih->id,
        ]);

        $this->verifikatorKecamatan = User::factory()->create([
            'role' => 'VERIFIKATOR',
            'unit_kerja_id' => $this->kecamatan->id,
        ]);

        // Kuitansi 1: Sarpras Pataruman (status MENUNGGU_PPTK)
        $this->kuitansiSarprasPataruman = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/PTR/VERIF/0001',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'anggaran_rekening_id' => $this->rekeningSarprasPataruman->id,
            'user_id' => $this->operatorPataruman->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-20',
            'uraian' => 'Semen Sarpras Pataruman',
            'nama_penerima' => 'Toko Material',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 1000000,
            'total_pajak' => 0,
            'nominal_neto' => 1000000,
            'status' => 'MENUNGGU_PPTK',
        ]);

        // Kuitansi 2: Pemberdayaan Pataruman (status MENUNGGU_VERIFIKASI)
        $this->kuitansiPemberdayaanPataruman = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/PTR/VERIF/0002',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'anggaran_rekening_id' => $this->rekeningPemberdayaanPataruman->id,
            'user_id' => $this->operatorPataruman->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-21',
            'uraian' => 'Honor Fasilitator Pataruman',
            'nama_penerima' => 'Fasilitator',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 1200000,
            'total_pajak' => 0,
            'nominal_neto' => 1200000,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        // Kuitansi 3: Sukagalih (status MENUNGGU_VERIFIKASI)
        $this->kuitansiSukagalih = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/SKG/VERIF/0001',
            'unit_kerja_id' => $this->kelurahanSukagalih->id,
            'anggaran_rekening_id' => $this->rekeningSukagalih->id,
            'user_id' => $this->operatorSukagalih->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-22',
            'uraian' => 'Kertas Sukagalih',
            'nama_penerima' => 'Toko ATK Sukagalih',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 800000,
            'total_pajak' => 0,
            'nominal_neto' => 800000,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);
    }

    public function test_verification_queue_scoping_for_lurah_pptk_and_verifikator(): void
    {
        // 1. Lurah Pataruman (read-only) ditolak 403 di antrean verifikasi
        $this->actingAs($this->lurahPataruman)->getJson('/api/v1/verification/queue')->assertStatus(403);

        // 2. Antrean PPTK Sarpras Pataruman: hanya melihat Sarpras Pataruman
        $resPptkSarpras = $this->actingAs($this->pptkSarprasPataruman)->getJson('/api/v1/verification/queue');
        $resPptkSarpras->assertOk();
        $idsPptk = collect($resPptkSarpras->json('data'))->pluck('id')->all();
        $this->assertContains('KWT/PTR/VERIF/0001', $idsPptk);
        $this->assertNotContains('KWT/PTR/VERIF/0002', $idsPptk);
        $this->assertNotContains('KWT/SKG/VERIF/0001', $idsPptk);

        // 3. Antrean Verifikator Kecamatan: melihat seluruh kuitansi berstatus MENUNGGU_VERIFIKASI lintas unit
        $resVerif = $this->actingAs($this->verifikatorKecamatan)->getJson('/api/v1/verification/queue');
        $resVerif->assertOk();
        $idsVerif = collect($resVerif->json('data'))->pluck('id')->all();
        $this->assertContains('KWT/PTR/VERIF/0002', $idsVerif);
        $this->assertContains('KWT/SKG/VERIF/0001', $idsVerif);
        // KWT/PTR/VERIF/0001 masih MENUNGGU_LURAH sehingga belum masuk antrean verifikator
        $this->assertNotContains('KWT/PTR/VERIF/0001', $idsVerif);
    }

    public function test_verification_detail_api_and_web_workspace_forbidden_for_unauthorized_user(): void
    {
        // 1. API GET /api/v1/verification/{id}
        // Lurah Sukagalih dilarang membuka detail kuitansi Pataruman -> 403
        $this->actingAs($this->lurahSukagalih)
            ->getJson("/api/v1/verification/{$this->kuitansiSarprasPataruman->id}")
            ->assertStatus(403);

        // PPTK Pemberdayaan dilarang membuka detail kuitansi Sarpras -> 403
        $this->actingAs($this->pptkPemberdayaanPataruman)
            ->getJson("/api/v1/verification/{$this->kuitansiSarprasPataruman->id}")
            ->assertStatus(403);

        // Lurah Pataruman (read-only) juga ditolak 403 membuka workspace verifikasi -> 403
        $this->actingAs($this->lurahPataruman)
            ->getJson("/api/v1/verification/{$this->kuitansiSarprasPataruman->id}")
            ->assertStatus(403);

        // 2. Web Route GET /verification/{id}/workspace
        // Lurah Sukagalih dilarang membuka workspace kuitansi Pataruman -> 403
        $this->actingAs($this->lurahSukagalih)
            ->get("/verification/{$this->kuitansiSarprasPataruman->id}/workspace")
            ->assertStatus(403);

        // PPTK Pemberdayaan dilarang membuka workspace kuitansi Sarpras -> 403
        $this->actingAs($this->pptkPemberdayaanPataruman)
            ->get("/verification/{$this->kuitansiSarprasPataruman->id}/workspace")
            ->assertStatus(403);

        // Verifikator Kecamatan BERHAK membuka workspace kuitansi Pataruman -> 200
        $this->actingAs($this->verifikatorKecamatan)
            ->get("/verification/{$this->kuitansiSarprasPataruman->id}/workspace")
            ->assertOk();
    }

    public function test_lurah_and_pptk_cannot_execute_verification_action(): void
    {
        $payload = [
            'action' => 'VERIFIED',
            'notes' => 'Mencoba eksekusi verifikasi sebagai non-verifikator',
        ];

        // Lurah Pataruman mencoba verifikasi -> 403
        $this->actingAs($this->lurahPataruman)
            ->postJson("/api/v1/verification/{$this->kuitansiPemberdayaanPataruman->id}/action", $payload)
            ->assertStatus(403);

        // PPTK Pemberdayaan mencoba verifikasi -> 403
        $this->actingAs($this->pptkPemberdayaanPataruman)
            ->postJson("/api/v1/verification/{$this->kuitansiPemberdayaanPataruman->id}/action", $payload)
            ->assertStatus(403);
    }
}
