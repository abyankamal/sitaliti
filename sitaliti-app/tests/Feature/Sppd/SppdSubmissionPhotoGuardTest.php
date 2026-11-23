<?php

namespace Tests\Feature\Sppd;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\KuitansiLampiran;
use App\Models\Pegawai;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SppdSubmissionPhotoGuardTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unit;

    private User $bpp;

    private AnggaranRekening $rekening;

    private Pegawai $pegawai1;

    private Pegawai $pegawai2;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(UnitKerjaSeeder::class);
        $this->unit = UnitKerja::where('tipe', 'KELURAHAN')->first();
        $this->bpp = User::factory()->create(['role' => 'BPP', 'unit_kerja_id' => $this->unit->id]);

        $this->rekening = AnggaranRekening::create([
            'unit_kerja_id' => $this->unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Subkeg SPPD',
            'kode_rekening' => '5.1.02.04.01.0001',
            'nama_rekening' => 'Belanja Perjalanan Dinas',
            'pagu_murni' => 50000000,
            'pagu_perubahan' => 50000000,
            'kategori_belanja' => 'B.4',
            'is_leaf' => true,
        ]);

        for ($m = 1; $m <= 12; $m++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $this->rekening->id,
                'bulan' => $m,
                'alokasi_pagu' => 10000000,
                'realisasi_sp2d' => 0,
                'terkunci_bulan' => 0,
            ]);
        }

        $this->pegawai1 = Pegawai::create([
            'unit_kerja_id' => $this->unit->id,
            'nip' => '198501012010011001',
            'nama' => 'Peserta Satu',
        ]);

        $this->pegawai2 = Pegawai::create([
            'unit_kerja_id' => $this->unit->id,
            'nip' => '198501012010011002',
            'nama' => 'Peserta Dua',
        ]);
    }

    private function attachGeneralDocument(int $kuitansiId): void
    {
        KuitansiLampiran::create([
            'kuitansi_id' => $kuitansiId,
            'anggaran_rekening_id' => $this->rekening->id,
            'checklist_code' => 'B.4.1',
            'nama_dokumen' => 'Surat Tugas',
            'file_path' => 'lampiran/surat-tugas.pdf',
            'file_name' => 'surat-tugas.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', "surat-tugas-{$kuitansiId}"),
        ]);
    }

    public function test_draft_can_be_saved_without_photo_but_submit_requires_participants_and_photo(): void
    {
        // 1. Simpan DRAFT SPPD tanpa peserta & tanpa foto -> berhasil
        $draftRes = $this->actingAs($this->bpp)
            ->postJson('/api/v1/receipts', [
                'anggaran_rekening_id' => $this->rekening->id,
                'bulan_anggaran' => 5,
                'tanggal_transaksi' => '2026-05-10',
                'nama_penerima' => 'Peserta Satu',
                'items' => [
                    ['nama_barang' => 'Uang Harian', 'volume' => 1, 'satuan' => 'OH', 'harga_satuan' => 250000],
                ],
            ])
            ->assertCreated();

        $receiptId = $draftRes->json('data.id');
        $this->attachGeneralDocument($receiptId);

        // 2. Submit tanpa peserta -> ditolak 422
        $this->actingAs($this->bpp)
            ->postJson("/api/v1/receipts/{$receiptId}/submit")
            ->assertUnprocessable();

        // 3. Tambah peserta tanpa foto -> submit masih ditolak 422
        $this->actingAs($this->bpp)
            ->putJson("/api/v1/receipts/{$receiptId}", [
                'items' => [
                    ['nama_barang' => 'Uang Harian', 'volume' => 1, 'satuan' => 'OH', 'harga_satuan' => 250000],
                ],
                'sppd_peserta_synced' => true,
                'sppd_peserta' => [
                    [
                        'pegawai_id' => $this->pegawai1->id,
                        'tanggal_berangkat' => '2026-05-10',
                        'tanggal_pulang' => '2026-05-11',
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAs($this->bpp)
            ->postJson("/api/v1/receipts/{$receiptId}/submit")
            ->assertUnprocessable();

        // 4. Unggah 1 foto bersama rombongan -> submit berhasil
        $this->actingAs($this->bpp)
            ->post("/api/v1/receipts/{$receiptId}/update", [
                'items' => [
                    ['nama_barang' => 'Uang Harian', 'volume' => 1, 'satuan' => 'OH', 'harga_satuan' => 250000],
                ],
                'sppd_peserta_synced' => '1',
                'sppd_foto' => UploadedFile::fake()->image('rombongan.jpg'),
                'sppd_peserta' => [
                    [
                        'pegawai_id' => $this->pegawai1->id,
                        'tanggal_berangkat' => '2026-05-10',
                        'tanggal_pulang' => '2026-05-11',
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAs($this->bpp)
            ->postJson("/api/v1/receipts/{$receiptId}/submit")
            ->assertOk();
    }

    public function test_individual_participant_photos_must_all_be_present_when_no_group_photo(): void
    {
        $res = $this->actingAs($this->bpp)
            ->post('/api/v1/receipts', [
                'anggaran_rekening_id' => $this->rekening->id,
                'bulan_anggaran' => 5,
                'tanggal_transaksi' => '2026-05-15',
                'nama_penerima' => 'Rombongan',
                'items' => [
                    ['nama_barang' => 'Uang Harian', 'volume' => 2, 'satuan' => 'OH', 'harga_satuan' => 250000],
                ],
                'sppd_peserta_synced' => '1',
                'sppd_peserta' => [
                    [
                        'pegawai_id' => $this->pegawai1->id,
                        'tanggal_berangkat' => '2026-05-15',
                        'tanggal_pulang' => '2026-05-15',
                        'foto' => UploadedFile::fake()->image('p1.jpg'),
                    ],
                    [
                        'pegawai_id' => $this->pegawai2->id,
                        'tanggal_berangkat' => '2026-05-15',
                        'tanggal_pulang' => '2026-05-15',
                    ],
                ],
            ])
            ->assertCreated();

        $receiptId = $res->json('data.id');
        $this->attachGeneralDocument($receiptId);

        // Peserta 2 belum punya foto -> ditolak 422
        $this->actingAs($this->bpp)
            ->postJson("/api/v1/receipts/{$receiptId}/submit")
            ->assertUnprocessable();

        // Lengkapi foto peserta 2 -> berhasil submit
        $this->actingAs($this->bpp)
            ->post("/api/v1/receipts/{$receiptId}/update", [
                'items' => [
                    ['nama_barang' => 'Uang Harian', 'volume' => 2, 'satuan' => 'OH', 'harga_satuan' => 250000],
                ],
                'sppd_peserta_synced' => '1',
                'sppd_peserta' => [
                    [
                        'pegawai_id' => $this->pegawai1->id,
                        'tanggal_berangkat' => '2026-05-15',
                        'tanggal_pulang' => '2026-05-15',
                    ],
                    [
                        'pegawai_id' => $this->pegawai2->id,
                        'tanggal_berangkat' => '2026-05-15',
                        'tanggal_pulang' => '2026-05-15',
                        'foto' => UploadedFile::fake()->image('p2.png'),
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAs($this->bpp)
            ->postJson("/api/v1/receipts/{$receiptId}/submit")
            ->assertOk();
    }
}
