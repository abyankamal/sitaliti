<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterSbmItem extends Model
{
    use HasFactory;

    protected $table = 'master_sbm_item';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tahun_anggaran',
        'kategori_belanja',
        'kode_sbm',
        'uraian_standar',
        'satuan',
        'harga_maksimal',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tahun_anggaran' => 'integer',
            'harga_maksimal' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActiveForYear(Builder $query, int $tahun): Builder
    {
        return $query->where('tahun_anggaran', $tahun)->where('is_active', true);
    }
}
