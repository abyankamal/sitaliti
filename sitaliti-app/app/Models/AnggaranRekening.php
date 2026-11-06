<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnggaranRekening extends Model
{
    use HasFactory;

    protected $table = 'anggaran_rekening';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'unit_kerja_id',
        'kode_sub_kegiatan',
        'nama_sub_kegiatan',
        'kode_rekening',
        'nama_rekening',
        'pagu_murni',
        'pagu_perubahan',
        'kategori_belanja',
        'is_leaf',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pagu_murni' => 'decimal:2',
            'pagu_perubahan' => 'decimal:2',
            'is_leaf' => 'boolean',
        ];
    }

    /**
     * Relasi ke unit kerja pemilik rekening anggaran.
     */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    /**
     * Relasi ke alokasi jadwal kas 12 bulan RAK.
     */
    public function kasBulanan(): HasMany
    {
        return $this->hasMany(AnggaranKasBulanan::class, 'anggaran_rekening_id');
    }
}

