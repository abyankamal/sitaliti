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
        'nip',
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
            if (is_string($pegawai->nip)) {
                $pegawai->nip = self::normalizeNip($pegawai->nip);
            }
        });
    }

    /**
     * Menormalisasi NIP/NIK dengan menghapus spasi dan tanda hubung agar unik konsisten.
     */
    public static function normalizeNip(string $nip): string
    {
        $trimmed = trim($nip);

        return preg_replace('/[\s-]+/', '', $trimmed) ?? $trimmed;
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
