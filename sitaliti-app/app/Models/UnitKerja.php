<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitKerja extends Model
{
    use HasFactory;

    protected $table = 'master_unit_kerja';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kode_unit',
        'nama_unit',
        'tipe',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relasi ke seluruh rekening belanja di bawah unit kerja ini.
     */
    public function rekening(): HasMany
    {
        return $this->hasMany(AnggaranRekening::class, 'unit_kerja_id');
    }

    /**
     * Relasi ke seluruh pengguna (operator/verifikator) unit kerja ini.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'unit_kerja_id');
    }
}
