<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KuitansiRiwayat extends Model
{
    use HasFactory;

    protected $table = 'kuitansi_riwayat';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kuitansi_id',
        'user_id',
        'action',
        'status_lama',
        'status_baru',
        'catatan',
        'metadata',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function kuitansi(): BelongsTo
    {
        return $this->belongsTo(Kuitansi::class, 'kuitansi_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

