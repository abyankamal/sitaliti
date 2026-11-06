<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KuitansiItem extends Model
{
    use HasFactory;

    protected $table = 'kuitansi_item';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kuitansi_id',
        'nama_barang',
        'volume',
        'satuan',
        'harga_satuan',
        'total_harga',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'volume' => 'decimal:2',
            'harga_satuan' => 'decimal:2',
            'total_harga' => 'decimal:2',
        ];
    }

    public function kuitansi(): BelongsTo
    {
        return $this->belongsTo(Kuitansi::class, 'kuitansi_id');
    }
}

