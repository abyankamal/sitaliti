<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pegawai extends Model
{
    use HasFactory;

    protected $table = 'pegawai';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'unit_kerja_id',
        'status_kepegawaian',
        'nip',
        'nik',
        'nama',
        'jabatan',
        'golongan',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_kerja_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Pegawai $pegawai) {
            $pegawai->nip = self::normalizeNip($pegawai->nip);
        });
    }

    /**
     * Menormalisasi NIP dengan menghapus spasi dan tanda hubung; nilai kosong atau '-' menjadi null.
     */
    public static function normalizeNip(?string $nip): ?string
    {
        if ($nip === null) {
            return null;
        }

        $trimmed = trim($nip);
        if ($trimmed === '' || $trimmed === '-') {
            return null;
        }

        $normalized = preg_replace('/[\s-]+/', '', $trimmed) ?? $trimmed;

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Scope hanya pegawai yang aktif.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function sppdPeserta(): HasMany
    {
        return $this->hasMany(KuitansiSppdPeserta::class, 'pegawai_id');
    }
}
