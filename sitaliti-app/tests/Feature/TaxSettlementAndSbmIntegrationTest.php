<?php

namespace Tests\Feature;

use App\Models\AnggaranAuditLog;
use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiItem;
use App\Models\KuitansiPajak;
use App\Models\MasterRekapBelanjaOperasi;
use App\Models\MasterSbmItem;
use App\Models\PengaturanKepatuhan;
use App\Models\RekapBelanjaOperasiRekening;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TaxSettlementAndSbmIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $kecamatan;

    private UnitKerja $kelurahan;

    private User $bp;

    private User $verifikator;

    private User $otherBpp;

    private AnggaranRekening $rekening;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->kecamatan = UnitKerja::create([
            'kode_unit' => 'KEC-TK',
            'nama_unit' => 'Kecamatan Tarogong Kidul',
            'tipe' => 'KECAMATAN',
        ]);

        $this->kelurahan = UnitKerja::create([
            'kode_unit' => 'KEL-SUK',
            'nama_unit' => 'Kelurahan Sukakarya',
            'tipe' => 'KELURAHAN',
        ]);

        foreach (User::defaultRolePermissions() as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            foreach ($perms as $permName) {
                Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
            }
            $role->syncPermissions($perms);
        }

        $this->bp = User::factory()->create([
            'role' => 'BP',
            'unit_kerja_id' => $this->kecamatan->id,
        ]);

        $this->verifikator = User::factory()->create([
            'role' => 'VERIFIKATOR',
            'unit_kerja_id' => $this->kecamatan->id,
        ]);

        $this->otherBpp = User::factory()->create([
            'role' => 'BPP',
            'unit_kerja_id' => $this->kelurahan->id,
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
            'pagu_perubahan' => 50000000,
            'is_leaf' => true,
        ]);

        AnggaranKasBulanan::create([
            'anggaran_rekening_id' => $this->rekening->id,
            'bulan' => 1,
            'alokasi_pagu' => 20000000,
        ]);
    }

    public function test_sbm_soft_warning_triggers_on_validate_draft_and_verification_workspace_using_nama_barang(): void
    {
        PengaturanKepatuhan::setValue('sbm_soft_warning_enabled', true);

        MasterSbmItem::create([
            'tahun_anggaran' => 2026,
            'kategori_belanja' => 'B1_ATK',
            'kode_sbm' => 'SBM-ATK-001',
            'uraian_standar' => 'Kertas HVS F4 70gr',
            'satuan' => 'Rim',
            'harga_maksimal' => 65000,
            'is_active' => true,
        ]);

        $draftRes = $this->actingAs($this->bp)->postJson('/api/v1/receipts/validate-draft', [
            'anggaran_rekening_id' => $this->rekening->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-15',
            'kategori_belanja' => 'B.1',
            'items' => [
                ['nama_barang' => 'Kertas HVS F4 70gr', 'volume' => 2, 'harga_satuan' => 80000],
            ],
        ]);

        $draftRes->assertOk();
        $this->assertCount(1, $draftRes->json('data.sbm_warnings'));
        $this->assertSame('SBM-ATK-001', $draftRes->json('data.sbm_warnings.0.kode_sbm'));

        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/SBM/'.uniqid(),
            'unit_kerja_id' => $this->kecamatan->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->bp->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-15',
            'uraian' => 'Belanja Kertas',
            'nama_penerima' => 'Toko Kertas',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 160000,
            'total_pajak' => 0,
            'nominal_neto' => 160000,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        KuitansiItem::create([
            'kuitansi_id' => $kuitansi->id,
            'nama_barang' => 'Kertas HVS F4 70gr',
            'volume' => 2,
            'satuan' => 'Rim',
            'harga_satuan' => 80000,
            'total_harga' => 160000,
        ]);

        $wsRes = $this->actingAs($this->verifikator)->getJson("/api/v1/verification/{$kuitansi->id}");
        $wsRes->assertOk();
        $this->assertCount(1, $wsRes->json('data.sbm_warnings'));
        $this->assertSame('SBM-ATK-001', $wsRes->json('data.sbm_warnings.0.kode_sbm'));
    }

    public function test_tax_settlement_atomic_multi_row_rollback_unit_isolation_and_sp2d_lock(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/TAX/'.uniqid(),
            'unit_kerja_id' => $this->kecamatan->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->bp->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-15',
            'uraian' => 'Belanja ATK',
            'nama_penerima' => 'Toko ATK',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 3330000,
            'total_pajak' => 375000,
            'nominal_neto' => 2955000,
            'status' => 'LOLOS_VERIFIKASI',
        ]);

        $ppn = KuitansiPajak::create([
            'kuitansi_id' => $kuitansi->id,
            'kode_pajak' => 'PPN_11',
            'nama_pajak' => 'PPN 11%',
            'tarif' => 0.11,
            'tipe_tarif' => 'PERSENTASE_DPP',
            'dpp' => 3000000,
            'nominal' => 330000,
        ]);

        $pph22 = KuitansiPajak::create([
            'kuitansi_id' => $kuitansi->id,
            'kode_pajak' => 'PPH_22',
            'nama_pajak' => 'PPh 22',
            'tarif' => 0.015,
            'tipe_tarif' => 'PERSENTASE_DPP',
            'dpp' => 3000000,
            'nominal' => 45000,
        ]);

        // Isolasi unit kerja: BPP kelurahan lain ditolak 403
        $this->actingAs($this->otherBpp)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/tax-settlement", [
                'kuitansi_pajak_id' => $ppn->id,
                'ntpn' => 'NTPN123456',
            ])
            ->assertStatus(403);

        // Multi-row gagal pada baris ke-2 -> baris ke-1 tidak berubah dan tidak ada file yatim
        $this->actingAs($this->bp)
            ->post("/api/v1/receipts/{$kuitansi->id}/tax-settlement", [
                'settlements' => [
                    [
                        'kuitansi_pajak_id' => $ppn->id,
                        'ntpn' => 'NTPN-SHOULD-NOT-SAVE',
                        'bukti_setor' => UploadedFile::fake()->create('bukti1.pdf', 10, 'application/pdf'),
                    ],
                    [
                        'kuitansi_pajak_id' => 999999,
                        'ntpn' => 'NTPN-INVALID',
                    ],
                ],
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertNull($ppn->fresh()->ntpn);
        $this->assertSame([], Storage::disk('local')->allFiles("tax-settlements/{$kuitansi->id}"));

        // Multi-row sukses & preview file bukti setor dapat diakses
        $okRes = $this->actingAs($this->bp)
            ->post("/api/v1/receipts/{$kuitansi->id}/tax-settlement", [
                'settlements' => [
                    [
                        'kuitansi_pajak_id' => $ppn->id,
                        'kode_billing' => 'BILL-001',
                        'ntpn' => 'NTPN-001',
                        'bukti_setor' => UploadedFile::fake()->create('bukti-ppn.pdf', 10, 'application/pdf'),
                    ],
                    [
                        'kuitansi_pajak_id' => $pph22->id,
                        'kode_billing' => 'BILL-002',
                        'ntpn' => 'NTPN-002',
                    ],
                ],
            ], ['Accept' => 'application/json']);

        $okRes->assertOk();
        $this->assertSame('NTPN-001', $ppn->fresh()->ntpn);
        $this->assertSame('NTPN-002', $pph22->fresh()->ntpn);

        $this->actingAs($this->bp)
            ->get("/api/v1/receipts/{$kuitansi->id}/tax-settlement/{$ppn->id}/preview")
            ->assertOk();

        // Lock SELESAI_SP2D -> 422
        $kuitansi->update(['status' => 'SELESAI_SP2D']);
        $this->actingAs($this->bp)
            ->postJson("/api/v1/receipts/{$kuitansi->id}/tax-settlement", [
                'kuitansi_pajak_id' => $ppn->id,
                'ntpn' => 'NTPN-AFTER-SP2D',
            ])
            ->assertStatus(422);
    }

    public function test_edit_receipt_preserves_ntpn_when_tax_nominal_unchanged_and_resets_when_changed(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KWT/EDIT/'.uniqid(),
            'unit_kerja_id' => $this->kecamatan->id,
            'anggaran_rekening_id' => $this->rekening->id,
            'user_id' => $this->bp->id,
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-15',
            'uraian' => 'Belanja ATK',
            'nama_penerima' => 'Toko ATK',
            'metode_pembayaran' => 'TRANSFER',
            'nominal_bruto' => 3330000,
            'total_pajak' => 375000,
            'nominal_neto' => 2955000,
            'status' => 'DRAFT',
        ]);

        $buktiPath = UploadedFile::fake()->create('setor.pdf', 10, 'application/pdf')->store("tax-settlements/{$kuitansi->id}", 'local');

        KuitansiPajak::create([
            'kuitansi_id' => $kuitansi->id,
            'kode_pajak' => 'PPN_11',
            'nama_pajak' => 'PPN 11%',
            'tarif' => 0.11,
            'tipe_tarif' => 'PERSENTASE_DPP',
            'dpp' => 3000000,
            'nominal' => 330000,
            'kode_billing' => 'BILL-KEEP',
            'ntpn' => 'NTPN-KEEP',
            'tanggal_setor' => '2026-01-20',
            'bukti_setor_path' => $buktiPath,
        ]);

        // Edit dengan nominal sama (3.330.000) -> NTPN & bukti_setor_path dipertahankan
        $this->actingAs($this->bp)->putJson("/api/v1/receipts/{$kuitansi->id}", [
            'uraian' => 'Belanja ATK Revisi Deskripsi',
            'items' => [
                ['nama_barang' => 'Kertas A4', 'volume' => 1, 'satuan' => 'Paket', 'harga_satuan' => 3330000],
            ],
        ])->assertOk();

        $preserved = $kuitansi->pajak()->where('kode_pajak', 'PPN_11')->first();
        $this->assertSame('NTPN-KEEP', $preserved->ntpn);
        $this->assertSame($buktiPath, $preserved->bukti_setor_path);
        Storage::disk('local')->assertExists($buktiPath);

        // Edit dengan nominal berbeda (4.440.000) -> NTPN di-reset & file lama dihapus
        $this->actingAs($this->bp)->putJson("/api/v1/receipts/{$kuitansi->id}", [
            'uraian' => 'Belanja ATK Revisi Nominal',
            'items' => [
                ['nama_barang' => 'Kertas A4', 'volume' => 1, 'satuan' => 'Paket', 'harga_satuan' => 4440000],
            ],
        ])->assertOk();

        $reset = $kuitansi->pajak()->where('kode_pajak', 'PPN_11')->first();
        $this->assertNull($reset->ntpn);
        $this->assertNull($reset->bukti_setor_path);
        Storage::disk('local')->assertMissing($buktiPath);
    }

    public function test_rekap_belanja_operasi_drilldown_with_search_returns_200(): void
    {
        $pos = MasterRekapBelanjaOperasi::create([
            'kode' => 'POS-ATK',
            'nama' => 'Alat Tulis Kantor',
            'urutan' => 1,
            'is_active' => true,
        ]);

        RekapBelanjaOperasiRekening::create([
            'master_rekap_id' => $pos->id,
            'kode_rekening' => $this->rekening->kode_rekening,
        ]);

        $this->actingAs($this->bp)
            ->getJson("/api/v1/rekap-belanja-operasi/drilldown?pos_key={$pos->id}&search=ATK")
            ->assertOk();
    }

    public function test_delete_user_referenced_in_anggaran_audit_logs_returns_422(): void
    {
        $targetUser = User::factory()->create([
            'role' => 'BPP',
            'unit_kerja_id' => $this->kelurahan->id,
        ]);

        AnggaranAuditLog::create([
            'anggaran_rekening_id' => $this->rekening->id,
            'unit_kerja_id' => $this->kecamatan->id,
            'user_id' => $targetUser->id,
            'actor_role' => 'BPP',
            'action' => 'UPDATE_PAGU',
            'old_pagu_perubahan' => 40000000,
            'new_pagu_perubahan' => 50000000,
            'created_at' => now(),
        ]);

        $this->actingAs($this->verifikator)
            ->deleteJson("/api/v1/users/{$targetUser->id}")
            ->assertStatus(422);
    }
}
