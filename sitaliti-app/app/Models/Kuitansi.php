<?php

namespace App\Models;

use App\Models\Concerns\ResolvesByIdOrNumber;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Kuitansi extends Model
{
    use HasFactory;
    use ResolvesByIdOrNumber;

    protected static function numberColumn(): string
    {
        return 'nomor_kuitansi';
    }

    protected $table = 'kuitansi';

    /**
     * @var list<string>
     */
    protected $appends = [
        'sppd_foto_url',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nomor_kuitansi',
        'unit_kerja_id',
        'anggaran_rekening_id',
        'user_id',
        'pptk_user_id',
        'lurah_user_id',
        'verifikator_id',
        'bulan_anggaran',
        'nomor_bukti_toko',
        'tanggal_transaksi',
        'uraian',
        'nama_penerima',
        'npwp_penerima',
        'metode_pembayaran',
        'golongan_asn',
        'is_sewa_kendaraan',
        'is_pkp',
        'nominal_bruto',
        'total_pajak',
        'nominal_neto',
        'status',
        'qr_token',
        'catatan_pptk',
        'catatan_lurah',
        'catatan_verifikasi',
        'sppd_foto_path',
        'sppd_foto_name',
        'sppd_foto_size',
        'sppd_foto_mime',
        'tenggat_sla',
        'tanggal_submit',
        'tanggal_pptk_approve',
        'tanggal_lurah_approve',
        'tanggal_verifikasi',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bulan_anggaran' => 'integer',
            'is_sewa_kendaraan' => 'boolean',
            'is_pkp' => 'boolean',
            'tanggal_transaksi' => 'date',
            'nominal_bruto' => 'decimal:2',
            'total_pajak' => 'decimal:2',
            'nominal_neto' => 'decimal:2',
            'sppd_foto_size' => 'integer',
            'tenggat_sla' => 'datetime',
            'tanggal_submit' => 'datetime',
            'tanggal_pptk_approve' => 'datetime',
            'tanggal_lurah_approve' => 'datetime',
            'tanggal_verifikasi' => 'datetime',
        ];
    }

    /**
     * SLA terlampaui: dihitung real-time, hanya untuk kuitansi REVISI_MINOR yang lewat tenggat.
     */
    protected function isSlaBreached(): Attribute
    {
        return Attribute::get(fn (): bool => $this->status === 'REVISI_MINOR'
            && $this->tenggat_sla !== null
            && now()->gt($this->tenggat_sla));
    }

    /**
     * Sisa jam menuju tenggat SLA (negatif bila terlambat); null bila tidak berlaku.
     */
    protected function slaRemainingHours(): Attribute
    {
        return Attribute::get(function (): ?float {
            if ($this->status !== 'REVISI_MINOR' || $this->tenggat_sla === null) {
                return null;
            }

            return round(now()->floatDiffInHours($this->tenggat_sla, false), 2);
        });
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

    public function pptkUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pptk_user_id');
    }

    public function lurahUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lurah_user_id');
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

    public function spjItems(): HasMany
    {
        return $this->hasMany(PengajuanSpjItem::class, 'kuitansi_id');
    }

    public function activeSpjItem(): HasOne
    {
        return $this->hasOne(PengajuanSpjItem::class, 'kuitansi_id')->where('status_item', 'AKTIF');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(KuitansiRiwayat::class, 'kuitansi_id');
    }

    public function sppdPeserta(): HasMany
    {
        return $this->hasMany(KuitansiSppdPeserta::class, 'kuitansi_id');
    }

    /**
     * URL preview foto keberangkatan rombongan SPPD.
     */
    protected function sppdFotoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->id || ! $this->sppd_foto_path) {
                return null;
            }

            return "/sppd/photo/{$this->id}";
        });
    }
}
