<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MasterTarifPajak extends Model
{
    use HasFactory;

    protected $table = 'master_tarif_pajak';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tahun_anggaran',
        'kode_pajak',
        'nama_pajak',
        'tarif',
        'ambang_batas',
        'nominal_tetap',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tahun_anggaran' => 'integer',
            'tarif' => 'decimal:4',
            'ambang_batas' => 'decimal:2',
            'nominal_tetap' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActiveForYear(Builder $query, int $tahun): Builder
    {
        return $query->where('tahun_anggaran', $tahun)->where('is_active', true);
    }

    /**
     * Mengambil peta tarif pajak aktif per kode untuk tahun anggaran tertentu secara aman.
     *
     * @return array<string, self>
     */
    public static function getRatesMapForYear(int $tahun): array
    {
        try {
            if (! Schema::hasTable('master_tarif_pajak')) {
                return [];
            }

            return self::activeForYear($tahun)
                ->get()
                ->keyBy('kode_pajak')
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
