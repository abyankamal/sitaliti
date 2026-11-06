<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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

        if (User::where('email', 'test@example.com')->doesntExist()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}
