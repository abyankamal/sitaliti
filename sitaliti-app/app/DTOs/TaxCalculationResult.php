<?php

namespace App\DTOs;

readonly class TaxCalculationResult
{
    /**
     * @param  array<int, array{kode_pajak: string, nama_pajak: string, tarif: float, tipe_tarif: string, nominal: float}>  $rincianPajak
     */
    public function __construct(
        public float $nilaiBruto,
        public float $dpp,
        public float $ppn,
        public float $pbjtMamin,
        public float $pph21,
        public float $pph22,
        public float $pph23,
        public float $beaMeterai,
        public float $totalPajak,
        public float $nilaiNeto,
        public array $rincianPajak = []
    ) {}

    /**
     * Serialisasi ke format array untuk respon JSON atau persistence.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nilai_bruto' => $this->nilaiBruto,
            'dpp' => $this->dpp,
            'ppn' => $this->ppn,
            'pbjt_mamin' => $this->pbjtMamin,
            'pph21' => $this->pph21,
            'pph22' => $this->pph22,
            'pph23' => $this->pph23,
            'bea_meterai' => $this->beaMeterai,
            'total_pajak' => $this->totalPajak,
            'nilai_neto' => $this->nilaiNeto,
            'rincian_pajak' => $this->rincianPajak,
        ];
    }
}
