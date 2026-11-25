<?php

namespace App\Services;

use App\Contracts\TaxCalculationServiceInterface;
use App\DTOs\TaxCalculationResult;
use App\Models\MasterTarifPajak;

class TaxCalculationService implements TaxCalculationServiceInterface
{
    /**
     * Menghitung simulasi potongan pajak otomatis berdasarkan kategori belanja, nominal bruto, dan opsi NPWP/PPh.
     * Sesuai ketentuan UU HPP, UU HKPD (PBJT 10%), dan PMK 59/2022.
     *
     * @param  string  $kategoriChecklist  Contoh: 'B1_ATK', 'B2_MAMIN', 'B3_HONOR', 'B4_PERJADIN', 'B5_PEMELIHARAAN', 'B6_MODAL'
     * @param  float  $nilaiBruto  Nilai kuitansi bruto
     * @param array{
     *     has_npwp?: bool,
     *     golongan_asn?: string|null,
     *     is_rekanan_badan?: bool,
     *     is_sewa_kendaraan?: bool,
     *     is_pkp?: bool,
     *     pph21_custom_rate?: float|null
     * } $options
     * @return array<string, mixed>
     */
    public function calculateTax(string $kategoriChecklist, float $nilaiBruto, array $options = []): array
    {
        $hasNpwp = (bool) ($options['has_npwp'] ?? true);
        $golonganAsn = $options['golongan_asn'] ?? null;
        $isSewaKendaraan = (bool) ($options['is_sewa_kendaraan'] ?? false);
        $isPkp = (bool) ($options['is_pkp'] ?? true);
        $tahun = (int) ($options['tahun_anggaran'] ?? date('Y'));
        $rates = MasterTarifPajak::getRatesMapForYear($tahun);

        $dpp = 0.0;
        $ppn = 0.0;
        $pbjtMamin = 0.0;
        $pph21 = 0.0;
        $pph22 = 0.0;
        $pph23 = 0.0;
        $beaMeterai = 0.0;
        $rincianPajak = [];

        // 1. Ambang batas Bea Meterai flat Rp 10.000 (> Rp 5.000.000) sesuai UU No. 10/2020
        // Bea meterai adalah pajak dokumen fisik, dicatat tetapi TIDAK mengurangi nilai neto ke rekanan.
        $meteraiThreshold = $this->thresholdValue($rates, 'BEA_METERAI', 5000000.0);
        if ($nilaiBruto > $meteraiThreshold) {
            $beaMeterai = $this->fixedNominalValue($rates, 'BEA_METERAI', 10000.0);
            $rincianPajak[] = [
                'kode_pajak' => 'BEA_METERAI',
                'nama_pajak' => 'Bea Meterai Lunas',
                'tarif' => 0.0,
                'tipe_tarif' => 'FIXED',
                'nominal' => $beaMeterai,
                'dpp' => 0.0,
            ];
        }

        // Normalisasi kode kategori belanja format RAK (B.1 s.d. B.6) ke kode perhitungan internal
        $normalizedCategory = match ($kategoriChecklist) {
            'B.1', 'B1_ATK' => 'B1_ATK',
            'B.2', 'B2_MAMIN' => 'B2_MAMIN',
            'B.3', 'B3_HONOR' => 'B3_HONOR',
            'B.4', 'B4_PERJADIN' => 'B4_PERJADIN',
            'B.5', 'B5_PEMELIHARAAN', 'B5_MODAL' => 'B5_PEMELIHARAAN',
            'B.6', 'B6_MODAL', 'B6_LAINNYA' => 'B6_MODAL',
            default => $kategoriChecklist,
        };

        switch ($normalizedCategory) {
            case 'B1_ATK':
            case 'B6_MODAL':
                [$dpp, $ppn, $pph22, $goodsRincian] = $this->calculateGoodsPurchaseTax($nilaiBruto, $hasNpwp, $rates);
                $rincianPajak = array_merge($rincianPajak, $goodsRincian);
                break;

            case 'B2_MAMIN':
                // Pajak Barang & Jasa Tertentu (PBJT) 10% Pemda Garut (UU HKPD No. 1/2022 & Perda Garut 1/2024).
                // Mutlak bebas dari PPN pusat.
                $pbjtRate = $this->rateValue($rates, 'PBJT_MAMIN_10', 0.10);
                $dpp = round($nilaiBruto / (1 + $pbjtRate));
                $pbjtMamin = $nilaiBruto - $dpp;

                $rincianPajak[] = [
                    'kode_pajak' => 'PBJT_MAMIN_10',
                    'nama_pajak' => 'PBJT Makanan dan/atau Minuman '.($pbjtRate * 100).'%',
                    'tarif' => $pbjtRate,
                    'tipe_tarif' => 'PERSENTASE_DPP',
                    'nominal' => $pbjtMamin,
                ];
                break;

            case 'B3_HONOR':
                // Honorarium bebas PPN. DPP adalah 100% Bruto.
                $dpp = $nilaiBruto;

                $tarifPph21 = match ($golonganAsn) {
                    'GOLONGAN_IV' => $this->rateValue($rates, 'PPH21_GOL_IV', 0.15),
                    'GOLONGAN_III' => $this->rateValue($rates, 'PPH21_GOL_III', 0.05),
                    'NON_ASN' => $this->rateValue($rates, 'PPH21_NON_ASN', 0.025),
                    default => 0.0,
                };

                if (isset($options['pph21_custom_rate']) && $options['pph21_custom_rate'] !== null) {
                    $tarifPph21 = (float) $options['pph21_custom_rate'];
                }

                $pph21 = round($tarifPph21 * $dpp);

                if ($pph21 > 0) {
                    $rincianPajak[] = [
                        'kode_pajak' => 'PPH_21',
                        'nama_pajak' => 'PPh Pasal 21 Final',
                        'tarif' => $tarifPph21,
                        'tipe_tarif' => 'PERSENTASE_BRUTO',
                        'nominal' => $pph21,
                    ];
                }
                break;

            case 'B4_PERJADIN':
                // SPPD ASN biasa (uang harian/tiket/penginapan) bebas pajak (0%).
                // Hanya memotong PPh 23 (2% NPWP / 4% Non-NPWP) jika termasuk sewa kendaraan / jasa pihak ketiga.
                $dpp = $nilaiBruto;
                if ($isSewaKendaraan) {
                    $tarifPph23 = $hasNpwp
                        ? $this->rateValue($rates, 'PPH23_NPWP', 0.020)
                        : $this->rateValue($rates, 'PPH23_NON_NPWP', 0.040);
                    $pph23 = round($tarifPph23 * $dpp);

                    $rincianPajak[] = [
                        'kode_pajak' => 'PPH_23',
                        'nama_pajak' => $hasNpwp ? 'PPh Pasal 23 Sewa ('.($tarifPph23 * 100).'%)' : 'PPh Pasal 23 Sewa Non-NPWP ('.($tarifPph23 * 100).'%)',
                        'tarif' => $tarifPph23,
                        'tipe_tarif' => 'PERSENTASE_BRUTO',
                        'nominal' => $pph23,
                    ];
                }
                break;

            case 'B5_PEMELIHARAAN':
                // Jasa pemeliharaan: PPh 23 (2% NPWP / 4% Non-NPWP) dari DPP,
                // ditambah PPN jika pembayaran > ambang batas dan rekanan PKP (PMK 59/2022).
                $ppnThreshold = $this->thresholdValue($rates, 'PPN_11', 2000000.0);
                $ppnRate = $this->rateValue($rates, 'PPN_11', 0.11);
                if ($nilaiBruto > $ppnThreshold && $isPkp) {
                    $dpp = round($nilaiBruto / (1 + $ppnRate));
                    $ppn = $nilaiBruto - $dpp;

                    $rincianPajak[] = [
                        'kode_pajak' => 'PPN_11',
                        'nama_pajak' => 'Pajak Pertambahan Nilai '.($ppnRate * 100).'%',
                        'tarif' => $ppnRate,
                        'tipe_tarif' => 'PERSENTASE_DPP',
                        'nominal' => $ppn,
                    ];
                } else {
                    $dpp = $nilaiBruto;
                }

                $tarifPph23 = $hasNpwp
                    ? $this->rateValue($rates, 'PPH23_NPWP', 0.020)
                    : $this->rateValue($rates, 'PPH23_NON_NPWP', 0.040);
                $pph23 = round($tarifPph23 * $dpp);

                $rincianPajak[] = [
                    'kode_pajak' => 'PPH_23',
                    'nama_pajak' => $hasNpwp ? 'PPh Pasal 23 Jasa ('.($tarifPph23 * 100).'%)' : 'PPh Pasal 23 Jasa Non-NPWP ('.($tarifPph23 * 100).'%)',
                    'tarif' => $tarifPph23,
                    'tipe_tarif' => 'PERSENTASE_DPP',
                    'nominal' => $pph23,
                ];
                break;

            default:
                $dpp = $nilaiBruto;
                break;
        }

        // Total pajak yang dipungut/dipotong dari hak pembayaran rekanan
        $totalPajak = $ppn + $pbjtMamin + $pph21 + $pph22 + $pph23;
        $nilaiNeto = $nilaiBruto - $totalPajak;

        $resultDTO = new TaxCalculationResult(
            nilaiBruto: $nilaiBruto,
            dpp: $dpp,
            ppn: $ppn,
            pbjtMamin: $pbjtMamin,
            pph21: $pph21,
            pph22: $pph22,
            pph23: $pph23,
            beaMeterai: $beaMeterai,
            totalPajak: $totalPajak,
            nilaiNeto: $nilaiNeto,
            rincianPajak: $rincianPajak
        );

        return $resultDTO->toArray();
    }

