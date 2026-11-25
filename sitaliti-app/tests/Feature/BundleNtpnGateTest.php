<?php

namespace Tests\Feature;

use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiPajak;
use App\Models\PengajuanSpj;
use App\Models\PengajuanSpjItem;
use App\Models\PengaturanKepatuhan;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BundleNtpnGateTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $kecamatan;

    private User $verifikator;

    private AnggaranRekening $rekening;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kecamatan = UnitKerja::create([
            'kode_unit' => 'KEC-TK',
            'nama_unit' => 'Kecamatan Tarogong Kidul',
            'tipe' => 'KECAMATAN',
        ]);

        foreach (User::defaultRolePermissions() as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            foreach ($perms as $permName) {
                Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
            }
            $role->syncPermissions($perms);
        }

        $this->verifikator = User::factory()->create([
            'role' => 'VERIFIKATOR',
            'unit_kerja_id' => $this->kecamatan->id,
        ]);

        $this->rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->kecamatan->id,
            'tahun_anggaran' => 2026,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Operasional Kecamatan',
            'kode_rekening' => '5.1.02.01.01.0024',
            'nama_rekening' => 'Belanja Alat/Bahan ATK',
            'kategori_belanja' => 'B.1',
            'pagu_total' => 50000000,
        ]);
    }

    private function createBundledReceiptWithTaxes(bool $withNtpn, bool $includeMeteraiOnly = false): PengajuanSpj
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/NTPN/'.uniqid(),
            'unit_kerja_id' => $this->kecamatan->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->verifikator->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-15',
            'uraian' => 'Belanja ATK',
            'nama_penerima' => 'Toko ATK',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 6000000,
            'total_pajak' => $includeMeteraiOnly ? 0 : 675676,
            'nominal_neto' => $includeMeteraiOnly ? 6000000 : 5324324,
            'status' => 'DIBUNDEL',
        ]);

        if ($includeMeteraiOnly) {
            KuitansiPajak::create([
                'kuitansi_id' => $kuitansi->id,
                'kode_pajak' => 'BEA_METERAI',
                'nama_pajak' => 'Bea Meterai Lunas',
                'tarif' => 0,
                'tipe_tarif' => 'FIXED',
                'dpp' => 0,
                'nominal' => 10000,
            ]);
        } else {
            KuitansiPajak::create([
                'kuitansi_id' => $kuitansi->id,
                'kode_pajak' => 'PPN_11',
                'nama_pajak' => 'PPN 11%',
                'tarif' => 0.11,
                'tipe_tarif' => 'PERSENTASE_DPP',
                'dpp' => 5405405,
                'nominal' => 594595,
                'ntpn' => $withNtpn ? 'NTPN-PPN-001' : null,
            ]);
            KuitansiPajak::create([
                'kuitansi_id' => $kuitansi->id,
                'kode_pajak' => 'PPH_22',
                'nama_pajak' => 'PPh 22',
                'tarif' => 0.015,
                'tipe_tarif' => 'PERSENTASE_DPP',
                'dpp' => 5405405,
                'nominal' => 81081,
                'ntpn' => $withNtpn ? 'NTPN-PPH22-001' : null,
            ]);
        }

        $bundle = PengajuanSpj::create([
            'nomor_bundel' => 'BND-SPP_GU/2026/01/'.rand(100, 999),
            'unit_kerja_id' => $this->kecamatan->id,
            'user_id' => $this->verifikator->id,
            'jenis_bundel' => 'SPP_GU',
            'tahun_anggaran' => 2026,
            'bulan_periode' => 1,
            'total_bruto' => 6000000,
            'total_pajak' => $includeMeteraiOnly ? 0 : 675676,
            'total_neto' => $includeMeteraiOnly ? 6000000 : 5324324,
            'status_bundel' => 'MENUNGGU_PPK',
        ]);

        PengajuanSpjItem::create([
            'pengajuan_spj_id' => $bundle->id,
            'kuitansi_id' => $kuitansi->id,
        ]);

        return $bundle;
    }

    public function test_ppk_approval_blocked_when_ntpn_gate_enabled_and_ntpn_missing(): void
    {
        PengaturanKepatuhan::setValue('ntpn_gate_spp_gu', true);
        $bundle = $this->createBundledReceiptWithTaxes(withNtpn: false);

        $response = $this->actingAs($this->verifikator)
            ->postJson("/api/v1/bundles/{$bundle->id}/approve-ppk", []);

        $response->assertStatus(422);
        $this->assertEquals('MENUNGGU_PPK', $bundle->fresh()->status_bundel);
    }

    public function test_ppk_approval_succeeds_when_ntpn_filled_or_only_bea_meterai(): void
    {
        PengaturanKepatuhan::setValue('ntpn_gate_spp_gu', true);

        $bundleWithNtpn = $this->createBundledReceiptWithTaxes(withNtpn: true);
        $this->actingAs($this->verifikator)
            ->postJson("/api/v1/bundles/{$bundleWithNtpn->id}/approve-ppk", [])
            ->assertOk();
        $this->assertEquals('DISETUJUI_PPK', $bundleWithNtpn->fresh()->status_bundel);

        $bundleMeteraiOnly = $this->createBundledReceiptWithTaxes(withNtpn: false, includeMeteraiOnly: true);
        $this->actingAs($this->verifikator)
            ->postJson("/api/v1/bundles/{$bundleMeteraiOnly->id}/approve-ppk", [])
            ->assertOk();
        $this->assertEquals('DISETUJUI_PPK', $bundleMeteraiOnly->fresh()->status_bundel);
    }

    public function test_ppk_approval_succeeds_without_ntpn_when_gate_disabled(): void
    {
        PengaturanKepatuhan::setValue('ntpn_gate_spp_gu', false);
        $bundle = $this->createBundledReceiptWithTaxes(withNtpn: false);

        $this->actingAs($this->verifikator)
            ->postJson("/api/v1/bundles/{$bundle->id}/approve-ppk", [])
            ->assertOk();
        $this->assertEquals('DISETUJUI_PPK', $bundle->fresh()->status_bundel);
    }
}
