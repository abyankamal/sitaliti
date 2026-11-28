<?php

namespace Tests\Feature;

use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiSppdPeserta;
use App\Models\Pegawai;
use App\Models\SppdAssignment;
use App\Models\SppdPeserta;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\SppdScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SppdConflictPreDepartureTest extends TestCase
{
    use RefreshDatabase;

    private function seedContext(): array
    {
        $unit = UnitKerja::create([
            'kode_unit' => '7.01.0.00.0.00.41.0000',
            'nama_unit' => 'Kecamatan Tarogong Kidul',
            'tipe' => 'KECAMATAN',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'BP',
            'unit_kerja_id' => $unit->id,
        ]);

        $pegawai = Pegawai::create([
            'unit_kerja_id' => $unit->id,
            'status_kepegawaian' => 'NON_ASN',
            'nip' => null,
            'nama' => 'Teguh Rahayu',
            'jabatan' => 'Pelaksana',
            'is_active' => true,
        ]);

        return [$unit, $user, $pegawai];
    }

    public function test_detects_conflict_between_pre_departure_sppd_assignments(): void
    {
        [$unit, $user, $pegawai] = $this->seedContext();

        $existing = SppdAssignment::create([
            'unit_kerja_id' => $unit->id,
            'user_id' => $user->id,
            'nomor_surat_tugas' => '001/Tarkid/SPPD/X/2026',
            'maksud_perjalanan' => 'Koordinasi Kelurahan',
            'tempat_berangkat' => 'Kecamatan',
            'tempat_tujuan' => 'Kelurahan Sukakarya',
            'tanggal_berangkat' => '2026-10-19',
            'tanggal_kembali' => '2026-10-20',
            'pemberi_perintah_nama' => 'AHMAD MAWARDI,AP,.SE',
            'pemberi_perintah_jabatan' => 'Camat Tarogong Kidul',
            'status' => 'TERBIT',
        ]);

        SppdPeserta::create([
            'sppd_assignment_id' => $existing->id,
            'pegawai_id' => $pegawai->id,
            'urutan' => 1,
        ]);

        $service = app(SppdScheduleService::class);

        $conflicts = $service->findConflicts([[
            'pegawai_id' => $pegawai->id,
            'tanggal_berangkat' => '2026-10-20',
            'tanggal_pulang' => '2026-10-21',
        ]]);

        $this->assertCount(1, $conflicts);
        $this->assertSame('SPPD_ASSIGNMENT', $conflicts[0]['source']);

        // Edit draft sendiri tidak memicu false-conflict
        $noSelfConflict = $service->findConflicts([[
            'pegawai_id' => $pegawai->id,
            'tanggal_berangkat' => '2026-10-19',
            'tanggal_pulang' => '2026-10-20',
        ]], null, $existing->id);

        $this->assertCount(0, $noSelfConflict);

        $this->expectException(ValidationException::class);
        $service->assertNoConflictsWithLock([[
            'pegawai_id' => $pegawai->id,
            'tanggal_berangkat' => '2026-10-19',
            'tanggal_pulang' => '2026-10-19',
        ]]);
    }

    public function test_detects_conflict_between_pre_departure_and_kuitansi_sppd(): void
    {
        [$unit, $user, $pegawai] = $this->seedContext();

        $rekening = AnggaranRekening::create([
            'unit_kerja_id' => $unit->id,
            'tahun_anggaran' => 2026,
            'kode_sub_kegiatan' => '7.01.01.2.01.01',
            'nama_sub_kegiatan' => 'Koordinasi Pemerintahan',
            'kode_rekening' => '5.1.02.04.01.0003',
            'nama_rekening' => 'Belanja Perjalanan Dinas Dalam Kota',
            'kategori_belanja' => 'B.4',
            'pagu_dpa' => 10000000,
        ]);

        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KWT-SPPD-001',
            'unit_kerja_id' => $unit->id,
            'anggaran_rekening_id' => $rekening->id,
            'user_id' => $user->id,
            'bulan_anggaran' => 10,
            'tanggal_transaksi' => '2026-10-19',
            'uraian' => 'Perjadin',
            'nama_penerima' => 'Teguh Rahayu',
            'nominal_bruto' => 70000,
            'total_pajak' => 0,
            'nominal_neto' => 70000,
            'status' => 'DRAFT',
        ]);

        KuitansiSppdPeserta::create([
            'kuitansi_id' => $kuitansi->id,
            'pegawai_id' => $pegawai->id,
            'tanggal_berangkat' => '2026-10-19',
            'tanggal_pulang' => '2026-10-19',
            'tujuan' => 'Kelurahan Sukakarya',
        ]);

        $service = app(SppdScheduleService::class);

        $conflicts = $service->findConflicts([[
            'pegawai_id' => $pegawai->id,
            'tanggal_berangkat' => '2026-10-19',
            'tanggal_pulang' => '2026-10-19',
        ]]);

        $this->assertCount(1, $conflicts);
        $this->assertSame('KUITANSI', $conflicts[0]['source']);
    }
}
