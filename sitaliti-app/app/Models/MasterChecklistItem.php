<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterChecklistItem extends Model
{
    use HasFactory;

    protected $table = 'master_checklist_item';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kategori_belanja',
        'kode_item',
        'nama_item',
        'deskripsi',
        'is_required',
        'urutan',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'urutan' => 'integer',
        ];
    }

    public function verifikasi(): HasMany
    {
        return $this->hasMany(KuitansiVerifikasiChecklist::class, 'master_checklist_item_id');
    }
}
