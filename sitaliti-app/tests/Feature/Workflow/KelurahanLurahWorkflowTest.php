<?php

namespace Tests\Feature\Workflow;

use App\Contracts\BudgetReservationServiceInterface;
use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\PptkSubkegiatanAssignment;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class KelurahanLurahWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected UnitKerja $kecamatan;

    protected UnitKerja $kelurahanPataruman;

    protected UnitKerja $kelurahanSukagalih;

    protected AnggaranRekening $rekeningSarprasPataruman;

    protected AnggaranRekening $rekeningPemberdayaanPataruman;

    protected AnggaranRekening $rekeningKecamatan;

    protected User $operatorPataruman;

    protected User $pptkSarprasPataruman;

    protected User $pptkPemberdayaanPataruman;

    protected User $lurahPataruman;

    protected User $lurahSukagalih;

    protected User $operatorKecamatan;

    protected User $pptkKecamatan;

    protected User $verifikatorKecamatan;

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

        // Rekening Sarpras Pataruman
        $this->rekeningSarprasPataruman = AnggaranRekening::create([
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0002',
            'nama_sub_kegiatan' => 'Pembangunan Sarana dan Prasarana Kelurahan',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja Alat Tulis Kantor Sarpras',
            'pagu_murni' => 50000000,
            'pagu_perubahan' => 50000000,
            'realisasi' => 0,
            'kategori_belanja' => 'OPERASIONAL',
            'tahun_anggaran' => 2026,
        ]);

        // Rekening Pemberdayaan Pataruman
        $this->rekeningPemberdayaanPataruman = AnggaranRekening::create([
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'kode_sub_kegiatan' => '7.01.03.2.02.0003',
            'nama_sub_kegiatan' => 'Pemberdayaan Masyarakat di Kelurahan',
            'kode_rekening' => '5.1.02.02.01.0026',
            'nama_rekening' => 'Belanja Jasa Tenaga Kerja Pemberdayaan',
            'pagu_murni' => 50000000,
            'pagu_perubahan' => 50000000,
            'realisasi' => 0,
            'kategori_belanja' => 'OPERASIONAL',
            'tahun_anggaran' => 2026,
        ]);

        // Rekening Kecamatan
        $this->rekeningKecamatan = AnggaranRekening::create([
            'unit_kerja_id' => $this->kecamatan->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Penyusunan Dokumen Perencanaan Kecamatan',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja Alat Tulis Kantor Kecamatan',
            'pagu_murni' => 50000000,
            'pagu_perubahan' => 50000000,
            'realisasi' => 0,
            'kategori_belanja' => 'OPERASIONAL',
            'tahun_anggaran' => 2026,
        ]);

        // Kas bulanan untuk 12 bulan
        foreach ([$this->rekeningSarprasPataruman, $this->rekeningPemberdayaanPataruman, $this->rekeningKecamatan] as $rek) {
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
            'role' => 'OPERATOR',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
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

        $this->operatorKecamatan = User::factory()->create([
            'role' => 'OPERATOR',
            'unit_kerja_id' => $this->kecamatan->id,
        ]);

        $this->pptkKecamatan = User::factory()->create([
            'role' => 'PPTK',
            'unit_kerja_id' => $this->kecamatan->id,
        ]);

        $this->verifikatorKecamatan = User::factory()->create([
            'role' => 'VERIFIKATOR',
            'unit_kerja_id' => $this->kecamatan->id,
        ]);
    }

    public function test_kelurahan_receipt_sequential_approval_reaches_menunggu_lurah_then_verifikasi(): void
    {
        // 1. Buat kuitansi Sarpras Pataruman
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/PTR/2026/01/0001',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'anggaran_rekening_id' => $this->rekeningSarprasPataruman->id,
            'user_id' => $this->operatorPataruman->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-20',
            'uraian' => 'Semen dan Pasir Drainase Sarpras',
            'nama_penerima' => 'Toko Bangunan Sejahtera',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 2000000,
            'total_pajak' => 0,
            'nominal_neto' => 2000000,
            'status' => 'MENUNGGU_PPTK',
        ]);

        // Kunci pagu kas sebesar 2.000.000 pada bulan 1
        app(BudgetReservationServiceInterface::class)->reserveBudget(
            (string) $this->rekeningSarprasPataruman->id,
            1,
            2000000,
            1
        );

        // 2. PPTK Sarpras Pataruman menyetujui -> harus berstatus MENUNGGU_LURAH
        $responsePptk = $this->actingAs($this->pptkSarprasPataruman)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/pptk-approve", [
                'catatan' => 'Pekerjaan fisik sarpras telah diperiksa',
            ]);

        $responsePptk->assertOk()
            ->assertJsonPath('data.status', 'MENUNGGU_LURAH')
            ->assertJsonPath('data.pptk_user_id', $this->pptkSarprasPataruman->id);

        $kuitansi->refresh();
        $this->assertEquals('MENUNGGU_LURAH', $kuitansi->status);
        $this->assertEquals($this->pptkSarprasPataruman->id, $kuitansi->pptk_user_id);
        $this->assertNotNull($kuitansi->tanggal_pptk_approve);

        // 3. Lurah Pataruman mengotorisasi -> harus berstatus MENUNGGU_VERIFIKASI
        $responseLurah = $this->actingAs($this->lurahPataruman)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/lurah-approve", [
                'catatan' => 'Disetujui KPA Kelurahan Pataruman',
            ]);

        $responseLurah->assertOk()
            ->assertJsonPath('data.status', 'MENUNGGU_VERIFIKASI')
            ->assertJsonPath('data.lurah_user_id', $this->lurahPataruman->id);

        $kuitansi->refresh();
        $this->assertEquals('MENUNGGU_VERIFIKASI', $kuitansi->status);
        $this->assertEquals($this->lurahPataruman->id, $kuitansi->lurah_user_id);
        $this->assertNotNull($kuitansi->tanggal_lurah_approve);
    }

    public function test_kecamatan_receipt_approval_bypasses_lurah_and_goes_directly_to_verifikasi(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/KEC/2026/01/0001',
            'unit_kerja_id' => $this->kecamatan->id,
            'anggaran_rekening_id' => $this->rekeningKecamatan->id,
            'user_id' => $this->operatorKecamatan->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-20',
            'uraian' => 'Kertas F4 Kecamatan',
            'nama_penerima' => 'Toko ATK Garut',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 1000000,
            'total_pajak' => 0,
            'nominal_neto' => 1000000,
            'status' => 'MENUNGGU_PPTK',
        ]);

        $response = $this->actingAs($this->pptkKecamatan)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/pptk-approve", [
                'catatan' => 'Disetujui PPTK Kecamatan',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'MENUNGGU_VERIFIKASI')
            ->assertJsonPath('data.pptk_user_id', $this->pptkKecamatan->id);

        $kuitansi->refresh();
        $this->assertEquals('MENUNGGU_VERIFIKASI', $kuitansi->status);
        $this->assertNull($kuitansi->lurah_user_id);
    }

    public function test_lurah_rejection_restores_locked_budget_and_returns_to_draft(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/PTR/2026/01/0002',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'anggaran_rekening_id' => $this->rekeningSarprasPataruman->id,
            'user_id' => $this->operatorPataruman->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-22',
            'uraian' => 'Cat Tembok Posyandu Sarpras',
            'nama_penerima' => 'Toko Cat',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 1500000,
            'total_pajak' => 0,
            'nominal_neto' => 1500000,
            'status' => 'MENUNGGU_LURAH',
        ]);

        // Kunci pagu
        app(BudgetReservationServiceInterface::class)->reserveBudget(
            (string) $this->rekeningSarprasPataruman->id,
            1,
            1500000,
            1
        );

        $kasBulananBefore = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekeningSarprasPataruman->id)
            ->where('bulan', 1)
            ->first();
        $this->assertEquals(1500000, (float) $kasBulananBefore->terkunci_bulan);

        $response = $this->actingAs($this->lurahPataruman)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/lurah-reject", [
                'alasan' => 'Dokumentasi foto fisik belum lengkap, mohon lengkapi',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'DRAFT');

        $kuitansi->refresh();
        $this->assertEquals('DRAFT', $kuitansi->status);
        $this->assertEquals($this->lurahPataruman->id, $kuitansi->lurah_user_id);
        $this->assertEquals('Dokumentasi foto fisik belum lengkap, mohon lengkapi', $kuitansi->catatan_lurah);

        $kasBulananAfter = AnggaranKasBulanan::where('anggaran_rekening_id', $this->rekeningSarprasPataruman->id)
            ->where('bulan', 1)
            ->first();
        $this->assertEquals(0, (float) $kasBulananAfter->terkunci_bulan);
    }

    public function test_pptk_subkegiatan_two_tier_isolation_returns_forbidden(): void
    {
        $kuitansiSarpras = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/PTR/2026/01/0003',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'anggaran_rekening_id' => $this->rekeningSarprasPataruman->id,
            'user_id' => $this->operatorPataruman->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-25',
            'uraian' => 'Semen Drainase Sarpras',
            'nama_penerima' => 'Toko Bangunan',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 1000000,
            'total_pajak' => 0,
            'nominal_neto' => 1000000,
            'status' => 'MENUNGGU_PPTK',
        ]);

        // PPTK Pemberdayaan mencoba approve Sarpras -> harus 403
        $responseApprove = $this->actingAs($this->pptkPemberdayaanPataruman)
            ->postJson("/api/v1/receipts/{$kuitansiSarpras->id}/pptk-approve", [
                'catatan' => 'Bypass attempt',
            ]);
        $responseApprove->assertStatus(403);

        // PPTK Pemberdayaan mencoba reject Sarpras -> harus 403
        $responseReject = $this->actingAs($this->pptkPemberdayaanPataruman)
            ->postJson("/api/v1/receipts/{$kuitansiSarpras->id}/pptk-reject", [
                'alasan' => 'Bypass reject attempt',
            ]);
        $responseReject->assertStatus(403);
    }

    public function test_cross_kelurahan_lurah_authorization_returns_forbidden(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/PTR/2026/01/0004',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'anggaran_rekening_id' => $this->rekeningSarprasPataruman->id,
            'user_id' => $this->operatorPataruman->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-25',
            'uraian' => 'Uraian Uji IDOR Lurah',
            'nama_penerima' => 'Penerima Uji',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 500000,
            'total_pajak' => 0,
            'nominal_neto' => 500000,
            'status' => 'MENUNGGU_LURAH',
        ]);

        // Lurah Sukagalih mencoba approve Pataruman -> harus 403
        $responseApprove = $this->actingAs($this->lurahSukagalih)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/lurah-approve", [
                'catatan' => 'Cross kelurahan approve attempt',
            ]);
        $responseApprove->assertStatus(403);

        // Lurah Sukagalih mencoba reject Pataruman -> harus 403
        $responseReject = $this->actingAs($this->lurahSukagalih)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/lurah-reject", [
                'alasan' => 'Cross kelurahan reject attempt',
            ]);
        $responseReject->assertStatus(403);
    }

    public function test_verification_anti_bypass_rejects_receipt_in_menunggu_lurah(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/PTR/2026/01/0005',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'anggaran_rekening_id' => $this->rekeningSarprasPataruman->id,
            'user_id' => $this->operatorPataruman->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-26',
            'uraian' => 'Bypass attempt verifikasi',
            'nama_penerima' => 'Penerima Uji',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 500000,
            'total_pajak' => 0,
            'nominal_neto' => 500000,
            'status' => 'MENUNGGU_LURAH',
        ]);

        $verificationService = app(VerificationService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Kuitansi harus diotorisasi oleh Lurah terlebih dahulu sebelum dapat diverifikasi.');

        $verificationService->processAction(
            (string) $kuitansi->id,
            'VERIFIED',
            [],
            (string) $this->verifikatorKecamatan->id
        );
    }
}
