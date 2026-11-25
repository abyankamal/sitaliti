<?php

namespace Database\Seeders;

use App\Models\MasterSbmItem;
use App\Models\MasterTarifPajak;
use App\Models\PengaturanKepatuhan;
use Illuminate\Database\Seeder;

class ComplianceRulesSeeder extends Seeder
{
    public function run(): void
    {
        $tahun = (int) date('Y');

        $taxRates = [
            [
                'kode_pajak' => 'PPN_11',
                'nama_pajak' => 'Pajak Pertambahan Nilai (PPN)',
                'tarif' => 0.1100,
                'ambang_batas' => 2000000.00,
                'nominal_tetap' => null,
            ],
            [
                'kode_pajak' => 'PBJT_MAMIN_10',
                'nama_pajak' => 'PBJT Makanan dan/atau Minuman 10% (Perda Garut 1/2024)',
                'tarif' => 0.1000,
                'ambang_batas' => null,
                'nominal_tetap' => null,
            ],
            [
                'kode_pajak' => 'PPH21_GOL_III',
                'nama_pajak' => 'PPh Pasal 21 Final ASN Golongan III',
                'tarif' => 0.0500,
                'ambang_batas' => null,
                'nominal_tetap' => null,
            ],
            [
                'kode_pajak' => 'PPH21_GOL_IV',
                'nama_pajak' => 'PPh Pasal 21 Final ASN Golongan IV',
                'tarif' => 0.1500,
                'ambang_batas' => null,
                'nominal_tetap' => null,
            ],
            [
                'kode_pajak' => 'PPH21_NON_ASN',
                'nama_pajak' => 'PPh Pasal 21 Non-ASN Efektif (PMK 168/2023)',
                'tarif' => 0.0250,
                'ambang_batas' => null,
                'nominal_tetap' => null,
            ],
            [
                'kode_pajak' => 'PPH22_NPWP',
                'nama_pajak' => 'PPh Pasal 22 Belanja Barang (Ber-NPWP)',
                'tarif' => 0.0150,
                'ambang_batas' => 2000000.00,
                'nominal_tetap' => null,
            ],
            [
                'kode_pajak' => 'PPH22_NON_NPWP',
                'nama_pajak' => 'PPh Pasal 22 Belanja Barang (Non-NPWP)',
                'tarif' => 0.0300,
                'ambang_batas' => 2000000.00,
                'nominal_tetap' => null,
            ],
            [
                'kode_pajak' => 'PPH23_NPWP',
                'nama_pajak' => 'PPh Pasal 23 Jasa/Sewa (Ber-NPWP)',
                'tarif' => 0.0200,
                'ambang_batas' => null,
                'nominal_tetap' => null,
            ],
            [
                'kode_pajak' => 'PPH23_NON_NPWP',
                'nama_pajak' => 'PPh Pasal 23 Jasa/Sewa (Non-NPWP)',
                'tarif' => 0.0400,
                'ambang_batas' => null,
                'nominal_tetap' => null,
            ],
            [
                'kode_pajak' => 'BEA_METERAI',
                'nama_pajak' => 'Bea Meterai Lunas (UU No. 10/2020)',
                'tarif' => 0.0000,
                'ambang_batas' => 5000000.00,
                'nominal_tetap' => 10000.00,
            ],
        ];

        foreach ($taxRates as $rate) {
            MasterTarifPajak::updateOrCreate(
                [
                    'tahun_anggaran' => $tahun,
                    'kode_pajak' => $rate['kode_pajak'],
                ],
                [
                    'nama_pajak' => $rate['nama_pajak'],
                    'tarif' => $rate['tarif'],
                    'ambang_batas' => $rate['ambang_batas'],
                    'nominal_tetap' => $rate['nominal_tetap'],
                    'is_active' => true,
                ]
            );
        }

        $sbmItems = [
            [
                'kategori_belanja' => 'B1_ATK',
                'kode_sbm' => 'SBM-ATK-001',
                'uraian_standar' => 'Kertas HVS F4/A4 70gr',
                'satuan' => 'Rim',
                'harga_maksimal' => 65000.00,
            ],
            [
                'kategori_belanja' => 'B2_MAMIN',
                'kode_sbm' => 'SBM-MAMIN-001',
                'uraian_standar' => 'Makanan dan Minuman Rapat Biasa (Nasi Kotak)',
                'satuan' => 'Orang/Kali',
                'harga_maksimal' => 45000.00,
            ],
            [
                'kategori_belanja' => 'B3_HONOR',
                'kode_sbm' => 'SBM-HONOR-001',
                'uraian_standar' => 'Honorarium Narasumber Non-ASN',
                'satuan' => 'OJ',
                'harga_maksimal' => 900000.00,
            ],
        ];

        foreach ($sbmItems as $item) {
            MasterSbmItem::updateOrCreate(
                [
                    'tahun_anggaran' => $tahun,
                    'kode_sbm' => $item['kode_sbm'],
                ],
                [
                    'kategori_belanja' => $item['kategori_belanja'],
                    'uraian_standar' => $item['uraian_standar'],
                    'satuan' => $item['satuan'],
                    'harga_maksimal' => $item['harga_maksimal'],
                    'is_active' => true,
                ]
            );
        }

        $settings = [
            'ntpn_gate_spp_gu' => [
                'value' => 'true',
                'description' => 'Wajibkan Kode Billing & NTPN pada seluruh kuitansi berpajak sebelum pengesahan PPK-SKPD untuk bundel SPP_GU.',
            ],
            'ntpn_gate_lpj_bpp' => [
                'value' => 'false',
                'description' => 'Wajibkan Kode Billing & NTPN sebelum pengesahan PPK-SKPD untuk bundel LPJ_BPP.',
            ],
            'sbm_soft_warning_enabled' => [
                'value' => 'true',
                'description' => 'Aktifkan peringatan non-blocking (soft-warning) jika harga satuan melebihi batas SBM/SSH.',
            ],
        ];

        foreach ($settings as $key => $cfg) {
            PengaturanKepatuhan::firstOrCreate(
                ['key' => $key],
                [
                    'value' => $cfg['value'],
                    'description' => $cfg['description'],
                ]
            );
        }
    }
}
