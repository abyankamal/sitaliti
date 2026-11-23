<?php

namespace Tests\Feature\Sppd;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiLampiran;
use App\Models\KuitansiSppdPeserta;
use App\Models\Pegawai;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SppdScheduleConflictTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    private User $bppA;

    private User $verifikator;

    private AnggaranRekening $rekA1;

    private AnggaranRekening $rekA2;

    private AnggaranRekening $rekB1;

    private Pegawai $pegawai1;

    private Pegawai $pegawaiInactive;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(UnitKerjaSeeder::class);
        $units = UnitKerja::where('tipe', 'KELURAHAN')->take(2)->get();
        $this->unitA = $units[0];
        $this->unitB = $units[1];

        $this->bppA = User::factory()->create(['role' => 'BPP', 'unit_kerja_id' => $this->unitA->id]);
        $this->verifikator = User::factory()->create(['role' => 'VERIFIKATOR', 'unit_kerja_id' => $this->unitA->id]);

        $this->rekA1 = $this->createSppdRekening($this->unitA, '7.01.01.2.01.0001', 'B.4');
        $this->rekA2 = $this->createSppdRekening($this->unitA, '7.01.01.2.01.0002', 'B4_PERJADIN');
        $this->rekB1 = $this->createSppdRekening($this->unitB, '7.01.01.2.02.0001', 'B.4');

        $this->pegawai1 = Pegawai::create([
            'unit_kerja_id' => $this->unitA->id,
            'nip' => '198501012010011001',
            'nama' => 'Budi Santoso',
            'is_active' => true,
        ]);

        $this->pegawaiInactive = Pegawai::create([
            'unit_kerja_id' => $this->unitA->id,
            'nip' => '198501012010011099',
            'nama' => 'Pegawai Nonaktif',
            'is_active' => false,
        ]);
    }

    private function createSppdRekening(UnitKerja $unit, string $kodeSub, string $kategori): AnggaranRekening
    {
        $rek = AnggaranRekening::create([
            'unit_kerja_id' => $unit->id,
            'kode_sub_kegiatan' => $kodeSub,
            'nama_sub_kegiatan' => "Subkeg {$kodeSub}",
            'kode_rekening' => '5.1.02.04.01.0001',
            'nama_rekening' => 'Belanja Perjalanan Dinas',
            'pagu_murni' => 50000000,
            'pagu_perubahan' => 50000000,
            'kategori_belanja' => $kategori,
            'is_leaf' => true,
        ]);

        for ($m = 1; $m <= 12; $m++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $rek->id,
                'bulan' => $m,
                'alokasi_pagu' => 10000000,
                'realisasi_sp2d' => 0,
                'terkunci_bulan' => 0,
            ]);
        }

        return $rek;
    }

    private function baseReceiptPayload(AnggaranRekening $rekening, array $peserta): array
    {
        return [
            'anggaran_rekening_id' => $rekening->id,
            'bulan_anggaran' => 5,
            'tanggal_transaksi' => '2026-05-10',
            'nama_penerima' => 'Budi Santoso',
            'uraian_belanja' => 'Perjalanan Dinas',
            'items' => [
                ['nama_barang' => 'Uang Harian', 'volume' => 1, 'satuan' => 'OH', 'harga_satuan' => 300000],
            ],
            'sppd_peserta_synced' => true,
            'sppd_peserta' => $peserta,
        ];
    }

    public function test_conflict_is_rejected_across_sub_kegiatan_and_unit_kerja_while_adjacent_dates_allowed(): void
    {
        // Kuitansi 1: 10-12 Mei 2026
        $this->actingAs($this->bppA)
            ->postJson('/api/v1/receipts', $this->baseReceiptPayload($this->rekA1, [
                [
                    'pegawai_id' => $this->pegawai1->id,
                    'tanggal_berangkat' => '2026-05-10',
                    'tanggal_pulang' => '2026-05-12',
                    'tujuan' => 'Bandung',
                ],
            ]))
            ->assertCreated();

        // Bentrok lintas Sub Kegiatan (rekA2 berkategori B4_PERJADIN) pada 12-13 Mei (irisan 1 hari)
        $this->actingAs($this->bppA)
            ->postJson('/api/v1/receipts', $this->baseReceiptPayload($this->rekA2, [
                [
                    'pegawai_id' => $this->pegawai1->id,
                    'tanggal_berangkat' => '2026-05-12',
                    'tanggal_pulang' => '2026-05-13',
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SPPD_SCHEDULE_CONFLICT');

        // Bentrok lintas Unit Kerja (rekB1 oleh Verifikator) pada 11 Mei (1 hari di dalam rentang)
        $this->actingAs($this->verifikator)
            ->postJson('/api/v1/receipts', $this->baseReceiptPayload($this->rekB1, [
                [
                    'pegawai_id' => $this->pegawai1->id,
                    'tanggal_berangkat' => '2026-05-11',
                    'tanggal_pulang' => '2026-05-11',
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SPPD_SCHEDULE_CONFLICT');

        // Tanggal bersebelahan (13 Mei) diperbolehkan
        $this->actingAs($this->bppA)
            ->postJson('/api/v1/receipts', $this->baseReceiptPayload($this->rekA2, [
                [
                    'pegawai_id' => $this->pegawai1->id,
                    'tanggal_berangkat' => '2026-05-13',
                    'tanggal_pulang' => '2026-05-14',
                ],
            ]))
            ->assertCreated();
    }

    public function test_ditolak_and_revisi_mayor_receipts_do_not_block_schedule(): void
    {
        $kuitansi = Kuitansi::create([
            'nomor_kuitansi' => 'KW-2026-05-0010',
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekA1->id,
            'user_id' => $this->bppA->id,
            'bulan_anggaran' => 5,
            'tanggal_transaksi' => '2026-05-10',
            'uraian' => 'Perjadin Ditolak',
            'nama_penerima' => 'Budi Santoso',
            'nominal_bruto' => 300000,
            'total_pajak' => 0,
            'nominal_neto' => 300000,
            'status' => 'REVISI_MAYOR',
        ]);

        KuitansiSppdPeserta::create([
            'kuitansi_id' => $kuitansi->id,
            'pegawai_id' => $this->pegawai1->id,
            'tanggal_berangkat' => '2026-05-10',
            'tanggal_pulang' => '2026-05-12',
        ]);

        $this->actingAs($this->bppA)
            ->postJson('/api/v1/receipts', $this->baseReceiptPayload($this->rekA2, [
                [
                    'pegawai_id' => $this->pegawai1->id,
                    'tanggal_berangkat' => '2026-05-10',
                    'tanggal_pulang' => '2026-05-12',
                ],
            ]))
            ->assertCreated();
    }

    public function test_submit_returns_422_instead_of_500_when_schedule_conflicts(): void
    {
        // Kuitansi A aktif (DRAFT) memakai 10-12 Mei
        $this->actingAs($this->bppA)
            ->postJson('/api/v1/receipts', $this->baseReceiptPayload($this->rekA1, [
                [
                    'pegawai_id' => $this->pegawai1->id,
                    'tanggal_berangkat' => '2026-05-10',
                    'tanggal_pulang' => '2026-05-12',
                ],
            ]))
            ->assertCreated();

        // Kuitansi B berstatus REVISI_MAYOR (tidak memblokir) lalu akan disubmit ulang
        $kuitansiB = Kuitansi::create([
            'nomor_kuitansi' => 'KW-2026-05-0020',
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekA2->id,
            'user_id' => $this->bppA->id,
            'bulan_anggaran' => 5,
            'tanggal_transaksi' => '2026-05-11',
            'uraian' => 'Perjadin Bentrok Saat Submit',
            'nama_penerima' => 'Budi Santoso',
            'nominal_bruto' => 300000,
            'total_pajak' => 0,
            'nominal_neto' => 300000,
            'status' => 'REVISI_MAYOR',
            'sppd_foto_path' => 'sppd-photos/2026/05/dummy.jpg',
        ]);

        KuitansiLampiran::create([
            'kuitansi_id' => $kuitansiB->id,
            'anggaran_rekening_id' => $this->rekA2->id,
            'checklist_code' => 'B.4.1',
            'nama_dokumen' => 'Surat Tugas',
            'file_path' => 'lampiran/dummy.pdf',
            'file_name' => 'dummy.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'dummy-content'),
        ]);

        KuitansiSppdPeserta::create([
            'kuitansi_id' => $kuitansiB->id,
            'pegawai_id' => $this->pegawai1->id,
            'tanggal_berangkat' => '2026-05-11',
            'tanggal_pulang' => '2026-05-11',
        ]);

        $this->actingAs($this->bppA)
            ->postJson("/api/v1/receipts/{$kuitansiB->id}/submit")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SPPD_SCHEDULE_CONFLICT');
    }

    public function test_partial_update_and_edit_preserve_participants_and_photos_and_reject_duplicates_or_inactive(): void
    {
        $foto = UploadedFile::fake()->image('peserta.jpg');

        $createRes = $this->actingAs($this->bppA)
            ->post('/api/v1/receipts', [
                'anggaran_rekening_id' => $this->rekA1->id,
                'bulan_anggaran' => 5,
                'tanggal_transaksi' => '2026-05-15',
                'nama_penerima' => 'Budi Santoso',
                'items' => [
                    ['nama_barang' => 'Uang Harian', 'volume' => 1, 'satuan' => 'OH', 'harga_satuan' => 300000],
                ],
                'sppd_peserta_synced' => '1',
                'sppd_peserta' => [
                    [
                        'pegawai_id' => $this->pegawai1->id,
                        'tanggal_berangkat' => '2026-05-15',
                        'tanggal_pulang' => '2026-05-16',
                        'foto' => $foto,
                    ],
                ],
            ])
            ->assertCreated();

        $receiptId = $createRes->json('data.id');
        $originalFotoPath = KuitansiSppdPeserta::where('kuitansi_id', $receiptId)->value('foto_path');
        $this->assertNotNull($originalFotoPath);

        // Partial update tanpa key sppd_peserta tidak boleh menghapus peserta
        $this->actingAs($this->bppA)
            ->putJson("/api/v1/receipts/{$receiptId}", [
                'is_sewa_kendaraan' => true,
                'items' => [
                    ['nama_barang' => 'Uang Harian', 'volume' => 1, 'satuan' => 'OH', 'harga_satuan' => 300000],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('kuitansi_sppd_peserta', [
            'kuitansi_id' => $receiptId,
            'pegawai_id' => $this->pegawai1->id,
            'foto_path' => $originalFotoPath,
        ]);

        // Edit dengan sppd_peserta_synced=1 tanpa upload foto baru tetap mempertahankan foto eksisting
        $this->actingAs($this->bppA)
            ->putJson("/api/v1/receipts/{$receiptId}", $this->baseReceiptPayload($this->rekA1, [
                [
                    'pegawai_id' => $this->pegawai1->id,
                    'tanggal_berangkat' => '2026-05-15',
                    'tanggal_pulang' => '2026-05-17',
                ],
            ]))
            ->assertOk();

        $this->assertDatabaseHas('kuitansi_sppd_peserta', [
            'kuitansi_id' => $receiptId,
            'pegawai_id' => $this->pegawai1->id,
            'foto_path' => $originalFotoPath,
        ]);

        // Duplikat pegawai dalam 1 kuitansi ditolak
        $this->actingAs($this->bppA)
            ->postJson('/api/v1/receipts', $this->baseReceiptPayload($this->rekA2, [
                ['pegawai_id' => $this->pegawai1->id, 'tanggal_berangkat' => '2026-05-20', 'tanggal_pulang' => '2026-05-20'],
                ['pegawai_id' => $this->pegawai1->id, 'tanggal_berangkat' => '2026-05-21', 'tanggal_pulang' => '2026-05-21'],
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SPPD_SCHEDULE_CONFLICT');

        // Pegawai nonaktif ditolak
        $this->actingAs($this->bppA)
            ->postJson('/api/v1/receipts', $this->baseReceiptPayload($this->rekA2, [
                ['pegawai_id' => $this->pegawaiInactive->id, 'tanggal_berangkat' => '2026-05-22', 'tanggal_pulang' => '2026-05-22'],
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INACTIVE_PEGAWAI');
    }
}
