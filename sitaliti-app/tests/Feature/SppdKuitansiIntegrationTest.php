<?php

namespace Tests\Feature;

use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\Pegawai;
use App\Models\SppdAssignment;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\SppdReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SppdKuitansiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function seedEnvironment(): array
    {
        $unit = UnitKerja::create([
            'kode_unit' => '7.01.0.00.0.00.41.0000',
            'nama_unit' => 'Kecamatan Tarogong Kidul',
            'tipe' => 'KECAMATAN',
            'pejabat_nama' => 'AHMAD MAWARDI,AP,.SE',
            'pejabat_jabatan' => 'Camat Tarogong Kidul',
            'is_active' => true,
        ]);

        $operator = User::factory()->create(['role' => 'BP', 'unit_kerja_id' => $unit->id]);

        $rekeningSppd = AnggaranRekening::create([
            'unit_kerja_id' => $unit->id,
            'tahun_anggaran' => 2026,
            'kode_sub_kegiatan' => '7.01.01.2.01.01',
            'nama_sub_kegiatan' => 'Koordinasi Pemerintahan',
            'kode_rekening' => '5.1.02.04.01.0003',
            'nama_rekening' => 'Belanja Perjalanan Dinas Dalam Kota',
            'kategori_belanja' => 'B.4',
            'pagu_dpa' => 10000000,
        ]);

        $rekeningAtk = AnggaranRekening::create([
            'unit_kerja_id' => $unit->id,
            'tahun_anggaran' => 2026,
            'kode_sub_kegiatan' => '7.01.01.2.01.01',
            'nama_sub_kegiatan' => 'Koordinasi Pemerintahan',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja ATK',
            'kategori_belanja' => 'B.1',
            'pagu_dpa' => 10000000,
        ]);

        $nonAsn = Pegawai::create([
            'unit_kerja_id' => $unit->id,
            'status_kepegawaian' => 'NON_ASN',
            'nip' => null,
            'nama' => 'Teguh Rahayu',
            'jabatan' => 'Pelaksana',
            'is_active' => true,
        ]);

        return [$unit, $operator, $rekeningSppd, $rekeningAtk, $nonAsn];
    }

    public function test_converts_sppd_assignment_to_kuitansi_and_locks_mutation_after_submit(): void
    {
        [$unit, $operator, $rekeningSppd, , $nonAsn] = $this->seedEnvironment();

        $res = $this->actingAs($operator)
            ->postJson('/api/v1/sppd-assignments', [
                'nomor_surat_tugas' => '010/Tarkid/SPPD/X/2026',
                'maksud_perjalanan' => 'Koordinasi Sukakarya',
                'tempat_berangkat' => 'Kecamatan',
                'tempat_tujuan' => 'Kelurahan Sukakarya',
                'tanggal_berangkat' => '2026-10-19',
                'tanggal_kembali' => '2026-10-19',
                'peserta' => [
                    [
                        'pegawai_id' => $nonAsn->id,
                        'nomor_spd' => '010/SPD/X/2026',
                        'tingkat_biaya' => '70000',
                        'biaya_items' => [
                            ['uraian_biaya' => 'Uang Transpor Lokal', 'nominal' => 70000],
                        ],
                    ],
                ],
            ])
            ->assertCreated();

        $assignmentId = $res->json('data.id');

        $convertRes = $this->actingAs($operator)
            ->postJson("/api/v1/sppd-assignments/{$assignmentId}/convert-kuitansi", [
                'anggaran_rekening_id' => $rekeningSppd->id,
                'bulan_anggaran' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'SELESAI')
            ->assertJsonPath('data.kuitansi.nominal_bruto', '70000.00')
            ->assertJsonPath('data.kuitansi.golongan_asn', 'NON_ASN');

        $kuitansiId = $convertRes->json('data.kuitansi_id');
        $this->assertDatabaseHas('kuitansi_sppd_peserta', [
            'kuitansi_id' => $kuitansiId,
            'pegawai_id' => $nonAsn->id,
        ]);

        // Terlihat di SppdReportService
        $report = app(SppdReportService::class)->getPaginatedReport($operator);
        $this->assertSame(1, $report->total());

        // Kunci mutasi setelah Kuitansi diajukan (MENUNGGU_PPTK)
        Kuitansi::whereKey($kuitansiId)->update(['status' => 'MENUNGGU_PPTK']);

        $this->actingAs($operator)
            ->putJson("/api/v1/sppd-assignments/{$assignmentId}", [
                'maksud_perjalanan' => 'Ubah setelah submit',
                'tempat_berangkat' => 'Kecamatan',
                'tempat_tujuan' => 'Kelurahan Sukakarya',
                'tanggal_berangkat' => '2026-10-19',
                'tanggal_kembali' => '2026-10-19',
                'peserta' => [
                    ['pegawai_id' => $nonAsn->id],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SPPD_UPDATE_REJECTED')
            ->assertJsonStructure(['error' => ['details' => ['kuitansi_id']]]);
    }

    public function test_link_existing_kuitansi_enforces_guards(): void
    {
        [$unit, $operator, $rekeningSppd, $rekeningAtk, $nonAsn] = $this->seedEnvironment();

        $assignment = SppdAssignment::create([
            'unit_kerja_id' => $unit->id,
            'user_id' => $operator->id,
            'maksud_perjalanan' => 'Monitoring',
            'tempat_berangkat' => 'Kecamatan',
            'tempat_tujuan' => 'Kelurahan Jayawaras',
            'tanggal_berangkat' => '2026-10-20',
            'tanggal_kembali' => '2026-10-20',
            'pemberi_perintah_nama' => 'Camat',
            'pemberi_perintah_jabatan' => 'Camat',
            'status' => 'TERBIT',
        ]);
        $assignment->peserta()->create(['pegawai_id' => $nonAsn->id, 'urutan' => 1]);

        $nonSppdKuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-ATK-001',
            'unit_kerja_id' => $unit->id,
            'anggaran_rekening_id' => $rekeningAtk->id,
            'user_id' => $operator->id,
            'bulan_anggaran' => 10,
            'tanggal_transaksi' => '2026-10-20',
            'uraian' => 'ATK',
            'nama_penerima' => 'Toko',
            'nominal_bruto' => 70000,
            'total_pajak' => 0,
            'nominal_neto' => 70000,
            'status' => 'DRAFT',
        ]);

        $this->actingAs($operator)
            ->postJson("/api/v1/sppd-assignments/{$assignment->id}/link-kuitansi", [
                'kuitansi_id' => $nonSppdKuitansi->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SPPD_LINK_REJECTED')
            ->assertJsonStructure(['error' => ['details' => ['kuitansi_id']]]);

        $validKuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-SPPD-002',
            'unit_kerja_id' => $unit->id,
            'anggaran_rekening_id' => $rekeningSppd->id,
            'user_id' => $operator->id,
            'bulan_anggaran' => 10,
            'tanggal_transaksi' => '2026-10-20',
            'uraian' => 'Monitoring',
            'nama_penerima' => 'Teguh Rahayu',
            'nominal_bruto' => 70000,
            'total_pajak' => 0,
            'nominal_neto' => 70000,
            'status' => 'DRAFT',
        ]);

        $this->actingAs($operator)
            ->postJson("/api/v1/sppd-assignments/{$assignment->id}/link-kuitansi", [
                'kuitansi_id' => $validKuitansi->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.kuitansi_id', $validKuitansi->id)
            ->assertJsonPath('data.status', 'SELESAI');
    }
}
