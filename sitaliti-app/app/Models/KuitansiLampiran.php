<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KuitansiLampiran extends Model
{
    use HasFactory;

    protected $table = 'kuitansi_lampiran';

    /**
     * @var list<string>
     */
    protected $appends = [
        'file_url',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kuitansi_id',
        'anggaran_rekening_id',
        'checklist_code',
        'nama_dokumen',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'file_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function kuitansi(): BelongsTo
    {
        return $this->belongsTo(Kuitansi::class, 'kuitansi_id');
    }

    public function rekening(): BelongsTo
    {
        return $this->belongsTo(AnggaranRekening::class, 'anggaran_rekening_id');
    }

    /**
     * Accessor untuk URL preview/streaming lampiran berkas.
     */
    public function getFileUrlAttribute(): ?string
    {
        if (! $this->id || ! $this->kuitansi_id) {
            return null;
        }

        return "/receipts/{$this->kuitansi_id}/attachments/{$this->id}/preview";
    }
}
