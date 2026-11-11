<?php

namespace Tests\Feature\Security;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiLampiran;
use App\Models\PptkSubkegiatanAssignment;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrossUnitIdorIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected UnitKerja $kecamatan;

    protected UnitKerja $kelurahanPataruman;

    protected UnitKerja $kelurahanSukagalih;

    protected AnggaranRekening $rekeningSarprasPataruman;

    protected AnggaranRekening $rekeningPemberdayaanPataruman;

    protected AnggaranRekening $rekeningSukagalih;

    protected AnggaranRekening $rekeningKecamatan;

    protected User $operatorPataruman;

    protected User $operatorSukagalih;

    protected User $pptkSarprasPataruman;

    protected User $pptkPemberdayaanPataruman;

    protected User $lurahPataruman;

    protected User $lurahSukagalih;

    protected User $bppPataruman;

    protected User $verifikatorKecamatan;

    protected User $bpKecamatan;

    protected Kuitansi $kuitansiSarprasPataruman;

    protected Kuitansi $kuitansiPemberdayaanPataruman;

    protected Kuitansi $kuitansiSukagalih;

    protected KuitansiLampiran $lampiranSarpras;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

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

        // Rekening-rekening
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

        $this->rekeningKecamatan = AnggaranRekening::create([
            'unit_kerja_id' => $this->kecamatan->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Penyusunan Rencana Kerja Kecamatan',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja ATK Kantor Kecamatan',
            'pagu_murni' => 50000000,
            'pagu_perubahan' => 50000000,
            'realisasi' => 0,
            'kategori_belanja' => 'OPERASIONAL',
            'tahun_anggaran' => 2026,
        ]);

        // Kas bulanan
        foreach ([$this->rekeningSarprasPataruman, $this->rekeningPemberdayaanPataruman, $this->rekeningSukagalih, $this->rekeningKecamatan] as $rek) {
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

        $this->operatorSukagalih = User::factory()->create([
            'role' => 'OPERATOR',
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

        $this->bppPataruman = User::factory()->create([
            'role' => 'BPP',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
        ]);

        $this->bpKecamatan = User::factory()->create([
            'role' => 'BPP',
            'unit_kerja_id' => $this->kecamatan->id,
        ]);

        $this->verifikatorKecamatan = User::factory()->create([
            'role' => 'VERIFIKATOR',
            'unit_kerja_id' => $this->kecamatan->id,
        ]);

        // Kuitansi 1: Sarpras Pataruman
        $this->kuitansiSarprasPataruman = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/PTR/2026/01/SARPRAS',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'anggaran_rekening_id' => $this->rekeningSarprasPataruman->id,
            'user_id' => $this->operatorPataruman->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-15',
            'uraian' => 'Semen dan Pasir Sarpras Pataruman',
            'nama_penerima' => 'Toko Material',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 1000000,
            'total_pajak' => 0,
            'nominal_neto' => 1000000,
            'status' => 'MENUNGGU_PPTK',
        ]);

        // Fake attachment for Sarpras Pataruman
        $filePath = 'attachments/nota_sarpras.pdf';
        Storage::disk('local')->put($filePath, 'PDF CONTENT');
        $this->lampiranSarpras = KuitansiLampiran::create([
            'kuitansi_id' => $this->kuitansiSarprasPataruman->id,
            'anggaran_rekening_id' => $this->rekeningSarprasPataruman->id,
            'nama_dokumen' => 'Nota Pembelian ATK',
            'file_name' => 'nota_sarpras.pdf',
            'file_path' => $filePath,
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'PDF CONTENT'),
        ]);

        // Kuitansi 2: Pemberdayaan Pataruman
        $this->kuitansiPemberdayaanPataruman = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/PTR/2026/01/PEMBERDAYAAN',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'anggaran_rekening_id' => $this->rekeningPemberdayaanPataruman->id,
            'user_id' => $this->operatorPataruman->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-16',
            'uraian' => 'Honor Fasilitator Pataruman',
            'nama_penerima' => 'Fasilitator',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 1200000,
            'total_pajak' => 0,
            'nominal_neto' => 1200000,
            'status' => 'MENUNGGU_PPTK',
        ]);

        // Kuitansi 3: Sukagalih
        $this->kuitansiSukagalih = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/SKG/2026/01/SARPRAS',
            'unit_kerja_id' => $this->kelurahanSukagalih->id,
            'anggaran_rekening_id' => $this->rekeningSukagalih->id,
            'user_id' => $this->operatorSukagalih->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-17',
            'uraian' => 'Pengadaan ATK Sarpras Sukagalih',
            'nama_penerima' => 'Toko ATK Sukagalih',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 800000,
            'total_pajak' => 0,
            'nominal_neto' => 800000,
            'status' => 'MENUNGGU_PPTK',
        ]);
    }

    public function test_receipt_query_service_scoping_for_lurah_pptk_and_skpd_wide(): void
    {
        // 1. Lurah Pataruman: hanya melihat kuitansi Pataruman (2 kuitansi)
        $resLurah = $this->actingAs($this->lurahPataruman)->getJson('/api/v1/receipts');
        $resLurah->assertOk();
        $nomorsLurah = collect($resLurah->json('data.data'))->pluck('nomor_kuitansi')->all();
        $this->assertContains('KWT/PTR/2026/01/SARPRAS', $nomorsLurah);
        $this->assertContains('KWT/PTR/2026/01/PEMBERDAYAAN', $nomorsLurah);
        $this->assertNotContains('KWT/SKG/2026/01/SARPRAS', $nomorsLurah);

        // 2. PPTK Sarpras Pataruman: hanya melihat Sarpras Pataruman (1 kuitansi)
        $resPptkSarpras = $this->actingAs($this->pptkSarprasPataruman)->getJson('/api/v1/receipts');
        $resPptkSarpras->assertOk();
        $nomorsPptk = collect($resPptkSarpras->json('data.data'))->pluck('nomor_kuitansi')->all();
        $this->assertContains('KWT/PTR/2026/01/SARPRAS', $nomorsPptk);
        $this->assertNotContains('KWT/PTR/2026/01/PEMBERDAYAAN', $nomorsPptk);
        $this->assertNotContains('KWT/SKG/2026/01/SARPRAS', $nomorsPptk);

        // 3. Verifikator Kecamatan: melihat seluruh 3 kuitansi lintas SKPD
        $resVerifikator = $this->actingAs($this->verifikatorKecamatan)->getJson('/api/v1/receipts');
        $resVerifikator->assertOk();
        $nomorsVerif = collect($resVerifikator->json('data.data'))->pluck('nomor_kuitansi')->all();
        $this->assertContains('KWT/PTR/2026/01/SARPRAS', $nomorsVerif);
        $this->assertContains('KWT/PTR/2026/01/PEMBERDAYAAN', $nomorsVerif);
        $this->assertContains('KWT/SKG/2026/01/SARPRAS', $nomorsVerif);
    }

    public function test_cross_unit_and_cross_subkegiatan_show_and_history_idor_is_blocked(): void
    {
        // Lurah Sukagalih tidak boleh show kuitansi Pataruman (403)
        $this->actingAs($this->lurahSukagalih)
            ->getJson("/api/v1/receipts/{$this->kuitansiSarprasPataruman->id}")
            ->assertStatus(403);

        // Lurah Sukagalih tidak boleh lihat riwayat kuitansi Pataruman (403)
        $this->actingAs($this->lurahSukagalih)
            ->getJson("/api/v1/receipts/{$this->kuitansiSarprasPataruman->id}/history")
            ->assertStatus(403);

        // PPTK Pemberdayaan tidak boleh show kuitansi Sarpras (403)
        $this->actingAs($this->pptkPemberdayaanPataruman)
            ->getJson("/api/v1/receipts/{$this->kuitansiSarprasPataruman->id}")
            ->assertStatus(403);

        // PPTK Pemberdayaan tidak boleh lihat riwayat kuitansi Sarpras (403)
        $this->actingAs($this->pptkPemberdayaanPataruman)
            ->getJson("/api/v1/receipts/{$this->kuitansiSarprasPataruman->id}/history")
            ->assertStatus(403);

        // Lurah Pataruman BERHAK show kuitansi Pataruman (200)
        $this->actingAs($this->lurahPataruman)
            ->getJson("/api/v1/receipts/{$this->kuitansiSarprasPataruman->id}")
            ->assertOk();
    }

    public function test_cross_unit_submit_idor_is_blocked(): void
    {
        // Draft kuitansi milik Pataruman
        $draftPataruman = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/PTR/DRAFT/01',
            'unit_kerja_id' => $this->kelurahanPataruman->id,
            'anggaran_rekening_id' => $this->rekeningSarprasPataruman->id,
            'user_id' => $this->operatorPataruman->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-18',
            'uraian' => 'Draft Pataruman',
            'nama_penerima' => 'Toko Draft',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 500000,
            'total_pajak' => 0,
            'nominal_neto' => 500000,
            'status' => 'DRAFT',
        ]);

        // Operator Sukagalih mencoba submit kuitansi Pataruman -> harus 403
        $this->actingAs($this->operatorSukagalih)
            ->postJson("/api/v1/receipts/{$draftPataruman->id}/submit")
            ->assertStatus(403);
    }

    public function test_cross_unit_attachment_preview_idor_is_blocked(): void
    {
        // Lurah Sukagalih mencoba preview lampiran Pataruman -> 403
        $this->actingAs($this->lurahSukagalih)
            ->get("/api/v1/receipts/{$this->kuitansiSarprasPataruman->id}/attachments/{$this->lampiranSarpras->id}/preview")
            ->assertStatus(403);

        // PPTK Pemberdayaan mencoba preview lampiran Sarpras -> 403
        $this->actingAs($this->pptkPemberdayaanPataruman)
            ->get("/api/v1/receipts/{$this->kuitansiSarprasPataruman->id}/attachments/{$this->lampiranSarpras->id}/preview")
            ->assertStatus(403);

        // Lurah Pataruman boleh preview lampiran Pataruman -> 200
        $this->actingAs($this->lurahPataruman)
            ->get("/api/v1/receipts/{$this->kuitansiSarprasPataruman->id}/attachments/{$this->lampiranSarpras->id}/preview")
            ->assertOk();
    }

    public function test_cross_unit_control_sheet_pdf_idor_is_blocked(): void
    {
        // Lurah Sukagalih mencoba unduh Lembar Kendali Pataruman -> 403
        $this->actingAs($this->lurahSukagalih)
            ->get("/api/v1/control-sheet/{$this->kuitansiSarprasPataruman->id}/pdf")
            ->assertStatus(403);

        // PPTK Pemberdayaan mencoba unduh Lembar Kendali Sarpras -> 403
        $this->actingAs($this->pptkPemberdayaanPataruman)
            ->get("/api/v1/control-sheet/{$this->kuitansiSarprasPataruman->id}/pdf")
            ->assertStatus(403);
    }

    public function test_operator_kelurahan_cannot_store_receipt_with_rekening_from_another_unit(): void
    {
        $payload = [
            'anggaran_rekening_id' => $this->rekeningKecamatan->id, // Rekening Kecamatan!
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-20',
            'uraian' => 'Percobaan Lintas Unit',
            'nama_penerima' => 'Toko Lintas',
            'metode_pembayaran' => 'TUNAI_UP',
            'items' => [
                [
                    'nama_barang' => 'Kertas A4',
                    'volume' => 2,
                    'satuan' => 'Rim',
                    'harga_satuan' => 50000,
                ],
            ],
        ];

        // Operator Pataruman mencoba simpan dengan rekening Kecamatan -> 403
        $this->actingAs($this->operatorPataruman)
            ->postJson('/api/v1/receipts', $payload)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
