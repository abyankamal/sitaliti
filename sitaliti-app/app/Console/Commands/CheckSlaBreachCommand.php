<?php

namespace App\Console\Commands;

use App\Models\Kuitansi;
use App\Services\AuditTrailService;
use Illuminate\Console\Command;

class CheckSlaBreachCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitaliti:check-sla';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memindai kuitansi berstatus REVISI_MINOR yang melewati tenggat SLA dan mencatat peringatan ke audit trail';

    /**
     * Execute the console command.
     */
    public function handle(AuditTrailService $auditTrail): int
    {
        $this->info('Memulai pemindaian kepatuhan SLA kuitansi...');

        $breachedList = Kuitansi::with('unitKerja')
            ->where('status', 'REVISI_MINOR')
            ->whereNotNull('tenggat_sla')
            ->where('tenggat_sla', '<', now())
            ->get();

        if ($breachedList->isEmpty()) {
            $this->info('Tidak ditemukan kuitansi yang melanggar SLA. Seluruh antrean revisi terpantau tertib.');

            return self::SUCCESS;
        }

        $tableRows = [];
        $loggedCount = 0;

        foreach ($breachedList as $kuitansi) {
            $alreadyLogged = $kuitansi->riwayat()
                ->where('action', 'SLA_BREACHED')
                ->exists();

            if (! $alreadyLogged) {
                $auditTrail->log(
                    kuitansi: $kuitansi,
                    action: 'SLA_BREACHED',
                    statusLama: $kuitansi->status,
                    statusBaru: $kuitansi->status,
                    catatan: 'Peringatan SLA: Kuitansi telah melampaui tenggat batas revisi 2 hari kerja.',
                    user: null,
                    metadata: [
                        'tenggat_sla' => $kuitansi->tenggat_sla?->toIso8601String(),
                        'overdue_hours' => abs((float) $kuitansi->sla_remaining_hours),
                        'detected_by' => 'artisan:sitaliti:check-sla',
                    ]
                );
                $loggedCount++;
            }

            $tableRows[] = [
                $kuitansi->id,
                $kuitansi->nomor_kuitansi,
                $kuitansi->unitKerja?->nama_unit ?? '-',
                $kuitansi->tenggat_sla?->translatedFormat('d M Y H:i') ?? '-',
                abs((float) $kuitansi->sla_remaining_hours).' jam',
                $alreadyLogged ? 'Tercatat Sebelumnya' : 'Baru Dicatat',
            ];
        }

        $this->table(
            ['ID', 'Nomor Kuitansi', 'Unit Kerja', 'Tenggat SLA', 'Terlambat', 'Status Audit'],
            $tableRows
        );

        $this->warn("Ditemukan {$breachedList->count()} kuitansi melanggar SLA ({$loggedCount} peringatan baru dicatat ke riwayat audit).");

        return self::SUCCESS;
    }
}

