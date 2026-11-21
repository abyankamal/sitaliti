<?php

namespace Tests\Feature\Security;

use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\PptkSubkegiatanAssignment;
use App\Models\UnitKerja;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KelurahanDomainFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected UnitKerja $kecamatan;

    protected UnitKerja $kelurahanPataruman;

    protected UnitKerja $kelurahanSukagalih;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_lurah_default_permissions_are_configured_and_synced(): void
    {
        $perms = User::defaultRolePermissions('LURAH');

        $this->assertContains('kuitansi.view', $perms);
        $this->assertContains('bundle.view', $perms);
        $this->assertContains('budget.view', $perms);

        // Permissions that Lurah must NOT have (read-only):
        $this->assertNotContains('kuitansi.lurah_approve', $perms);
        $this->assertNotContains('kuitansi.lurah_reject', $perms);
        $this->assertNotContains('verification.queue', $perms);
        $this->assertNotContains('verification.audit', $perms);
        $this->assertNotContains('dashboard.view_all', $perms);
        $this->assertNotContains('kuitansi.create', $perms);
        $this->assertNotContains('kuitansi.edit', $perms);
        $this->assertNotContains('bundle.create', $perms);
        $this->assertNotContains('bundle.record_sp2d', $perms);
        $this->assertNotContains('verification.process_action', $perms);

        // Test Spatie auto-sync on User creation
        $lurah = User::factory()->create([
            'role' => 'LURAH',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);

        $this->assertTrue($lurah->hasRole('LURAH'));
        $this->assertTrue($lurah->hasPermissionTo('kuitansi.view'));
        $this->assertFalse($lurah->can('kuitansi.lurah_approve'));
        $this->assertFalse($lurah->can('dashboard.view_all'));
    }

    public function test_domain_scoping_helpers(): void
    {
        $lurahPataruman = User::factory()->create([
            'role' => 'LURAH',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);

        $operatorKelurahan = User::factory()->create([
            'role' => 'BPP',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);

        $operatorKecamatan = User::factory()->create([
            'role' => 'BPP',
            'unit_kerja_id' => $this->kecamatan->id,
        ]);

        $bppKelurahan = User::factory()->create([
            'role' => 'BPP',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);

        $bpKecamatan = User::factory()->create([
            'role' => 'BP',
            'unit_kerja_id' => $this->kecamatan->id,
        ]);

        $verifikator = User::factory()->create([
            'role' => 'VERIFIKATOR',
            'unit_kerja_id' => $this->kecamatan->id,
        ]);

        $kpaCamat = User::factory()->create([
            'role' => 'KPA',
            'unit_kerja_id' => $this->kecamatan->id,
        ]);

        // isKelurahanScoped
        $this->assertTrue($lurahPataruman->isKelurahanScoped());
        $this->assertTrue($operatorKelurahan->isKelurahanScoped());
        $this->assertTrue($bppKelurahan->isKelurahanScoped());
        $this->assertFalse($operatorKecamatan->isKelurahanScoped());
        $this->assertFalse($bpKecamatan->isKelurahanScoped());

        // isSkpdWide
        $this->assertTrue($verifikator->isSkpdWide());
        $this->assertTrue($kpaCamat->isSkpdWide());
        $this->assertTrue($bpKecamatan->isSkpdWide());
        $this->assertFalse($bppKelurahan->isSkpdWide());
        $this->assertFalse($lurahPataruman->isSkpdWide());
        $this->assertFalse($operatorKelurahan->isSkpdWide());

        // isKelurahanBpp
        $this->assertTrue($bppKelurahan->isKelurahanBpp());
        $this->assertFalse($bpKecamatan->isKelurahanBpp());
    }

    public function test_pptk_subkegiatan_assignment_and_granularity(): void
    {
        $pptkSarpras = User::factory()->create([
            'role' => 'PPTK',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);

        $pptkPemberdayaan = User::factory()->create([
            'role' => 'PPTK',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);

        $pptkGeneral = User::factory()->create([
            'role' => 'PPTK',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);

        // Assign 2 PPTKs explicitly
        PptkSubkegiatanAssignment::create([
            'user_id' => $pptkSarpras->id,
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0002',
        ]);

        PptkSubkegiatanAssignment::create([
            'user_id' => $pptkPemberdayaan->id,
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0003',
        ]);

        // Sarpras PPTK
        $this->assertTrue($pptkSarpras->canAccessPptkSubkegiatan($this->kelurahanPataruman->id, '7.01.03.2.02.0002'));
        $this->assertFalse($pptkSarpras->canAccessPptkSubkegiatan($this->kelurahanPataruman->id, '7.01.03.2.02.0003'));
        $this->assertFalse($pptkSarpras->canAccessPptkSubkegiatan($this->kelurahanSukagalih->id, '7.01.03.2.02.0002'));

        // Pemberdayaan PPTK
        $this->assertFalse($pptkPemberdayaan->canAccessPptkSubkegiatan($this->kelurahanPataruman->id, '7.01.03.2.02.0002'));
        $this->assertTrue($pptkPemberdayaan->canAccessPptkSubkegiatan($this->kelurahanPataruman->id, '7.01.03.2.02.0003'));

        // 1-PPTK fallback (0 assignment rows -> access all subkegiatan in own unit)
        $this->assertTrue($pptkGeneral->canAccessPptkSubkegiatan($this->kelurahanPataruman->id, '7.01.03.2.02.0002'));
        $this->assertTrue($pptkGeneral->canAccessPptkSubkegiatan($this->kelurahanPataruman->id, '7.01.03.2.02.0003'));
        $this->assertFalse($pptkGeneral->canAccessPptkSubkegiatan($this->kelurahanSukagalih->id, '7.01.03.2.02.0002'));
    }

    public function test_can_access_receipt_centralized_authorization(): void
    {
        $rekeningSarprasPataruman = AnggaranRekening::create([
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0002',
            'nama_sub_kegiatan' => 'Pembangunan Sarana dan Prasarana Kelurahan',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja Alat Tulis Kantor',
            'pagu_murni' => 50000000,
            'pagu_perubahan' => 50000000,
            'realisasi' => 0,
            'kategori_belanja' => 'OPERASIONAL',
            'tahun_anggaran' => 2026,
        ]);

        $rekeningPemberdayaanPataruman = AnggaranRekening::create([
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0003',
            'nama_sub_kegiatan' => 'Pemberdayaan Masyarakat di Kelurahan',
            'kode_rekening' => '5.1.02.02.01.0026',
            'nama_rekening' => 'Belanja Jasa Tenaga Kerja',
            'pagu_murni' => 50000000,
            'pagu_perubahan' => 50000000,
            'realisasi' => 0,
            'kategori_belanja' => 'OPERASIONAL',
            'tahun_anggaran' => 2026,
        ]);

        $operatorPataruman = User::factory()->create([
            'role' => 'BPP',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);

        $kuitansiSarpras = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/2026/01/0001',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'anggaran_rekening_id' => $rekeningSarprasPataruman->id,
            'user_id' => $operatorPataruman->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-15',
            'uraian' => 'Pengadaan ATK Sarpras',
            'nama_penerima' => 'Toko Alat Tulis',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 1000000,
            'total_pajak' => 0,
            'nominal_neto' => 1000000,
            'status' => 'DRAFT',
        ]);

        $kuitansiPemberdayaan = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/2026/01/0002',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'anggaran_rekening_id' => $rekeningPemberdayaanPataruman->id,
            'user_id' => $operatorPataruman->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-16',
            'uraian' => 'Honor Fasilitator Pemberdayaan',
            'nama_penerima' => 'Fasilitator',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 1500000,
            'total_pajak' => 0,
            'nominal_neto' => 1500000,
            'status' => 'DRAFT',
        ]);

        $lurahPataruman = User::factory()->create([
            'role' => 'LURAH',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);

        $lurahSukagalih = User::factory()->create([
            'role' => 'LURAH',
            'unit_kerja_id' => $this->kelurahanSukagalih->id,
        ]);

        $pptkSarpras = User::factory()->create([
            'role' => 'PPTK',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);
        PptkSubkegiatanAssignment::create([
            'user_id' => $pptkSarpras->id,
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0002',
        ]);

        $kasubagKeuangan = User::factory()->create([
            'role' => 'VERIFIKATOR',
            'unit_kerja_id' => $this->kecamatan->id,
        ]);

        // Lurah Pataruman can access own kelurahan receipts
        $this->assertTrue($lurahPataruman->canAccessReceipt($kuitansiSarpras));
        $this->assertTrue($lurahPataruman->canAccessReceipt($kuitansiPemberdayaan));

        // Lurah Sukagalih CANNOT access Pataruman receipts
        $this->assertFalse($lurahSukagalih->canAccessReceipt($kuitansiSarpras));

        // PPTK Sarpras can only access Sarpras receipt
        $this->assertTrue($pptkSarpras->canAccessReceipt($kuitansiSarpras));
        $this->assertFalse($pptkSarpras->canAccessReceipt($kuitansiPemberdayaan));

        // Kasubag Keuangan (Verifikator) can access all receipts SKPD-wide
        $this->assertTrue($kasubagKeuangan->canAccessReceipt($kuitansiSarpras));
        $this->assertTrue($kasubagKeuangan->canAccessReceipt($kuitansiPemberdayaan));
    }

    public function test_kuitansi_lurah_fields_and_relation(): void
    {
        $lurah = User::factory()->create([
            'role' => 'LURAH',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);

        $rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0002',
            'nama_sub_kegiatan' => 'Pembangunan Sarana dan Prasarana Kelurahan',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja Alat Tulis Kantor',
            'pagu_murni' => 50000000,
            'pagu_perubahan' => 50000000,
            'realisasi' => 0,
            'kategori_belanja' => 'OPERASIONAL',
            'tahun_anggaran' => 2026,
        ]);

        $now = Carbon::parse('2026-02-01 10:30:00');

        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/2026/02/0001',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'anggaran_rekening_id' => $rekening->id,
            'user_id' => $lurah->id,
            'lurah_user_id' => $lurah->id,
            'tanggal_lurah_approve' => $now,
            'catatan_lurah' => 'Disetujui KPA Kelurahan Pataruman',
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-01',
            'uraian' => 'Pengadaan ATK Sarpras',
            'nama_penerima' => 'Toko ATK',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 500000,
            'total_pajak' => 0,
            'nominal_neto' => 500000,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        $this->assertEquals($lurah->id, $kuitansi->lurahUser->id);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $kuitansi->tanggal_lurah_approve);
        $this->assertEquals('Disetujui KPA Kelurahan Pataruman', $kuitansi->catatan_lurah);
    }
}
