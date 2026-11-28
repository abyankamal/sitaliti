<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SppdAssignment extends Model
{
    use HasFactory;

    protected $table = 'sppd_assignments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'unit_kerja_id',
        'user_id',
        'anggaran_rekening_id',
        'kuitansi_id',
        'nomor_surat_tugas',
        'maksud_perjalanan',
        'alat_angkut',
        'tempat_berangkat',
        'tempat_tujuan',
        'tanggal_berangkat',
        'tanggal_kembali',
        'pemberi_perintah_nama',
        'pemberi_perintah_nip',
        'pemberi_perintah_jabatan',
        'dikeluarkan_di',
        'tanggal_penetapan',
        'laporan_hasil',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_kerja_id' => 'integer',
            'user_id' => 'integer',
            'anggaran_rekening_id' => 'integer',
            'kuitansi_id' => 'integer',
            'tanggal_berangkat' => 'date',
            'tanggal_kembali' => 'date',
            'tanggal_penetapan' => 'date',
        ];
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function rekening(): BelongsTo
    {
        return $this->belongsTo(AnggaranRekening::class, 'anggaran_rekening_id');
    }

    public function kuitansi(): BelongsTo
    {
        return $this->belongsTo(Kuitansi::class, 'kuitansi_id');
    }

    public function peserta(): HasMany
    {
        return $this->hasMany(SppdPeserta::class, 'sppd_assignment_id')
            ->orderBy('urutan')
            ->orderBy('id');
    }
}
