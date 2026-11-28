<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SppdAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_crud_sppd_assignment_with_mixed_asn_and_non_asn(): void
    {
        $kecamatan = UnitKerja::create([
            'kode_unit' => '7.01.0.00.0.00.41.0000',
            'nama_unit' => 'Kecamatan Tarogong Kidul',
            'tipe' => 'KECAMATAN',
            'pejabat_nama' => 'AHMAD MAWARDI,AP,.SE',
            'pejabat_nip' => '197005101990091001',
            'pejabat_jabatan' => 'Camat Tarogong Kidul',
            'is_active' => true,
        ]);

        $operator = User::factory()->create([
            'role' => 'BP',
            'unit_kerja_id' => $kecamatan->id,
        ]);

        $asn = Pegawai::create([
            'unit_kerja_id' => $kecamatan->id,
            'status_kepegawaian' => 'ASN',
            'nip' => '198211032010012002',
            'nama' => 'Siti Supiah,S.Ip',
            'jabatan' => 'Kasubbag Umum & Evlap',
            'golongan' => 'Penata III/c',
            'is_active' => true,
        ]);

        $nonAsn = Pegawai::create([
            'unit_kerja_id' => $kecamatan->id,
            'status_kepegawaian' => 'NON_ASN',
            'nip' => null,
            'nama' => 'Teguh Rahayu',
            'jabatan' => 'Pelaksana',
            'is_active' => true,
        ]);

        $createRes = $this->actingAs($operator)
            ->postJson('/api/v1/sppd-assignments', [
                'nomor_surat_tugas' => '001/Tarkid/SPPD/X/2026',
                'maksud_perjalanan' => 'Koordinasi Kelurahan',
                'tempat_berangkat' => 'Kecamatan',
                'tempat_tujuan' => 'Kelurahan Sukakarya',
                'tanggal_berangkat' => '2026-10-19',
                'tanggal_kembali' => '2026-10-19',
                'status' => 'TERBIT',
                'peserta' => [
                    [
                        'pegawai_id' => $asn->id,
                        'nomor_spd' => '001/SPD/X/2026',
                        'tingkat_biaya' => '370000',
                        'biaya_items' => [
                            ['uraian_biaya' => 'Uang Transpor Lokal', 'nominal' => 370000],
                        ],
                    ],
                    [
                        'pegawai_id' => $nonAsn->id,
                        'nomor_spd' => '002/SPD/X/2026',
                        'tingkat_biaya' => '70000',
                        'biaya_items' => [
                            ['uraian_biaya' => 'Uang Transpor Lokal', 'nominal' => 70000],
                        ],
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.pemberi_perintah_nama', 'AHMAD MAWARDI,AP,.SE')
            ->assertJsonCount(2, 'data.peserta');

        $id = $createRes->json('data.id');

        $this->actingAs($operator)
            ->putJson("/api/v1/sppd-assignments/{$id}", [
                'maksud_perjalanan' => 'Koordinasi Kelurahan (Revisi)',
                'tempat_berangkat' => 'Kecamatan',
                'tempat_tujuan' => 'Kelurahan Sukakarya',
                'tanggal_berangkat' => '2026-10-19',
                'tanggal_kembali' => '2026-10-19',
                'peserta' => [
                    ['pegawai_id' => $nonAsn->id, 'nomor_spd' => '002/SPD/X/2026'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.maksud_perjalanan', 'Koordinasi Kelurahan (Revisi)')
            ->assertJsonCount(1, 'data.peserta');

        $this->actingAs($operator)
            ->deleteJson("/api/v1/sppd-assignments/{$id}")
            ->assertOk();
    }

    public function test_enforces_unit_kerja_isolation_and_read_only_role_restrictions(): void
    {
        $kelA = UnitKerja::create([
            'kode_unit' => '7.01.0.00.0.00.41.0001',
            'nama_unit' => 'Kelurahan Pataruman',
            'tipe' => 'KELURAHAN',
            'pejabat_nama' => 'Lurah A',
            'pejabat_jabatan' => 'Lurah Pataruman',
            'is_active' => true,
        ]);

        $kelB = UnitKerja::create([
            'kode_unit' => '7.01.0.00.0.00.41.0006',
            'nama_unit' => 'Kelurahan Jayawaras',
            'tipe' => 'KELURAHAN',
            'pejabat_nama' => 'Lurah B',
            'pejabat_jabatan' => 'Lurah Jayawaras',
            'is_active' => true,
        ]);

        $bppA = User::factory()->create(['role' => 'BPP', 'unit_kerja_id' => $kelA->id]);
        $bppB = User::factory()->create(['role' => 'BPP', 'unit_kerja_id' => $kelB->id]);
        $lurahA = User::factory()->create(['role' => 'LURAH', 'unit_kerja_id' => $kelA->id]);

        $pegawaiA = Pegawai::create([
            'unit_kerja_id' => $kelA->id,
            'status_kepegawaian' => 'ASN',
            'nip' => '196612111986032003',
            'nama' => 'Lina Maryati',
            'is_active' => true,
        ]);

        $res = $this->actingAs($bppA)
            ->postJson('/api/v1/sppd-assignments', [
                'maksud_perjalanan' => 'Rapat PBB',
                'tempat_berangkat' => 'Jayawaras',
                'tempat_tujuan' => 'Kecamatan',
                'tanggal_berangkat' => '2026-10-22',
                'tanggal_kembali' => '2026-10-22',
                'peserta' => [['pegawai_id' => $pegawaiA->id]],
            ])
            ->assertCreated();

        $id = $res->json('data.id');

        $this->actingAs($bppB)->getJson("/api/v1/sppd-assignments/{$id}")->assertForbidden();
        $this->actingAs($bppB)->deleteJson("/api/v1/sppd-assignments/{$id}")->assertForbidden();

        $this->actingAs($lurahA)->postJson('/api/v1/sppd-assignments', [
            'maksud_perjalanan' => 'Test',
            'tempat_berangkat' => 'A',
            'tempat_tujuan' => 'B',
            'tanggal_berangkat' => '2026-10-23',
            'tanggal_kembali' => '2026-10-23',
            'peserta' => [['pegawai_id' => $pegawaiA->id]],
        ])->assertForbidden();
    }
}
