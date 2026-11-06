<?php

namespace App\DTOs;

readonly class BudgetContextData
{
    public function __construct(
        public string $rekeningId,
        public int $selectedMonth,
        public int $activeSystemMonth,
        public bool $isFutureMonth,
        public float $paguMurniBulan,
        public float $carryOverLalu,
        public float $totalTersedia,
        public float $terkunciKomitmen,
        public float $sisaDapatDigunakan,
        public string $statusLock
    ) {}

    public function toArray(): array
    {
        return [
            'rekening_id' => $this->rekeningId,
            'selected_month' => $this->selectedMonth,
            'active_system_month' => $this->activeSystemMonth,
            'is_future_month' => $this->isFutureMonth,
            'pagu_murni_bulan' => $this->paguMurniBulan,
            'carry_over_lalu' => $this->carryOverLalu,
            'total_tersedia' => $this->totalTersedia,
            'terkunci_komitmen' => $this->terkunciKomitmen,
            'sisa_dapat_digunakan' => $this->sisaDapatDigunakan,
            'status_lock' => $this->statusLock,
        ];
    }
}
