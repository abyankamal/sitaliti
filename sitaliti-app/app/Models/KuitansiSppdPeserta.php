<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KuitansiSppdPeserta extends Model
{
    use HasFactory;

    protected $table = 'kuitansi_sppd_peserta';

    /**
     * @var list<string>
     */
    protected $appends = [
        'foto_url',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kuitansi_id',
        'pegawai_id',
        'tanggal_berangkat',
        'tanggal_pulang',
        'tujuan',
        'foto_path',
        'foto_name',
        'foto_size',
        'foto_mime',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kuitansi_id' => 'integer',
            'pegawai_id' => 'integer',
            'tanggal_berangkat' => 'date',
            'tanggal_pulang' => 'date',
            'foto_size' => 'integer',
        ];
    }

    public function kuitansi(): BelongsTo
    {
        return $this->belongsTo(Kuitansi::class, 'kuitansi_id');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    /**
     * URL preview foto keberangkatan khusus milik peserta ini (tanpa lazy-load relasi kuitansi).
     */
    protected function fotoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->id || ! $this->kuitansi_id || ! $this->foto_path) {
                return null;
            }

            return "/sppd/photo/{$this->kuitansi_id}/{$this->id}";
        });
    }
}