    /**
     * Memvalidasi kepatuhan pajak:
     * - Keseimbangan matematis: Bruto - Total Pajak = Neto
     * - Wajib meterai untuk transaksi > Rp 5.000.000
     * - Pemisahan mutlak: PPN 11% dan PBJT 10% tidak boleh dipungut bersamaan
     *
     * @param  array<string, mixed>  $taxDetails
     */
    public function validateTaxCompliance(float $nilaiBruto, array $taxDetails): bool
    {
        $totalPajak = $taxDetails['total_pajak'] ?? 0.0;
        $nilaiNeto = $taxDetails['nilai_neto'] ?? 0.0;
        $ppn = $taxDetails['ppn'] ?? 0.0;
        $pbjtMamin = $taxDetails['pbjt_mamin'] ?? 0.0;
        $beaMeterai = $taxDetails['bea_meterai'] ?? 0.0;

        // 1. Keseimbangan matematis mutlak
        if (round($nilaiNeto + $totalPajak, 2) !== round($nilaiBruto, 2)) {
            return false;
        }

        // 2. Pemisahan mutlak PBJT vs PPN
        if ($ppn > 0.0 && $pbjtMamin > 0.0) {
            return false;
        }

        // 3. Verifikasi kewajiban meterai
        if ($nilaiBruto > 5000000.0 && $beaMeterai < 10000.0) {
            return false;
        }

        return true;
    }

