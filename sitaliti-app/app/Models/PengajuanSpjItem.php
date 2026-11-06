<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanSpjItem extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_spj_item';

    protected $fillable = [
        'pengajuan_spj_id',
        'kuitansi_id',
        'no_urut_ordner',
        'status_item',
    ];

    protected function casts(): array
    {
        return [
            'no_urut_ordner' => 'integer',
        ];
    }

    public function pengajuanSpj(): BelongsTo
    {
        return $this->belongsTo(PengajuanSpj::class, 'pengajuan_spj_id');
    }

    public function kuitansi(): BelongsTo
    {
        return $this->belongsTo(Kuitansi::class, 'kuitansi_id');
    }
}
