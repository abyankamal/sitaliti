<?php

namespace Tests\Unit;

use App\Models\Pegawai;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PegawaiNonAsnNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_nip_converts_blank_and_dash_to_null(): void
    {
        $this->assertNull(Pegawai::normalizeNip(null));
        $this->assertNull(Pegawai::normalizeNip(''));
        $this->assertNull(Pegawai::normalizeNip('   '));
        $this->assertNull(Pegawai::normalizeNip('-'));
        $this->assertSame('197005101990091001', Pegawai::normalizeNip('1970 0510-199009-1 001'));
    }

    public function test_multiple_non_asn_pegawai_with_null_nip_can_be_saved(): void
    {
        $first = Pegawai::create([
            'status_kepegawaian' => 'NON_ASN',
            'nip' => '',
            'nama' => 'Teguh Rahayu',
            'jabatan' => 'Pelaksana',
            'is_active' => true,
        ]);

        $second = Pegawai::create([
            'status_kepegawaian' => 'NON_ASN',
            'nip' => '-',
            'nama' => 'Budi Santoso',
            'jabatan' => 'Tenaga Teknis',
            'is_active' => true,
        ]);

        $this->assertNull($first->nip);
        $this->assertNull($second->nip);
        $this->assertDatabaseCount('pegawai', 2);
    }
}
