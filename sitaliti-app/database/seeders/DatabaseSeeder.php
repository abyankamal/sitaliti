<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UnitKerjaSeeder::class,
            MasterChecklistSeeder::class,
        ]);

        // Otomatis jalankan import RAK jika file tersedia
        $rakPath = storage_path('app/rak/RAK_Belanja_7.01.0.00.0.00.41.0000 - Kecamatan Tarogong Kidul.xlsx');
        if (file_exists($rakPath)) {
            $this->command->call('sitaliti:import-rak', ['file' => $rakPath]);
        }

        // Akun Resmi Percontohan Sesuai Struktur Brief SI-TALITI v2.0
        $officialUsers = [
            [
                'name' => 'Operator BPP Kecamatan',
                'email' => 'operator@tarkid.garutkab.go.id',
                'role' => 'OPERATOR',
            ],
            [
                'name' => 'Pejabat Pelaksana Teknis Kegiatan (PPTK)',
                'email' => 'pptk@tarkid.garutkab.go.id',
                'role' => 'PPTK',
            ],
            [
                'name' => 'Tim Verifikator Akuntabilitas',
                'email' => 'verifikator@tarkid.garutkab.go.id',
                'role' => 'VERIFIKATOR',
            ],
            [
                'name' => 'Camat Tarogong Kidul (KPA)',
                'email' => 'camat@tarkid.garutkab.go.id',
                'role' => 'KPA',
            ],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'role' => 'OPERATOR',
            ],
        ];

        foreach ($officialUsers as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                ]
            );
        }
    }
}
