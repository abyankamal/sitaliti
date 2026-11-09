<?php

namespace Tests\Unit\Services;

use App\Contracts\BudgetReservationServiceInterface;
use App\Exceptions\PaguOverlimitException;
use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BudgetReservationTest extends TestCase
{
    use RefreshDatabase;

    private BudgetReservationServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BudgetReservationServiceInterface::class);
    }

    /**
     * Test 1: getPopupAllocationData mengembalikan struktur pagu dan akumulasi carry-over bulan lalu.
     * Skenario:
     * - Bulan 1 (Januari): Pagu 10.000.000, realisasi 6.000.000 -> Sisa 4.000.000
     * - Bulan 2 (Februari): Pagu 15.000.000
     * Carry-over ke Februari = 4.000.000
     * Total tersedia Februari = 15.000.000 + 4.000.000 = 19.000.000
     */
    public function test_alokasi_popup_menampilkan_pagu_dan_carry_over_akumulatif(): void
    {
        $rekeningId = '5.1.02.01.01.0024'; // Belanja Alat Tulis Kantor
        $bulanDipilih = 2; // Februari
        $activeMonth = 2;

        $data = $this->service->getPopupAllocationData($rekeningId, $bulanDipilih, $activeMonth);

        $this->assertEquals($rekeningId, $data['rekening_id']);
        $this->assertEquals(2, $data['selected_month']);
        $this->assertEquals(2, $data['active_system_month']);
        $this->assertFalse($data['is_future_month']);
        $this->assertGreaterThan(0, $data['pagu_murni_bulan']);
        $this->assertGreaterThanOrEqual(0, $data['carry_over_lalu']);
        $this->assertEquals(
            $data['pagu_murni_bulan'] + $data['carry_over_lalu'],
            $data['total_tersedia'],
            'Total tersedia harus sama dengan pagu murni bulan berjalan + akumulasi carry-over'
        );
        $this->assertEquals(
            $data['total_tersedia'] - $data['terkunci_komitmen'],
            $data['sisa_dapat_digunakan'],
            'Sisa dapat digunakan harus sama dengan total tersedia - terkunci komitmen'
        );
        $this->assertEquals('OPEN', $data['status_lock']);
    }

    /**
     * Test 2: Bulan mendatang (future month) otomatis terkunci dan sisa dapat digunakan adalah Rp 0.
     * Skenario:
     * - Sistem berada di Bulan 3 (Maret).
     * - Pengguna mencoba memeriksa alokasi Bulan 5 (Mei).
     * - Mei berstatus LOCKED_FUTURE, is_future_month = true, sisa_dapat_digunakan = 0.
     */
    public function test_bulan_mendatang_otomatis_terkunci_dan_sisa_nol(): void
    {
        $rekeningId = '5.1.02.01.01.0024';
        $bulanDipilih = 5; // Mei
        $activeMonth = 3; // Maret

        $data = $this->service->getPopupAllocationData($rekeningId, $bulanDipilih, $activeMonth);

        $this->assertTrue($data['is_future_month']);
        $this->assertEquals('LOCKED_FUTURE', $data['status_lock']);
        $this->assertEquals(0.0, $data['sisa_dapat_digunakan'], 'Bulan masa depan tidak boleh memiliki sisa yang dapat ditarik');
    }

    /**
     * Test 3: reserveBudget berhasil mengunci pagu jika saldo tersedia mencukupi.
     */
    public function test_reserve_budget_berhasil_mengunci_pagu_tersedia(): void
    {
        $rekeningId = '5.1.02.01.01.0024';
        $bulan = 2;
        $activeMonth = 2;
        $nominal = 2500000.0;

        $before = $this->service->getPopupAllocationData($rekeningId, $bulan, $activeMonth);

        $this->service->reserveBudget($rekeningId, $bulan, $nominal, $activeMonth);

        $after = $this->service->getPopupAllocationData($rekeningId, $bulan, $activeMonth);

        $this->assertEquals(
            $before['terkunci_komitmen'] + $nominal,
            $after['terkunci_komitmen'],
            'Pagu terkunci harus bertambah tepat sebesar nominal kuitansi'
        );
        $this->assertEquals(
            $before['sisa_dapat_digunakan'] - $nominal,
            $after['sisa_dapat_digunakan'],
            'Sisa dapat digunakan harus berkurang tepat sebesar nominal kuitansi'
        );
    }

    /**
     * Test 4: reserveBudget melempar PaguOverlimitException jika nominal melebihi sisa pagu tersedia.
     */
    public function test_reserve_budget_melempar_exception_jika_overlimit(): void
    {
        $rekeningId = '5.1.02.01.01.0024';
        $bulan = 2;
        $activeMonth = 2;

        $data = $this->service->getPopupAllocationData($rekeningId, $bulan, $activeMonth);
        $overlimitNominal = $data['sisa_dapat_digunakan'] + 500000000.0; // Melebihi pagu sebesar Rp 500jt

        $this->expectException(PaguOverlimitException::class);
        $this->service->reserveBudget($rekeningId, $bulan, $overlimitNominal, $activeMonth);
    }

    /**
     * Test 5: reserveBudget melempar PaguOverlimitException jika mencoba menarik pagu bulan masa depan.
     */
    public function test_reserve_budget_menolak_penarikan_pada_bulan_masa_depan(): void
    {
        $rekeningId = '5.1.02.01.01.0024';
        $bulan = 6; // Juni
        $activeMonth = 3; // Maret
        $nominal = 1000000.0;

        $this->expectException(PaguOverlimitException::class);
        $this->service->reserveBudget($rekeningId, $bulan, $nominal, $activeMonth);
    }

    /**
     * Test 6: reserveBudget menolak nominal negatif atau nol (mencegah Budget Inflation exploit).
     */
    public function test_reserve_budget_menolak_nominal_negatif_atau_nol(): void
    {
        $rekeningId = '5.1.02.01.01.0024';
        $bulan = 2;
        $activeMonth = 2;

        // Nominal Nol
        try {
            $this->service->reserveBudget($rekeningId, $bulan, 0.0, $activeMonth);
            $this->fail('Harus melempar InvalidArgumentException untuk nominal 0');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue(true);
        }

        // Nominal Negatif
        try {
            $this->service->reserveBudget($rekeningId, $bulan, -500000.0, $activeMonth);
            $this->fail('Harus melempar InvalidArgumentException untuk nominal negatif');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test 7: releaseBudget mengurangi pagu terkunci dan memiliki proteksi underflow (tidak pernah negatif).
     */
    public function test_release_budget_mengurangi_kuncian_dengan_aman(): void
    {
        $rekeningId = '5.1.02.01.01.0024';
        $bulan = 2;
        $activeMonth = 2;
        $nominal = 1500000.0;

        // Kunci terlebih dahulu
        $this->service->reserveBudget($rekeningId, $bulan, $nominal, $activeMonth);
        $mid = $this->service->getPopupAllocationData($rekeningId, $bulan, $activeMonth);

        // Lepas kuncian
        $this->service->releaseBudget($rekeningId, $bulan, $nominal);
        $after = $this->service->getPopupAllocationData($rekeningId, $bulan, $activeMonth);

        $this->assertEquals(
            $mid['terkunci_komitmen'] - $nominal,
            $after['terkunci_komitmen']
        );

        // Uji proteksi underflow: melepas lebih banyak daripada kuncian yang ada
        $this->service->releaseBudget($rekeningId, $bulan, 999999999.0);
        $afterUnderflow = $this->service->getPopupAllocationData($rekeningId, $bulan, $activeMonth);

        $this->assertGreaterThanOrEqual(0.0, $afterUnderflow['terkunci_komitmen'], 'Terkunci komitmen tidak boleh negatif');
    }

    /**
     * Test 8: settleBudget memindahkan komitmen dari pagu terkunci ke realisasi definitif.
     */
    public function test_settle_budget_memindahkan_kuncian_ke_realisasi_definitif(): void
    {
        $rekeningId = '5.1.02.01.01.0024';
        $bulan = 2;
        $activeMonth = 2;
        $nominal = 1000000.0;

        // Kunci
        $this->service->reserveBudget($rekeningId, $bulan, $nominal, $activeMonth);
        $before = $this->service->getPopupAllocationData($rekeningId, $bulan, $activeMonth);

        // Settle SP2D
        $this->service->settleBudget($rekeningId, $bulan, $nominal);
        $after = $this->service->getPopupAllocationData($rekeningId, $bulan, $activeMonth);

        $this->assertEquals(
            $before['terkunci_komitmen'] - $nominal,
            $after['terkunci_komitmen'],
            'Kuncian komitmen harus berkurang saat dibukukan menjadi realisasi'
        );
    }

    /**
     * Test 9: Integrasi transaksi database atomik saat baris AnggaranKasBulanan tersedia di DB.
     */
    public function test_reserve_release_settle_dengan_database_rows_dan_atomic_lock(): void
    {
        $unit = UnitKerja::create([
            'kode_unit' => '7.01.01',
            'nama_unit' => 'Kecamatan Tarogong Kidul',
            'tipe' => 'KECAMATAN',
            'is_active' => true,
        ]);

        $rekening = AnggaranRekening::create([
            'unit_kerja_id' => $unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Administrasi Umum',
            'kode_rekening' => '5.1.02.01.001.00024',
            'nama_rekening' => 'Belanja Alat Tulis Kantor',
            'pagu_murni' => 120000000.00,
            'pagu_perubahan' => 120000000.00,
            'kategori_belanja' => 'B.1',
            'is_leaf' => true,
        ]);

        for ($b = 1; $b <= 12; $b++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $rekening->id,
                'bulan' => $b,
                'alokasi_pagu' => ($b === 2) ? 15000000.00 : 10000000.00,
                'terkunci_bulan' => 0.00,
                'realisasi_bulan' => ($b === 1) ? 6000000.00 : 0.00,
            ]);
        }

        $rekeningId = (string) $rekening->id;

        // 1. Validasi alokasi popup membaca database dan menghitung carry-over
        $data = $this->service->getPopupAllocationData($rekeningId, 2, 2);
        $this->assertEquals(4000000.00, $data['carry_over_lalu']);
        $this->assertEquals(19000000.00, $data['total_tersedia']);
        $this->assertEquals(19000000.00, $data['sisa_dapat_digunakan']);

        // 2. Reserve budget mengunci baris database secara atomik
        $this->service->reserveBudget($rekeningId, 2, 3000000.00, 2);

        $rowBulan2 = AnggaranKasBulanan::where('anggaran_rekening_id', $rekening->id)
            ->where('bulan', 2)
            ->first();
        $this->assertEquals(3000000.00, (float) $rowBulan2->terkunci_bulan);

        // 3. Overlimit reservation melempar PaguOverlimitException
        $this->expectException(PaguOverlimitException::class);
        $this->service->reserveBudget($rekeningId, 2, 25000000.00, 2);
    }

    /**
     * Test 10: Release dan settle budget mengupdate row database secara atomik.
     */
    public function test_release_dan_settle_budget_memperbarui_database_rows(): void
    {
        $unit = UnitKerja::create([
            'kode_unit' => '7.01.02',
            'nama_unit' => 'Kelurahan Sukagalih',
            'tipe' => 'KELURAHAN',
            'is_active' => true,
        ]);

        $rekening = AnggaranRekening::create([
            'unit_kerja_id' => $unit->id,
            'kode_sub_kegiatan' => '7.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'Administrasi Umum',
            'kode_rekening' => '5.1.02.01.001.00025',
            'nama_rekening' => 'Belanja Pemeliharaan',
            'pagu_murni' => 50000000.00,
            'pagu_perubahan' => 50000000.00,
            'kategori_belanja' => 'B.5',
            'is_leaf' => true,
        ]);

        for ($b = 1; $b <= 12; $b++) {
            AnggaranKasBulanan::create([
                'anggaran_rekening_id' => $rekening->id,
                'bulan' => $b,
                'alokasi_pagu' => 10000000.00,
                'terkunci_bulan' => 0.00,
                'realisasi_bulan' => 0.00,
            ]);
        }

        $rekeningId = (string) $rekening->id;

        // Reserve 4 juta
        $this->service->reserveBudget($rekeningId, 2, 4000000.00, 2);

        // Release 1.5 juta
        $this->service->releaseBudget($rekeningId, 2, 1500000.00);
        $rowAfterRelease = AnggaranKasBulanan::where('anggaran_rekening_id', $rekening->id)
            ->where('bulan', 2)
            ->first();
        $this->assertEquals(2500000.00, (float) $rowAfterRelease->terkunci_bulan);

        // Settle 2.5 juta
        $this->service->settleBudget($rekeningId, 2, 2500000.00);
        $rowAfterSettle = AnggaranKasBulanan::where('anggaran_rekening_id', $rekening->id)
            ->where('bulan', 2)
            ->first();
        $this->assertEquals(0.00, (float) $rowAfterSettle->terkunci_bulan);
        $this->assertEquals(2500000.00, (float) $rowAfterSettle->realisasi_bulan);
    }
}
