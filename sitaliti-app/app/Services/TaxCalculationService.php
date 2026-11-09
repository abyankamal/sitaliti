<?php

namespace App\Services;

use App\Contracts\TaxCalculationServiceInterface;
use App\DTOs\TaxCalculationResult;

class TaxCalculationService implements TaxCalculationServiceInterface
{
    /**
     * Menghitung simulasi potongan pajak otomatis berdasarkan kategori belanja, nominal bruto, dan opsi NPWP/PPh.
     * Sesuai ketentuan UU HPP, UU HKPD (PBJT 10%), dan PMK 59/2022.
     *
     * @param  string  $kategoriChecklist  Contoh: 'B1_ATK', 'B2_MAMIN', 'B3_HONOR', 'B4_PERJADIN'
     * @param  float  $nilaiBruto  Nilai kuitansi bruto
     * @param array{
     *     has_npwp?: bool,
     *     golongan_asn?: string|null,
     *     is_rekanan_badan?: bool,
     *     pph21_custom_rate?: float|null
     * } $options
     * @return array<string, mixed>
     */
    public function calculateTax(string $kategoriChecklist, float $nilaiBruto, array $options = []): array
    {
        $hasNpwp = $options['has_npwp'] ?? true;
        $golonganAsn = $options['golongan_asn'] ?? null;

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
        if ($nilaiBruto > 5000000.0) {
            $beaMeterai = 10000.0;
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
            'B.5', 'B5_MODAL' => 'B5_MODAL',
            'B.6', 'B6_LAINNYA' => 'B6_LAINNYA',
            default => $kategoriChecklist,
        };

        switch ($normalizedCategory) {
            case 'B1_ATK':
                // Belanja kena PPN. Ekstraksi DPP bebas rounding drift:
                $dpp = round($nilaiBruto / 1.11);
                $calculatedPpn = $nilaiBruto - $dpp;

                // Evaluasi ambang batas PMK 59/2022:
                // Pembayaran <= Rp 2.000.000 (termasuk PPN) dikecualikan dari pemungutan PPN & PPh 22 oleh bendahara.
                if ($nilaiBruto > 2000000.0) {
                    $ppn = $calculatedPpn;

                    // PPh 22 dihitung dari DPP: 1.5% jika NPWP, penalti 100% (3.0%) jika non-NPWP
                    $tarifPph22 = $hasNpwp ? 0.015 : 0.030;
                    $pph22 = round($tarifPph22 * $dpp);

                    $rincianPajak[] = [
                        'kode_pajak' => 'PPN_11',
                        'nama_pajak' => 'Pajak Pertambahan Nilai 11%',
                        'tarif' => 0.11,
                        'tipe_tarif' => 'PERSENTASE_DPP',
                        'nominal' => $ppn,
                    ];

                    $rincianPajak[] = [
                        'kode_pajak' => 'PPH_22',
                        'nama_pajak' => $hasNpwp ? 'PPh Pasal 22 (1.5%)' : 'PPh Pasal 22 Non-NPWP (3.0%)',
                        'tarif' => $tarifPph22,
                        'tipe_tarif' => 'PERSENTASE_DPP',
                        'nominal' => $pph22,
                    ];
                }
                break;

            case 'B2_MAMIN':
                // Pajak Barang & Jasa Tertentu (PBJT) 10% Pemda Garut (UU HKPD No. 1/2022 & Perda Garut 1/2024).
                // Mutlak bebas dari PPN pusat 11%.
                $dpp = round($nilaiBruto / 1.10);
                $pbjtMamin = $nilaiBruto - $dpp;

                $rincianPajak[] = [
                    'kode_pajak' => 'PBJT_MAMIN_10',
                    'nama_pajak' => 'PBJT Makanan dan/atau Minuman 10%',
                    'tarif' => 0.10,
                    'tipe_tarif' => 'PERSENTASE_DPP',
                    'nominal' => $pbjtMamin,
                ];
                break;

            case 'B3_HONOR':
                // Honorarium bebas PPN. DPP adalah 100% Bruto.
                $dpp = $nilaiBruto;

                // Tarif PPh 21 Final berdasarkan kepangkatan PNS (PP 80/2010):
                // - Golongan I & II: 0%
                // - Golongan III: 5%
                // - Golongan IV: 15%
                // - Non-ASN: 5%
                $tarifPph21 = match ($golonganAsn) {
                    'GOLONGAN_IV' => 0.15,
                    'GOLONGAN_III', 'NON_ASN' => 0.05,
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
                // Perjadin sewa transportasi/kendaraan dikenakan PPh 23 dari bruto/sewa:
                // 2.0% jika NPWP, penalti 100% (4.0%) jika non-NPWP
                $dpp = $nilaiBruto;
                $tarifPph23 = $hasNpwp ? 0.020 : 0.040;
                $pph23 = round($tarifPph23 * $dpp);

                $rincianPajak[] = [
                    'kode_pajak' => 'PPH_23',
                    'nama_pajak' => $hasNpwp ? 'PPh Pasal 23 Sewa (2.0%)' : 'PPh Pasal 23 Sewa Non-NPWP (4.0%)',
                    'tarif' => $tarifPph23,
                    'tipe_tarif' => 'PERSENTASE_BRUTO',
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
}