    /**
     * Menghitung PPN dan PPh 22 untuk belanja barang/modal (B.1 & B.6) sesuai PMK 59/2022 & tarif tahun anggaran.
     *
     * @param  array<string, MasterTarifPajak>  $rates
     * @return array{0: float, 1: float, 2: float, 3: array<int, array<string, mixed>>}
     */
    private function calculateGoodsPurchaseTax(float $nilaiBruto, bool $hasNpwp, array $rates = []): array
    {
        $ppnRate = $this->rateValue($rates, 'PPN_11', 0.11);
        $ppnThreshold = $this->thresholdValue($rates, 'PPN_11', 2000000.0);
        $dpp = round($nilaiBruto / (1 + $ppnRate));
        $ppn = 0.0;
        $pph22 = 0.0;
        $rincian = [];

        if ($nilaiBruto > $ppnThreshold) {
            $ppn = $nilaiBruto - $dpp;
            $tarifPph22 = $hasNpwp
                ? $this->rateValue($rates, 'PPH22_NPWP', 0.015)
                : $this->rateValue($rates, 'PPH22_NON_NPWP', 0.030);
            $pph22 = round($tarifPph22 * $dpp);

            $rincian[] = [
                'kode_pajak' => 'PPN_11',
                'nama_pajak' => 'Pajak Pertambahan Nilai '.($ppnRate * 100).'%',
                'tarif' => $ppnRate,
                'tipe_tarif' => 'PERSENTASE_DPP',
                'nominal' => $ppn,
            ];

            $rincian[] = [
                'kode_pajak' => 'PPH_22',
                'nama_pajak' => $hasNpwp ? 'PPh Pasal 22 ('.($tarifPph22 * 100).'%)' : 'PPh Pasal 22 Non-NPWP ('.($tarifPph22 * 100).'%)',
                'tarif' => $tarifPph22,
                'tipe_tarif' => 'PERSENTASE_DPP',
                'nominal' => $pph22,
            ];
        }

        return [$dpp, $ppn, $pph22, $rincian];
    }

    /**
     * @param  array<string, MasterTarifPajak>  $rates
     */
    private function rateValue(array $rates, string $kode, float $default): float
    {
        return isset($rates[$kode]) ? (float) $rates[$kode]->tarif : $default;
    }

    /**
     * @param  array<string, MasterTarifPajak>  $rates
     */
    private function thresholdValue(array $rates, string $kode, float $default): float
    {
        return isset($rates[$kode]) && $rates[$kode]->ambang_batas !== null
            ? (float) $rates[$kode]->ambang_batas
            : $default;
    }

    /**
     * @param  array<string, MasterTarifPajak>  $rates
     */
    private function fixedNominalValue(array $rates, string $kode, float $default): float
    {
        return isset($rates[$kode]) && $rates[$kode]->nominal_tetap !== null
            ? (float) $rates[$kode]->nominal_tetap
            : $default;
    }
}
