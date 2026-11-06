<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnggaranKasBulanan extends Model
{
    use HasFactory;

    protected $table = 'anggaran_kas_bulanan';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'anggaran_rekening_id',
        'bulan',
        'alokasi_pagu',
        'terkunci_bulan',
        'realisasi_bulan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'alokasi_pagu' => 'decimal:2',
            'terkunci_bulan' => 'decimal:2',
            'realisasi_bulan' => 'decimal:2',
        ];
    }

    /**
     * Relasi ke rekening belanja induk.
     */
    public function rekening(): BelongsTo
    {
        return $this->belongsTo(AnggaranRekening::class, 'anggaran_rekening_id');
    }
}
