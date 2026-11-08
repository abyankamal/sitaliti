<?php

namespace Tests\Feature\Console;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiRiwayat;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CheckSlaCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createKuitansiWithDeadline(string $status, ?Carbon $deadline): Kuitansi
    {
        $unit = UnitKerja::firstOrCreate(
            ['kode_unit' => 'KEC-01'],
            ['nama_unit' => 'Kecamatan Tarogong Kidul', 'singkatan' => 'KEC', 'is_active' => true]
        );

        $user = User::factory()->create([
            'unit_kerja_id' => $unit->id,
            'role' => 'STAFF_SEKSI',
        ]);

        $rekening = AnggaranRekening::firstOrCreate(
            ['kode_rekening' => '5.1.02.01.01.0024'],
            [
                'unit_kerja_id' => $unit->id,
                'kode_sub_kegiatan' => '7.01.01.2.01.0001',
                'nama_sub_kegiatan' => 'Penyusunan Rencana Program',
                'tahun_anggaran' => 2026,
                'nama_rekening' => 'Belanja Alat Tulis Kantor',
                'pagu_murni' => 50000000,
                'pagu_geser' => 50000000,
                'pagu_akhir' => 50000000,
                'is_leaf' => true,
            ]
        );

        AnggaranKasBulanan::firstOrCreate(
            ['anggaran_rekening_id' => $rekening->id, 'bulan' => 3],
            ['alokasi_pagu' => 10000000, 'pagu_terkunci' => 0, 'realisasi' => 0]
        );

        return Kuitansi::create([
            'nomor_kuitansi' => 'KUI/TEST/SLA/'.uniqid(),
            'unit_kerja_id' => $unit->id,
            'anggaran_rekening_id' => $rekening->id,
            'user_id' => $user->id,
            'bulan_anggaran' => 3,
            'tanggal_transaksi' => '2026-03-01',
            'uraian' => 'Pengadaan ATK Test SLA',
            'nama_penerima' => 'Toko Alat Tulis',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 1000000,
            'total_pajak' => 0,
            'nominal_neto' => 1000000,
            'status' => $status,
            'tenggat_sla' => $deadline,
        ]);
    }

    public function test_scans_and_logs_audit_trail_for_breached_kuitansi(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        $breached = $this->createKuitansiWithDeadline('REVISI_MINOR', now()->subHours(5));
        $valid = $this->createKuitansiWithDeadline('REVISI_MINOR', now()->addHours(10));
        $differentStatus = $this->createKuitansiWithDeadline('MENUNGGU_VERIFIKASI', now()->subHours(5));

        $this->artisan('sitaliti:check-sla')
            ->expectsOutputToContain('Memulai pemindaian')
            ->expectsOutputToContain($breached->nomor_kuitansi)
            ->doesntExpectOutputToContain($valid->nomor_kuitansi)
            ->doesntExpectOutputToContain($differentStatus->nomor_kuitansi)
            ->assertExitCode(0);

        // Pastikan hanya 1 audit trail SLA_BREACHED dicatat
        $this->assertDatabaseHas('kuitansi_riwayat', [
            'kuitansi_id' => $breached->id,
            'action' => 'SLA_BREACHED',
            'status_lama' => 'REVISI_MINOR',
            'status_baru' => 'REVISI_MINOR',
        ]);

        $this->assertDatabaseMissing('kuitansi_riwayat', [
            'kuitansi_id' => $valid->id,
            'action' => 'SLA_BREACHED',
        ]);
    }

    public function test_does_not_duplicate_sla_breached_audit_log(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        $breached = $this->createKuitansiWithDeadline('REVISI_MINOR', now()->subHours(5));

        // Jalankan pertama kali -> mencatat 1 baris
        $this->artisan('sitaliti:check-sla')
            ->expectsOutputToContain('Baru Dicatat')
            ->assertExitCode(0);

        $this->assertEquals(1, KuitansiRiwayat::where('kuitansi_id', $breached->id)->where('action', 'SLA_BREACHED')->count());

        // Jalankan kedua kali -> mendeteksi "Tercatat Sebelumnya" tanpa menambah baris audit
        $this->artisan('sitaliti:check-sla')
            ->expectsOutputToContain('Tercatat Sebelumnya')
            ->assertExitCode(0);

        $this->assertEquals(1, KuitansiRiwayat::where('kuitansi_id', $breached->id)->where('action', 'SLA_BREACHED')->count());
    }
}
