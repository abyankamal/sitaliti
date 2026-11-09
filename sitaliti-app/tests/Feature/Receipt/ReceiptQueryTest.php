<?php

namespace Tests\Feature\Receipt;

use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\MasterChecklistSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptQueryTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    private AnggaranRekening $rekeningA;

    private AnggaranRekening $rekeningB;

    private User $operatorA1;

    private User $operatorA2;

    private User $operatorB;

    private User $pptk;

    private User $verifikator;

    private User $kpa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->seed(MasterChecklistSeeder::class);

        $units = UnitKerja::take(2)->get();
        $this->unitA = $units[0];
        $this->unitB = $units[1];

        // Operator 1 dan 2 di Unit A
        $this->operatorA1 = User::factory()->create([
            'unit_kerja_id' => $this->unitA->id,
            'role' => 'OPERATOR',
        ]);

        $this->operatorA2 = User::factory()->create([
            'unit_kerja_id' => $this->unitA->id,
            'role' => 'OPERATOR',
        ]);

        // Operator di Unit B
        $this->operatorB = User::factory()->create([
            'unit_kerja_id' => $this->unitB->id,
            'role' => 'OPERATOR',
        ]);

        // PPTK, Verifikator, dan KPA
        $this->pptk = User::factory()->create([
            'unit_kerja_id' => $this->unitA->id,
            'role' => 'PPTK',
        ]);

        $this->verifikator = User::factory()->create([
            'unit_kerja_id' => $this->unitA->id,
            'role' => 'VERIFIKATOR',
        ]);

        $this->kpa = User::factory()->create([
            'unit_kerja_id' => $this->unitA->id,
            'role' => 'KPA',
        ]);

        // Rekening untuk Unit A (Kategori B.1 ATK)
        $this->rekeningA = AnggaranRekening::create([
            'unit_kerja_id' => $this->unitA->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Penyusunan Rencana Kerja',
            'kode_rekening' => '5.1.02.01.001.00024',
            'nama_rekening' => 'Belanja Alat Tulis Kantor',
            'pagu_murni' => 20000000.00,
            'pagu_perubahan' => 20000000.00,
            'kategori_belanja' => 'B.1',
        ]);

        // Rekening untuk Unit B (Kategori B.2 Mamin)
        $this->rekeningB = AnggaranRekening::create([
            'unit_kerja_id' => $this->unitB->id,
            'kode_sub_kegiatan' => '7.01.01.2.02.0002',
            'nama_sub_kegiatan' => 'Fasilitasi Pertemuan',
            'kode_rekening' => '5.1.02.01.001.00052',
            'nama_rekening' => 'Belanja Makanan dan Minuman',
            'pagu_murni' => 15000000.00,
            'pagu_perubahan' => 15000000.00,
            'kategori_belanja' => 'B.2',
        ]);

        for ($b = 1; $b <= 12; $b++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $this->rekeningA->id,
                'bulan' => $b,
                'alokasi_pagu' => 1500000.00,
                'terkunci_bulan' => 0.00,
                'realisasi_bulan' => 0.00,
            ]);

            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $this->rekeningB->id,
                'bulan' => $b,
                'alokasi_pagu' => 1000000.00,
                'terkunci_bulan' => 0.00,
                'realisasi_bulan' => 0.00,
            ]);
        }
    }

    public function test_unauthenticated_request_is_rejected_with_401(): void
    {
        $response = $this->getJson('/api/v1/receipts');
        $response->assertStatus(401);
    }

    public function test_authenticated_operator_can_get_paginated_receipts_with_proper_structure(): void
    {
        // Buat 1 kuitansi milik Operator A1
        $kuitansi = Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningA->id,
            'user_id' => $this->operatorA1->id,
            'nomor_kuitansi' => 'KW-TEST-STRUCT-01',
            'bulan_anggaran' => 3,
            'tanggal_transaksi' => '2026-03-15',
            'uraian' => 'Pembelian Kertas HVS F4',
            'nama_penerima' => 'Toko Buku Tarogong',
            'nominal_bruto' => 1200000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1200000.00,
            'status' => 'MENUNGGU_PPTK',
        ]);

        $response = $this->actingAs($this->operatorA1)->getJson('/api/v1/receipts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'nomor_kuitansi',
                            'bulan_anggaran',
                            'tanggal_transaksi',
                            'uraian',
                            'nama_penerima',
                            'nominal_bruto',
                            'total_pajak',
                            'nominal_neto',
                            'status',
                            'rekening' => [
                                'id',
                                'nama_rekening',
                                'kode_rekening',
                                'kategori_belanja',
                                'unit_kerja',
                            ],
                        ],
                    ],
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
                'tab_counts' => [
                    'semua',
                    'draft',
                    'menunggu_pptk',
                    'menunggu_verifikasi',
                    'revisi_minor',
                    'siap_bundel',
                ],
            ]);
    }

    public function test_operator_scoping_only_sees_own_unit_and_own_drafts(): void
    {
        // Kuitansi 1: Unit A, dibuat Operator A1, status DRAFT
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningA->id,
            'user_id' => $this->operatorA1->id,
            'nomor_kuitansi' => 'KW-DRAFT-A1',
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-10',
            'uraian' => 'Draft kuitansi A1',
            'nama_penerima' => 'CV Pertama',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'DRAFT',
        ]);

        // Kuitansi 2: Unit A, dibuat Operator A2, status DRAFT
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningA->id,
            'user_id' => $this->operatorA2->id,
            'nomor_kuitansi' => 'KW-DRAFT-A2',
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-11',
            'uraian' => 'Draft kuitansi A2',
            'nama_penerima' => 'CV Kedua',
            'nominal_bruto' => 600000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 600000.00,
            'status' => 'DRAFT',
        ]);

        // Kuitansi 3: Unit A, dibuat Operator A2, status MENUNGGU_PPTK (sudah diajukan, visible di Unit A)
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningA->id,
            'user_id' => $this->operatorA2->id,
            'nomor_kuitansi' => 'KW-SUBMITTED-A2',
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-12',
            'uraian' => 'Submitted kuitansi A2',
            'nama_penerima' => 'CV Ketiga',
            'nominal_bruto' => 700000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 700000.00,
            'status' => 'MENUNGGU_PPTK',
        ]);

        // Kuitansi 4: Unit B, dibuat Operator B, status MENUNGGU_PPTK
        Kuitansi::create([
            'unit_kerja_id' => $this->unitB->id,
            'anggaran_rekening_id' => $this->rekeningB->id,
            'user_id' => $this->operatorB->id,
            'nomor_kuitansi' => 'KW-SUBMITTED-B',
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-13',
            'uraian' => 'Submitted kuitansi B',
            'nama_penerima' => 'CV Keempat',
            'nominal_bruto' => 800000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 800000.00,
            'status' => 'MENUNGGU_PPTK',
        ]);

        // Operator A1 melihat daftar:
        // Harus melihat: KW-DRAFT-A1 (milik sendiri) dan KW-SUBMITTED-A2 (unit sama, non-draft)
        // TIDAK BOLEH melihat: KW-DRAFT-A2 (draft orang lain) dan KW-SUBMITTED-B (unit lain)
        $resA1 = $this->actingAs($this->operatorA1)->getJson('/api/v1/receipts');
        $resA1->assertStatus(200);

        $nomorListA1 = collect($resA1->json('data.data'))->pluck('nomor_kuitansi')->all();
        $this->assertContains('KW-DRAFT-A1', $nomorListA1);
        $this->assertContains('KW-SUBMITTED-A2', $nomorListA1);
        $this->assertNotContains('KW-DRAFT-A2', $nomorListA1);
        $this->assertNotContains('KW-SUBMITTED-B', $nomorListA1);
        $this->assertCount(2, $nomorListA1);
    }

    public function test_verifikator_and_kpa_have_cross_unit_visibility(): void
    {
        // 1 kuitansi di Unit A dan 1 kuitansi di Unit B (non-draft)
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningA->id,
            'user_id' => $this->operatorA1->id,
            'nomor_kuitansi' => 'KW-CROSS-A',
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-01',
            'uraian' => 'Kuitansi Unit A',
            'nama_penerima' => 'Penerima A',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        Kuitansi::create([
            'unit_kerja_id' => $this->unitB->id,
            'anggaran_rekening_id' => $this->rekeningB->id,
            'user_id' => $this->operatorB->id,
            'nomor_kuitansi' => 'KW-CROSS-B',
            'bulan_anggaran' => 2,
            'tanggal_transaksi' => '2026-02-02',
            'uraian' => 'Kuitansi Unit B',
            'nama_penerima' => 'Penerima B',
            'nominal_bruto' => 1500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1500000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        // Verifikator mengakses:
        $resVerif = $this->actingAs($this->verifikator)->getJson('/api/v1/receipts');
        $resVerif->assertStatus(200);
        $nomorListVerif = collect($resVerif->json('data.data'))->pluck('nomor_kuitansi')->all();
        $this->assertContains('KW-CROSS-A', $nomorListVerif);
        $this->assertContains('KW-CROSS-B', $nomorListVerif);

        // KPA mengakses:
        $resKpa = $this->actingAs($this->kpa)->getJson('/api/v1/receipts');
        $resKpa->assertStatus(200);
        $nomorListKpa = collect($resKpa->json('data.data'))->pluck('nomor_kuitansi')->all();
        $this->assertContains('KW-CROSS-A', $nomorListKpa);
        $this->assertContains('KW-CROSS-B', $nomorListKpa);
    }

    public function test_receipts_can_be_filtered_by_status_unit_month_and_category(): void
    {
        // Setup kuitansi variatif
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningA->id, // B.1
            'user_id' => $this->operatorA1->id,
            'nomor_kuitansi' => 'KW-FLT-01',
            'bulan_anggaran' => 3,
            'tanggal_transaksi' => '2026-03-01',
            'uraian' => 'Filter Target',
            'nama_penerima' => 'Toko ATK',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'REVISI_MINOR',
        ]);

        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningA->id,
            'user_id' => $this->operatorA1->id,
            'nomor_kuitansi' => 'KW-FLT-02',
            'bulan_anggaran' => 4, // beda bulan
            'tanggal_transaksi' => '2026-04-01',
            'uraian' => 'Beda Bulan',
            'nama_penerima' => 'Toko ATK',
            'nominal_bruto' => 1000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1000000.00,
            'status' => 'REVISI_MINOR',
        ]);

        Kuitansi::create([
            'unit_kerja_id' => $this->unitB->id,
            'anggaran_rekening_id' => $this->rekeningB->id, // B.2
            'user_id' => $this->operatorB->id,
            'nomor_kuitansi' => 'KW-FLT-03',
            'bulan_anggaran' => 3,
            'tanggal_transaksi' => '2026-03-05',
            'uraian' => 'Beda Unit & Kategori',
            'nama_penerima' => 'Resto Sedap',
            'nominal_bruto' => 2000000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 2000000.00,
            'status' => 'MENUNGGU_PPTK',
        ]);

        // Filter status=REVISI_MINOR, bulan=3, kategori=B.1
        $res = $this->actingAs($this->verifikator)->getJson('/api/v1/receipts?'.http_build_query([
            'status' => 'REVISI_MINOR',
            'bulan_anggaran' => 3,
            'kategori_belanja' => 'B.1',
            'unit_kerja_id' => $this->unitA->id,
        ]));

        $res->assertStatus(200);
        $data = $res->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('KW-FLT-01', $data[0]['nomor_kuitansi']);
    }

    public function test_receipts_search_filter_matches_number_description_or_payee(): void
    {
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningA->id,
            'user_id' => $this->operatorA1->id,
            'nomor_kuitansi' => 'KW-SRCH-999',
            'bulan_anggaran' => 5,
            'tanggal_transaksi' => '2026-05-10',
            'uraian' => 'Pengadaan Spanduk Sosialisasi',
            'nama_penerima' => 'Percetakan Berkah',
            'nominal_bruto' => 1500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 1500000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        // Search by nomor
        $res1 = $this->actingAs($this->verifikator)->getJson('/api/v1/receipts?search=SRCH-999');
        $this->assertCount(1, $res1->json('data.data'));

        // Search by uraian
        $res2 = $this->actingAs($this->verifikator)->getJson('/api/v1/receipts?search=Spanduk');
        $this->assertCount(1, $res2->json('data.data'));

        // Search by nama rekanan/penerima
        $res3 = $this->actingAs($this->verifikator)->getJson('/api/v1/receipts?search=Berkah');
        $this->assertCount(1, $res3->json('data.data'));

        // Search tidak cocok
        $res4 = $this->actingAs($this->verifikator)->getJson('/api/v1/receipts?search=XYZNOTFOUND');
        $this->assertCount(0, $res4->json('data.data'));
    }

    public function test_status_tab_counts_aggregation_is_accurate(): void
    {
        // 1 Draft (A1)
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningA->id,
            'user_id' => $this->operatorA1->id,
            'nomor_kuitansi' => 'KW-CNT-01',
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-01',
            'uraian' => 'Draft',
            'nama_penerima' => 'Penerima',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'DRAFT',
        ]);

        // 2 Menunggu PPTK
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningA->id,
            'user_id' => $this->operatorA1->id,
            'nomor_kuitansi' => 'KW-CNT-02',
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-02',
            'uraian' => 'Menunggu PPTK 1',
            'nama_penerima' => 'Penerima',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'MENUNGGU_PPTK',
        ]);

        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningA->id,
            'user_id' => $this->operatorA1->id,
            'nomor_kuitansi' => 'KW-CNT-03',
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-03',
            'uraian' => 'Menunggu PPTK 2',
            'nama_penerima' => 'Penerima',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'MENUNGGU_PPTK',
        ]);

        // 1 Menunggu Verifikasi
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningA->id,
            'user_id' => $this->operatorA1->id,
            'nomor_kuitansi' => 'KW-CNT-04',
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-04',
            'uraian' => 'Menunggu Verif',
            'nama_penerima' => 'Penerima',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'MENUNGGU_VERIFIKASI',
        ]);

        // 1 Revisi Minor
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningA->id,
            'user_id' => $this->operatorA1->id,
            'nomor_kuitansi' => 'KW-CNT-05',
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-05',
            'uraian' => 'Revisi Minor',
            'nama_penerima' => 'Penerima',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'REVISI_MINOR',
        ]);

        // 1 Lolos / Siap Bundel (TERVERIFIKASI)
        Kuitansi::create([
            'unit_kerja_id' => $this->unitA->id,
            'anggaran_rekening_id' => $this->rekeningA->id,
            'user_id' => $this->operatorA1->id,
            'nomor_kuitansi' => 'KW-CNT-06',
            'bulan_anggaran' => 1,
            'tanggal_transaksi' => '2026-01-06',
            'uraian' => 'Lolos Verif',
            'nama_penerima' => 'Penerima',
            'nominal_bruto' => 500000.00,
            'total_pajak' => 0.00,
            'nominal_neto' => 500000.00,
            'status' => 'TERVERIFIKASI',
        ]);

        $res = $this->actingAs($this->operatorA1)->getJson('/api/v1/receipts');
        $res->assertStatus(200);

        $tabCounts = $res->json('tab_counts');
        $this->assertEquals(6, $tabCounts['semua']);
        $this->assertEquals(1, $tabCounts['draft']);
        $this->assertEquals(2, $tabCounts['menunggu_pptk']);
        $this->assertEquals(1, $tabCounts['menunggu_verifikasi']);
        $this->assertEquals(1, $tabCounts['revisi_minor']);
        $this->assertEquals(1, $tabCounts['siap_bundel']);
    }
}
