<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use Database\Seeders\PegawaiSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PegawaiSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_pegawai_seeder_seeds_all_80_asn_and_pppk_idempotently(): void
    {
        $this->seed(UnitKerjaSeeder::class);
        $this->seed(PegawaiSeeder::class);

        $this->assertSame(80, Pegawai::query()->count());
        $this->assertSame(48, Pegawai::query()->where('status_kepegawaian', 'ASN')->count());
        $this->assertSame(32, Pegawai::query()->where('status_kepegawaian', 'PPPK')->count());
        $this->assertSame(80, Pegawai::query()->where('is_active', true)->count());

        // Pastikan seluruh 6 unit kerja memiliki pegawai
        $unitCountWithPegawai = Pegawai::query()
            ->distinct('unit_kerja_id')
            ->count('unit_kerja_id');
        $this->assertSame(6, $unitCountWithPegawai);

        // Pastikan normalisasi nama & NIP berjalan bersih
        $this->assertDatabaseHas('pegawai', [
            'nama' => 'SUHERMAN',
            'status_kepegawaian' => 'ASN',
        ]);

        // Pastikan idempoten saat dijalankan ulang
        $this->seed(PegawaiSeeder::class);
        $this->assertSame(80, Pegawai::query()->count());
    }
}
