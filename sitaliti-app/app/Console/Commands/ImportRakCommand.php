<?php

namespace App\Console\Commands;

use App\Contracts\RakImporterServiceInterface;
use Illuminate\Console\Command;

class ImportRakCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitaliti:import-rak 
                            {file? : Path file Excel RAK (.xlsx)} 
                            {--tahun=2026 : Tahun Anggaran RAK}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mengimpor data hierarki RAK (132 rekening leaf dan alokasi kas 12 bulan) dari file Excel SIPD-RI';

    /**
     * Execute the console command.
     */
    public function handle(RakImporterServiceInterface $importer): int
    {
        $fileArg = $this->argument('file');
        $tahun = (int) $this->option('tahun');

        if (! $fileArg) {
            // Default file path
            $defaultPaths = [
                storage_path('app/rak/RAK_Belanja_7.01.0.00.0.00.41.0000 - Kecamatan Tarogong Kidul.xlsx'),
                base_path('../RAK_Belanja_7.01.0.00.0.00.41.0000 - Kecamatan Tarogong Kidul.xlsx'),
                base_path('RAK_Belanja_7.01.0.00.0.00.41.0000 - Kecamatan Tarogong Kidul.xlsx'),
            ];

            foreach ($defaultPaths as $p) {
                if (file_exists($p)) {
                    $fileArg = $p;
                    break;
                }
            }
        }

        if (! $fileArg || ! file_exists($fileArg)) {
            $this->error('File RAK tidak ditemukan. Berikan path file yang valid sebagai argumen.');

            return self::FAILURE;
        }

        $this->info("Memulai impor RAK dari file: {$fileArg}");
        $this->info("Tahun Anggaran: {$tahun}");

        $start = microtime(true);

        try {
            $result = $importer->importFromFile($fileArg, $tahun);
            $elapsed = round(microtime(true) - $start, 2);

            $this->newLine();
            $this->table(
                ['Parameter', 'Nilai'],
                [
                    ['Total Unit Kerja', $result['total_unit']],
                    ['Total Subkegiatan', $result['total_subkegiatan']],
                    ['Total Rekening Leaf', $result['total_rekening']],
                    ['Total Pagu Tahunan (Rp)', number_format($result['total_pagu_tahunan'], 2, ',', '.')],
                    ['Waktu Eksekusi', "{$elapsed} detik"],
                    ['Status', $result['status']],
                ]
            );

            $this->info('Impor RAK berhasil diselesaikan secara sempurna!');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Gagal melakukan impor RAK: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
