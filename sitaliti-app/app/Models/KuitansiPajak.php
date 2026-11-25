<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KuitansiPajak extends Model
{
    use HasFactory;

    protected $table = 'kuitansi_pajak';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kuitansi_id',
        'kode_pajak',
        'nama_pajak',
        'tarif',
        'tipe_tarif',
        'dpp',
        'nominal',
        'kode_billing',
        'ntpn',
        'tanggal_setor',
        'bukti_setor_path',
        'ntpn_updated_by',
        'ntpn_updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tarif' => 'decimal:4',
            'dpp' => 'decimal:2',
            'nominal' => 'decimal:2',
            'tanggal_setor' => 'date',
            'ntpn_updated_at' => 'datetime',
        ];
    }

    public function isSettled(): bool
    {
        return ! blank($this->ntpn);
    }

    public function kuitansi(): BelongsTo
    {
        return $this->belongsTo(Kuitansi::class, 'kuitansi_id');
    }
}
