<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SppdPeserta extends Model
{
    use HasFactory;

    protected $table = 'sppd_peserta';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sppd_assignment_id',
        'pegawai_id',
        'urutan',
        'nomor_spd',
        'tingkat_biaya',
        'pengikut',
        'dibayar_semula',
        'keterangan_lain',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sppd_assignment_id' => 'integer',
            'pegawai_id' => 'integer',
            'urutan' => 'integer',
            'pengikut' => 'array',
            'dibayar_semula' => 'decimal:2',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SppdAssignment::class, 'sppd_assignment_id');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function biayaItems(): HasMany
    {
        return $this->hasMany(SppdPesertaBiaya::class, 'sppd_peserta_id')->orderBy('id');
    }

    /**
     * Total nominal rincian biaya milik peserta ini.
     */
    public function totalBiaya(): float
    {
        return (float) $this->biayaItems->sum('nominal');
    }
}
