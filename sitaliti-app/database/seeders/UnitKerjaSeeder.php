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
                'is_active' => true,
            ],
            [
                'kode_unit' => '7.01.0.00.0.00.41.0001',
                'nama_unit' => 'Kelurahan Pataruman',
                'tipe' => 'KELURAHAN',
                'is_active' => true,
            ],
            [
                'kode_unit' => '7.01.0.00.0.00.41.0002',
                'nama_unit' => 'Kelurahan Sukagalih',
                'tipe' => 'KELURAHAN',
                'is_active' => true,
            ],
            [
                'kode_unit' => '7.01.0.00.0.00.41.0006',
                'nama_unit' => 'Kelurahan Jayawaras',
                'tipe' => 'KELURAHAN',
                'is_active' => true,
            ],
            [
                'kode_unit' => '7.01.0.00.0.00.41.0009',
                'nama_unit' => 'Kelurahan Sukakarya',
                'tipe' => 'KELURAHAN',
                'is_active' => true,
            ],
            [
                'kode_unit' => '7.01.0.00.0.00.41.0012',
                'nama_unit' => 'Kelurahan Sukajaya',
                'tipe' => 'KELURAHAN',
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
