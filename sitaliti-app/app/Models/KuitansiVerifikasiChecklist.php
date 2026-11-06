<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KuitansiVerifikasiChecklist extends Model
{
    use HasFactory;

    protected $table = 'kuitansi_verifikasi_checklist';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kuitansi_id',
        'master_checklist_item_id',
        'kuitansi_lampiran_id',
        'is_verified',
        'status_kesesuaian',
        'catatan',
        'verified_by',
        'verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function kuitansi(): BelongsTo
    {
        return $this->belongsTo(Kuitansi::class, 'kuitansi_id');
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(MasterChecklistItem::class, 'master_checklist_item_id');
    }

    public function lampiran(): BelongsTo
    {
        return $this->belongsTo(KuitansiLampiran::class, 'kuitansi_lampiran_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
