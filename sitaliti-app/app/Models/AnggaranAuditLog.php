<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnggaranAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'anggaran_audit_logs';

    protected $fillable = [
        'anggaran_rekening_id',
        'unit_kerja_id',
        'user_id',
        'actor_role',
        'action',
        'old_pagu_perubahan',
        'new_pagu_perubahan',
        'old_alokasi',
        'new_alokasi',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_pagu_perubahan' => 'decimal:2',
            'new_pagu_perubahan' => 'decimal:2',
            'old_alokasi' => 'array',
            'new_alokasi' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function rekening(): BelongsTo
    {
        return $this->belongsTo(AnggaranRekening::class, 'anggaran_rekening_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
