<?php

namespace Tests\Feature;

use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\Pegawai;
use App\Models\PptkSubkegiatanAssignment;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SppdKelurahanKecamatanRekeningTest extends TestCase
{
    use RefreshDatabase;

    private function seedUnitsAndRekening(): array
    {
        $kecamatan = UnitKerja::create([
            'kode_unit' => '7.01.0.00.0.00.41.0000',
            'nama_unit' => 'Kecamatan Tarogong Kidul',
            'tipe' => 'KECAMATAN',
            'pejabat_nama' => 'AHMAD MAWARDI,AP,.SE',
            'pejabat_nip' => '197005101990091001',
            'pejabat_jabatan' => 'Camat Tarogong Kidul',
            'bendahara_nama' => 'YADI MULYADI',
            'bendahara_nip' => '197402062010011006',
            'is_active' => true,
        ]);

        $kelurahanA = UnitKerja::create([
            'kode_unit' => '7.01.0.00.0.00.41.0006',
            'nama_unit' => 'Kelurahan Jayawaras',
            'tipe' => 'KELURAHAN',
            'pejabat_nama' => 'YOYO,S.IP',
            'pejabat_nip' => '197503132010011007',
            'pejabat_jabatan' => 'Lurah Jayawaras',
            'is_active' => true,
        ]);

        $kelurahanB = UnitKerja::create([
            'kode_unit' => '7.01.0.00.0.00.41.0001',
            'nama_unit' => 'Kelurahan Pataruman',
            'tipe' => 'KELURAHAN',
            'is_active' => true,
        ]);

        $rekSppdKec = AnggaranRekening::create([
            'unit_kerja_id' => $kecamatan->id,
            'tahun_anggaran' => 2026,
            'kode_sub_kegiatan' => '7.01.01.2.01.01',
            'nama_sub_kegiatan' => 'Koordinasi Pemerintahan Kecamatan',
            'kode_rekening' => '5.1.02.04.01.0003',
            'nama_rekening' => 'Belanja Perjalanan Dinas Dalam Kota',
            'kategori_belanja' => 'B.4',
            'pagu_dpa' => 10000000,
        ]);

        $rekAtkKec = AnggaranRekening::create([
            'unit_kerja_id' => $kecamatan->id,
            'tahun_anggaran' => 2026,
            'kode_sub_kegiatan' => '7.01.01.2.01.02',
            'nama_sub_kegiatan' => 'Administrasi Umum Kecamatan',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja ATK',
            'kategori_belanja' => 'B.1',
            'pagu_dpa' => 5000000,
        ]);

        $rekHibahKelB = AnggaranRekening::create([
            'unit_kerja_id' => $kelurahanB->id,
            'tahun_anggaran' => 2026,
            'kode_sub_kegiatan' => '7.01.02.2.01.01',
            'nama_sub_kegiatan' => 'Hibah Kelurahan Pataruman',
            'kode_rekening' => '5.1.05.05.01.0001',
            'nama_rekening' => 'Belanja Hibah',
            'kategori_belanja' => 'B.1',
            'pagu_dpa' => 5000000,
        ]);

        return [$kecamatan, $kelurahanA, $kelurahanB, $rekSppdKec, $rekAtkKec, $rekHibahKelB];
    }

    public function test_kelurahan_bpp_can_input_kecamatan_sppd_rekening_only_and_pptk_can_access_receipt(): void
    {
        [, $kelurahanA, , $rekSppdKec, $rekAtkKec, $rekHibahKelB] = $this->seedUnitsAndRekening();

        $bppKelA = User::factory()->create(['role' => 'BPP', 'unit_kerja_id' => $kelurahanA->id]);
        $pptkKelA = User::factory()->create(['role' => 'PPTK', 'unit_kerja_id' => $kelurahanA->id]);

        PptkSubkegiatanAssignment::create([
            'user_id' => $pptkKelA->id,
            'unit_kerja_id' => $kelurahanA->id,
            'kode_sub_kegiatan' => '7.01.02.2.01.99',
        ]);

        $this->assertTrue($bppKelA->canInputRekening($rekSppdKec));
        $this->assertFalse($bppKelA->canInputRekening($rekAtkKec));
        $this->assertFalse($bppKelA->canInputRekening($rekHibahKelB));

        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-KEL-SPPD-01',
            'unit_kerja_id' => $kelurahanA->id,
            'anggaran_rekening_id' => $rekSppdKec->id,
            'user_id' => $bppKelA->id,
            'bulan_anggaran' => 10,
            'tanggal_transaksi' => '2026-10-19',
            'uraian' => 'Perjadin Kelurahan',
            'nama_penerima' => 'Lina Maryati',
            'nominal_bruto' => 70000,
            'total_pajak' => 0,
            'nominal_neto' => 70000,
            'status' => 'MENUNGGU_PPTK',
        ]);

        $this->assertTrue($pptkKelA->canAccessReceipt($kuitansi));
    }

    public function test_kelurahan_receipt_preserves_unit_kerja_id_on_create_and_update_and_blocks_non_sppd_switch(): void
    {
        [, $kelurahanA, , $rekSppdKec] = $this->seedUnitsAndRekening();
        $bppKelA = User::factory()->create(['role' => 'BPP', 'unit_kerja_id' => $kelurahanA->id]);

        $rekHibahKelA = AnggaranRekening::create([
            'unit_kerja_id' => $kelurahanA->id,
            'tahun_anggaran' => 2026,
            'kode_sub_kegiatan' => '7.01.02.2.01.01',
            'nama_sub_kegiatan' => 'Hibah Kelurahan Jayawaras',
            'kode_rekening' => '5.1.05.05.01.0001',
            'nama_rekening' => 'Belanja Hibah',
            'kategori_belanja' => 'B.1',
            'pagu_dpa' => 5000000,
        ]);

        // 1. Store via API dengan rekening SPPD Kecamatan tetap mencatat unit_kerja_id Kelurahan A
        $storeRes = $this->actingAs($bppKelA)
            ->postJson('/api/v1/receipts', [
                'anggaran_rekening_id' => $rekSppdKec->id,
                'bulan_anggaran' => 10,
                'tanggal_transaksi' => '2026-10-19',
                'uraian' => 'Perjadin Kelurahan Jayawaras',
                'nama_penerima' => 'Lina Maryati',
                'golongan_asn' => 'GOLONGAN_III',
                'items' => [
                    ['nama_barang' => 'Uang Transpor Lokal', 'volume' => 1, 'satuan' => 'Kegiatan', 'harga_satuan' => 70000],
                ],
            ])
            ->assertCreated();

        $kuitansiId = $storeRes->json('data.id');
        $this->assertDatabaseHas('kuitansi', [
            'id' => $kuitansiId,
            'unit_kerja_id' => $kelurahanA->id,
            'anggaran_rekening_id' => $rekSppdKec->id,
        ]);

        // 2. Update via API tidak menggeser unit_kerja_id ke Kecamatan
        $this->actingAs($bppKelA)
            ->putJson("/api/v1/receipts/{$kuitansiId}", [
                'anggaran_rekening_id' => $rekSppdKec->id,
                'bulan_anggaran' => 10,
                'tanggal_transaksi' => '2026-10-19',
                'uraian' => 'Perjadin Kelurahan Jayawaras (Revisi)',
                'nama_penerima' => 'Lina Maryati',
                'golongan_asn' => 'GOLONGAN_III',
                'items' => [
                    ['nama_barang' => 'Uang Transpor Lokal', 'volume' => 1, 'satuan' => 'Kegiatan', 'harga_satuan' => 70000],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('kuitansi', [
            'id' => $kuitansiId,
            'unit_kerja_id' => $kelurahanA->id,
        ]);

        // 3. Kuitansi non-SPPD (B.1) ditolak 422 jika dipindah ke rekening SPPD Kecamatan
        $nonSppd = Kuitansi::create([
            'nomor_kuitansi' => 'KW-HIBAH-01',
            'unit_kerja_id' => $kelurahanA->id,
            'anggaran_rekening_id' => $rekHibahKelA->id,
            'user_id' => $bppKelA->id,
            'bulan_anggaran' => 10,
            'tanggal_transaksi' => '2026-10-19',
            'uraian' => 'Hibah',
            'nama_penerima' => 'Penerima',
            'nominal_bruto' => 100000,
            'total_pajak' => 0,
            'nominal_neto' => 100000,
            'status' => 'DRAFT',
        ]);

        $this->actingAs($bppKelA)
            ->putJson("/api/v1/receipts/{$nonSppd->id}", [
                'anggaran_rekening_id' => $rekSppdKec->id,
                'bulan_anggaran' => 10,
                'tanggal_transaksi' => '2026-10-19',
                'uraian' => 'Coba pindah ke SPPD Kecamatan',
                'nama_penerima' => 'Penerima',
                'items' => [
                    ['nama_barang' => 'Item', 'volume' => 1, 'satuan' => 'Paket', 'harga_satuan' => 100000],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_REKENING_CROSS_UNIT');
    }

    public function test_kelurahan_sppd_assignment_validates_rekening_and_converts_to_kelurahan_kuitansi(): void
    {
        [, $kelurahanA, , $rekSppdKec, $rekAtkKec, $rekHibahKelB] = $this->seedUnitsAndRekening();

        $bppKelA = User::factory()->create(['role' => 'BPP', 'unit_kerja_id' => $kelurahanA->id]);
        $pptkKelA = User::factory()->create(['role' => 'PPTK', 'unit_kerja_id' => $kelurahanA->id]);

        $pegawai = Pegawai::create([
            'unit_kerja_id' => $kelurahanA->id,
            'status_kepegawaian' => 'ASN',
            'nip' => '196612111986032003',
            'nama' => 'Lina Maryati',
            'jabatan' => 'Kasi Pemerintahan',
            'golongan' => 'Penata III/c',
            'is_active' => true,
        ]);

        // Rekening non-SPPD Kecamatan ditolak 422
        $this->actingAs($bppKelA)
            ->postJson('/api/v1/sppd-assignments', [
                'anggaran_rekening_id' => $rekAtkKec->id,
                'maksud_perjalanan' => 'Rapat PBB',
                'tempat_berangkat' => 'Jayawaras',
                'tempat_tujuan' => 'Kecamatan',
                'tanggal_berangkat' => '2026-10-21',
                'tanggal_kembali' => '2026-10-21',
                'peserta' => [['pegawai_id' => $pegawai->id]],
            ])
            ->assertStatus(422);

        // Rekening Kelurahan lain ditolak 422
        $this->actingAs($bppKelA)
            ->postJson('/api/v1/sppd-assignments', [
                'anggaran_rekening_id' => $rekHibahKelB->id,
                'maksud_perjalanan' => 'Rapat PBB',
                'tempat_berangkat' => 'Jayawaras',
                'tempat_tujuan' => 'Kecamatan',
                'tanggal_berangkat' => '2026-10-21',
                'tanggal_kembali' => '2026-10-21',
                'peserta' => [['pegawai_id' => $pegawai->id]],
            ])
            ->assertStatus(422);

        // Rekening SPPD Kecamatan diterima 201
        $createRes = $this->actingAs($bppKelA)
            ->postJson('/api/v1/sppd-assignments', [
                'anggaran_rekening_id' => $rekSppdKec->id,
                'maksud_perjalanan' => 'Rapat PBB',
                'tempat_berangkat' => 'Jayawaras',
                'tempat_tujuan' => 'Kecamatan',
                'tanggal_berangkat' => '2026-10-21',
                'tanggal_kembali' => '2026-10-21',
                'peserta' => [
                    [
                        'pegawai_id' => $pegawai->id,
                        'nomor_spd' => '001/SPD/KEL/X/2026',
                        'biaya_items' => [
                            ['uraian_biaya' => 'Uang Transpor Lokal', 'nominal' => 70000],
                        ],
                    ],
                ],
            ])
            ->assertCreated();

        $assignmentId = $createRes->json('data.id');

        // PPTK ditolak 403 saat mencoba konversi
        $this->actingAs($pptkKelA)
            ->postJson("/api/v1/sppd-assignments/{$assignmentId}/convert-kuitansi", [
                'anggaran_rekening_id' => $rekSppdKec->id,
                'bulan_anggaran' => 10,
            ])
            ->assertForbidden();

        // BPP Kelurahan konversi -> Kuitansi unit_kerja_id Kelurahan A, rekening Kecamatan
        $this->actingAs($bppKelA)
            ->postJson("/api/v1/sppd-assignments/{$assignmentId}/convert-kuitansi", [
                'anggaran_rekening_id' => $rekSppdKec->id,
                'bulan_anggaran' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.kuitansi.unit_kerja_id', $kelurahanA->id)
            ->assertJsonPath('data.kuitansi.anggaran_rekening_id', $rekSppdKec->id);
    }
}
