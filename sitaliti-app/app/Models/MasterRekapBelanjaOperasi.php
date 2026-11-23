<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterRekapBelanjaOperasi extends Model
{
    use HasFactory;

    protected $table = 'master_rekap_belanja_operasi';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
        'urutan',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Daftar mapping kode rekening Belanja Operasi (5.1.*) milik pos rekap ini.
     */
    public function rekeningMappings(): HasMany
    {
        return $this->hasMany(RekapBelanjaOperasiRekening::class, 'master_rekap_id');
    }
}
