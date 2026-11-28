<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SppdPesertaBiaya extends Model
{
    use HasFactory;

    protected $table = 'sppd_peserta_biaya';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sppd_peserta_id',
        'uraian_biaya',
        'nominal',
        'keterangan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sppd_peserta_id' => 'integer',
            'nominal' => 'decimal:2',
        ];
    }

    public function peserta(): BelongsTo
    {
        return $this->belongsTo(SppdPeserta::class, 'sppd_peserta_id');
    }
}
