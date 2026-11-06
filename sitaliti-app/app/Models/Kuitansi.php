<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kuitansi extends Model
{
    use HasFactory;

    protected $table = 'kuitansi';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nomor_kuitansi',
        'unit_kerja_id',
        'anggaran_rekening_id',
        'user_id',
        'verifikator_id',
        'bulan_anggaran',
        'nomor_bukti_toko',
        'tanggal_transaksi',
        'uraian',
        'nama_penerima',
        'npwp_penerima',
        'metode_pembayaran',
        'nominal_bruto',
        'total_pajak',
        'nominal_neto',
        'status',
        'qr_token',
        'catatan_verifikasi',
        'tenggat_sla',
        'tanggal_submit',
        'tanggal_verifikasi',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bulan_anggaran' => 'integer',
            'tanggal_transaksi' => 'date',
            'nominal_bruto' => 'decimal:2',
            'total_pajak' => 'decimal:2',
            'nominal_neto' => 'decimal:2',
            'tenggat_sla' => 'datetime',
            'tanggal_submit' => 'datetime',
            'tanggal_verifikasi' => 'datetime',
        ];
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function rekening(): BelongsTo
    {
        return $this->belongsTo(AnggaranRekening::class, 'anggaran_rekening_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifikator_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KuitansiItem::class, 'kuitansi_id');
    }

    public function pajak(): HasMany
    {
        return $this->hasMany(KuitansiPajak::class, 'kuitansi_id');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(KuitansiLampiran::class, 'kuitansi_id');
    }

    public function checklistVerifikasi(): HasMany
    {
        return $this->hasMany(KuitansiVerifikasiChecklist::class, 'kuitansi_id');
    }
}
