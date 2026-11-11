<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PptkSubkegiatanAssignment extends Model
{
    use HasFactory;

    protected $table = 'pptk_subkegiatan_assignments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'unit_kerja_id',
        'kode_sub_kegiatan',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }
}
