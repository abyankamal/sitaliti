<?php

namespace Tests\Feature\Workflow;

use App\Models\Kuitansi;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SlaMonitoringTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function make(string $status, ?Carbon $deadline): Kuitansi
    {
        return new Kuitansi(['status' => $status, 'tenggat_sla' => $deadline]);
    }

    public function test_breached_only_when_revisi_minor_and_past_deadline(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        $this->assertTrue($this->make('REVISI_MINOR', now()->subHour())->is_sla_breached);
        $this->assertFalse($this->make('REVISI_MINOR', now()->addHour())->is_sla_breached);
        $this->assertFalse($this->make('REVISI_MINOR', null)->is_sla_breached);
    }

    public function test_status_change_clears_breach_statelessly(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');

        $k = $this->make('REVISI_MINOR', now()->subDay());
        $this->assertTrue($k->is_sla_breached);

        $k->status = 'MENUNGGU_VERIFIKASI';
        $this->assertFalse($k->is_sla_breached);
        $this->assertNull($k->sla_remaining_hours);
    }

    public function test_remaining_hours_is_dynamic(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');
        $k = $this->make('REVISI_MINOR', now()->addHours(10));

        $this->assertEquals(10.0, $k->sla_remaining_hours);

        Carbon::setTestNow('2026-03-10 14:00:00');
        $this->assertEquals(8.0, $k->sla_remaining_hours);

        Carbon::setTestNow('2026-03-10 23:00:00');
        $this->assertEquals(-1.0, $k->sla_remaining_hours);
    }
}
