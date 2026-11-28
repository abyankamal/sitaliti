<?php

namespace Database\Seeders;

use App\Models\UnitKerja;
use Illuminate\Database\Seeder;

class UnitKerjaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            [
                'kode_unit' => '7.01.0.00.0.00.41.0000',
                'nama_unit' => 'Kecamatan Tarogong Kidul',
                'tipe' => 'KECAMATAN',
                'kop_alamat' => 'Jalan RSU dr. Slamet No.8',
                'kop_telepon_email' => 'email : tarogongkidul.kecamatan@gmail.com',
                'kop_kode_pos' => '44150',
                'pejabat_nama' => 'AHMAD MAWARDI,AP,.SE',
                'pejabat_nip' => '197005101990091001',
                'pejabat_jabatan' => 'Camat Tarogong Kidul',
                'bendahara_nama' => 'YADI MULYADI',
                'bendahara_nip' => '197402062010011006',
                'is_active' => true,
            ],
            [
                'kode_unit' => '7.01.0.00.0.00.41.0001',
                'nama_unit' => 'Kelurahan Pataruman',
                'tipe' => 'KELURAHAN',
                'kop_alamat' => 'Kecamatan Tarogong Kidul, Kabupaten Garut',
                'kop_telepon_email' => null,
                'kop_kode_pos' => '44151',
                'pejabat_nama' => null,
                'pejabat_nip' => null,
                'pejabat_jabatan' => 'Lurah Pataruman',
                'bendahara_nama' => null,
                'bendahara_nip' => null,
                'is_active' => true,
            ],
            [
                'kode_unit' => '7.01.0.00.0.00.41.0002',
                'nama_unit' => 'Kelurahan Sukagalih',
                'tipe' => 'KELURAHAN',
                'kop_alamat' => 'Kecamatan Tarogong Kidul, Kabupaten Garut',
                'kop_telepon_email' => null,
                'kop_kode_pos' => '44151',
                'pejabat_nama' => null,
                'pejabat_nip' => null,
                'pejabat_jabatan' => 'Lurah Sukagalih',
                'bendahara_nama' => null,
                'bendahara_nip' => null,
                'is_active' => true,
            ],
            [
                'kode_unit' => '7.01.0.00.0.00.41.0006',
                'nama_unit' => 'Kelurahan Jayawaras',
                'tipe' => 'KELURAHAN',
                'kop_alamat' => 'JL. Gordah No 40',
                'kop_telepon_email' => 'Telp.(0262)2244016 Garut',
                'kop_kode_pos' => '44151',
                'pejabat_nama' => 'YOYO,S.IP',
                'pejabat_nip' => '197503132010011007',
                'pejabat_jabatan' => 'Lurah Jayawaras',
                'bendahara_nama' => null,
                'bendahara_nip' => null,
                'is_active' => true,
            ],
            [
                'kode_unit' => '7.01.0.00.0.00.41.0009',
                'nama_unit' => 'Kelurahan Sukakarya',
                'tipe' => 'KELURAHAN',
                'kop_alamat' => 'Kecamatan Tarogong Kidul, Kabupaten Garut',
                'kop_telepon_email' => null,
                'kop_kode_pos' => '44151',
                'pejabat_nama' => null,
                'pejabat_nip' => null,
                'pejabat_jabatan' => 'Lurah Sukakarya',
                'bendahara_nama' => null,
                'bendahara_nip' => null,
                'is_active' => true,
            ],
            [
                'kode_unit' => '7.01.0.00.0.00.41.0012',
                'nama_unit' => 'Kelurahan Sukajaya',
                'tipe' => 'KELURAHAN',
                'kop_alamat' => 'Kecamatan Tarogong Kidul, Kabupaten Garut',
                'kop_telepon_email' => null,
                'kop_kode_pos' => '44151',
                'pejabat_nama' => null,
                'pejabat_nip' => null,
                'pejabat_jabatan' => 'Lurah Sukajaya',
                'bendahara_nama' => null,
                'bendahara_nip' => null,
                'is_active' => true,
            ],
        ];

        foreach ($units as $unit) {
            UnitKerja::updateOrCreate(
                ['kode_unit' => $unit['kode_unit']],
                $unit
            );
        }
    }
}
