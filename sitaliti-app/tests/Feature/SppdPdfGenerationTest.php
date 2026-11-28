<?php

namespace Tests\Feature;

use App\Models\AnggaranRekening;
use App\Models\Pegawai;
use App\Models\SppdAssignment;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\TerbilangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SppdPdfGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function createUnitAndUser(string $kodeUnit, string $namaUnit, string $tipe = 'KECAMATAN'): array
    {
        $unit = UnitKerja::create([
            'kode_unit' => $kodeUnit,
            'nama_unit' => $namaUnit,
            'tipe' => $tipe,
            'kop_alamat' => 'Jalan RSU dr. Slamet No.8',
            'kop_telepon_email' => 'email : tarogongkidul.kecamatan@gmail.com',
            'kop_kode_pos' => '44150',
            'pejabat_nama' => 'AHMAD MAWARDI,AP,.SE',
            'pejabat_nip' => '197005101990091001',
            'pejabat_jabatan' => $tipe === 'KELURAHAN' ? "Lurah {$namaUnit}" : 'Camat Tarogong Kidul',
            'bendahara_nama' => 'YADI MULYADI',
            'bendahara_nip' => '197402062010011006',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => $tipe === 'KELURAHAN' ? 'BPP' : 'BP',
            'unit_kerja_id' => $unit->id,
        ]);

        return [$unit, $user];
    }

    public function test_generates_valid_f4_pdf_for_single_non_asn_across_all_scopes(): void
    {
        [$unit, $user] = $this->createUnitAndUser('7.01.0.00.0.00.41.0000', 'Kecamatan Tarogong Kidul');

        $nonAsn = Pegawai::create([
            'unit_kerja_id' => $unit->id,
            'status_kepegawaian' => 'NON_ASN',
            'nip' => null,
            'nama' => 'Teguh Rahayu',
            'jabatan' => 'Pelaksana',
            'is_active' => true,
        ]);

        $assignment = SppdAssignment::create([
            'unit_kerja_id' => $unit->id,
            'user_id' => $user->id,
            'nomor_surat_tugas' => '001/Tarkid/SPPD/X/2026',
            'maksud_perjalanan' => 'Koordinasi Kelurahan Sukakarya',
            'tempat_berangkat' => 'Kecamatan',
            'tempat_tujuan' => 'Kelurahan Sukakarya',
            'tanggal_berangkat' => '2026-10-19',
            'tanggal_kembali' => '2026-10-19',
            'pemberi_perintah_nama' => 'AHMAD MAWARDI,AP,.SE',
            'pemberi_perintah_nip' => '197005101990091001',
            'pemberi_perintah_jabatan' => 'Camat Tarogong Kidul',
            'laporan_hasil' => 'Monitoring kelompok masyarakat berjalan lancar.',
            'status' => 'TERBIT',
        ]);

        $peserta = $assignment->peserta()->create([
            'pegawai_id' => $nonAsn->id,
            'urutan' => 1,
            'nomor_spd' => '001/SPD/X/2026',
            'tingkat_biaya' => 'Rp 70.000',
        ]);
        $peserta->biayaItems()->create([
            'uraian_biaya' => 'Uang Transpor Lokal',
            'nominal' => 70000,
        ]);

        foreach (['pre', 'post', 'all'] as $scope) {
            $res = $this->actingAs($user)
                ->get("/sppd-assignments/{$assignment->id}/pdf?scope={$scope}");

            $res->assertOk();
            $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
            $this->assertStringStartsWith('%PDF-', $res->getContent());
        }

        $html = view('pdf.sppd.bundle', [
            'assignment' => $assignment->fresh(['unitKerja', 'rekening', 'peserta.pegawai', 'peserta.biayaItems', 'kuitansi.rekening']),
            'scope' => 'all',
            'lamaHari' => 1,
            'terbilang' => new TerbilangService,
            'logoDataUri' => null,
        ])->render();

        $this->assertStringContainsString('Teguh Rahayu', $html);
        $this->assertStringContainsString('PERHITUNGAN SPD RAMPUNG', $html);
        $this->assertStringContainsString('LAPORAN PERJALANAN DINAS', $html);
        $this->assertStringNotContainsString('DAFTAR NOMINATIF', $html);
    }

    public function test_generates_group_pdf_with_daftar_nominatif_and_enforces_unit_rbac(): void
    {
        [$unitA, $userA] = $this->createUnitAndUser('7.01.0.00.0.00.41.0000', 'Kecamatan Tarogong Kidul');
        [, $userB] = $this->createUnitAndUser('7.01.0.00.0.00.41.0006', 'Kelurahan Jayawaras', 'KELURAHAN');

        $assignment = SppdAssignment::create([
            'unit_kerja_id' => $unitA->id,
            'user_id' => $userA->id,
            'nomor_surat_tugas' => '005/Tarkid/SPPD/III/2026',
            'maksud_perjalanan' => 'Safari Ramadhan tingkat Kecamatan',
            'tempat_berangkat' => 'Kecamatan',
            'tempat_tujuan' => 'Desa Sukabakti',
            'tanggal_berangkat' => '2026-03-12',
            'tanggal_kembali' => '2026-03-12',
            'pemberi_perintah_nama' => 'AHMAD MAWARDI,AP,.SE',
            'pemberi_perintah_nip' => '197005101990091001',
            'pemberi_perintah_jabatan' => 'Camat Tarogong Kidul',
            'status' => 'TERBIT',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $pegawai = Pegawai::create([
                'unit_kerja_id' => $unitA->id,
                'status_kepegawaian' => $i === 5 ? 'NON_ASN' : 'ASN',
                'nip' => $i === 5 ? null : "19800101201001100{$i}",
                'nama' => "Peserta {$i}",
                'jabatan' => 'Pelaksana',
                'golongan' => $i === 5 ? null : 'Penata III/c',
                'is_active' => true,
            ]);

            $peserta = $assignment->peserta()->create([
                'pegawai_id' => $pegawai->id,
                'urutan' => $i,
                'nomor_spd' => "00{$i}/SPD/III/2026",
            ]);
            $peserta->biayaItems()->create([
                'uraian_biaya' => 'Uang Transpor Lokal',
                'nominal' => 70000,
            ]);
        }

        $res = $this->actingAs($userA)->get("/sppd-assignments/{$assignment->id}/pdf?scope=all");
        $res->assertOk();
        $this->assertStringStartsWith('%PDF-', $res->getContent());

        $html = view('pdf.sppd.bundle', [
            'assignment' => $assignment->fresh(['unitKerja', 'rekening', 'peserta.pegawai', 'peserta.biayaItems', 'kuitansi.rekening']),
            'scope' => 'all',
            'lamaHari' => 1,
            'terbilang' => new TerbilangService,
            'logoDataUri' => null,
        ])->render();

        $this->assertStringContainsString('DAFTAR NOMINATIF PENERIMAAN BIAYA PERJALANAN DINAS', $html);
        $this->assertStringContainsString('Tiga ratus lima puluh ribu rupiah', $html);

        // Operator kelurahan lain ditolak 403
        $this->actingAs($userB)
            ->get("/sppd-assignments/{$assignment->id}/pdf?scope=all")
            ->assertForbidden();
    }

    public function test_kelurahan_sppd_with_kecamatan_rekening_renders_qq_attribution_in_pdf(): void
    {
        [$kecamatan] = $this->createUnitAndUser('7.01.0.00.0.00.41.0000', 'Kecamatan Tarogong Kidul');
        [$kelurahan, $bppKel] = $this->createUnitAndUser('7.01.0.00.0.00.41.0006', 'Kelurahan Jayawaras', 'KELURAHAN');

        $rekKec = AnggaranRekening::create([
            'unit_kerja_id' => $kecamatan->id,
            'tahun_anggaran' => 2026,
            'kode_sub_kegiatan' => '7.01.01.2.01.01',
            'nama_sub_kegiatan' => 'Koordinasi Kecamatan',
            'kode_rekening' => '5.1.02.04.01.0009',
            'nama_rekening' => 'Perjadin Kecamatan',
            'kategori_belanja' => 'B.4',
            'pagu_dpa' => 10000000,
        ]);

        $pegawai = Pegawai::create([
            'unit_kerja_id' => $kelurahan->id,
            'status_kepegawaian' => 'ASN',
            'nip' => '196612111986032003',
            'nama' => 'Lina Maryati',
            'jabatan' => 'Kasi Pemerintahan',
            'golongan' => 'Penata III/c',
            'is_active' => true,
        ]);

        $assignment = SppdAssignment::create([
            'unit_kerja_id' => $kelurahan->id,
            'user_id' => $bppKel->id,
            'anggaran_rekening_id' => $rekKec->id,
            'nomor_surat_tugas' => '821/01-Kel/2026',
            'maksud_perjalanan' => 'Rapat Evaluasi PBB',
            'tempat_berangkat' => 'Jayawaras',
            'tempat_tujuan' => 'Kecamatan Tarogong Kidul',
            'tanggal_berangkat' => '2026-10-19',
            'tanggal_kembali' => '2026-10-19',
            'pemberi_perintah_nama' => 'YOYO,S.IP',
            'pemberi_perintah_nip' => '197503132010011007',
            'pemberi_perintah_jabatan' => 'Lurah Jayawaras',
            'status' => 'TERBIT',
        ]);

        $peserta = $assignment->peserta()->create([
            'pegawai_id' => $pegawai->id,
            'urutan' => 1,
            'nomor_spd' => '001/SPD/JW/X/2026',
        ]);
        $peserta->biayaItems()->create([
            'uraian_biaya' => 'Uang Transpor Lokal',
            'nominal' => 70000,
        ]);

        $html = view('pdf.sppd.bundle', [
            'assignment' => $assignment->fresh(['unitKerja', 'rekening.unitKerja', 'peserta.pegawai', 'peserta.biayaItems', 'kuitansi.rekening.unitKerja']),
            'scope' => 'all',
            'lamaHari' => 1,
            'terbilang' => new TerbilangService,
            'logoDataUri' => null,
        ])->render();

        $this->assertStringContainsString('Kecamatan Tarogong Kidul (qq. Kelurahan Jayawaras)', $html);
        $this->assertStringContainsString('5.1.02.04.01.0009', $html);
    }
}
