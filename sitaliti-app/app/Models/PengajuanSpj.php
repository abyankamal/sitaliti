<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengajuanSpj extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_spj';

    protected $fillable = [
        'nomor_bundel',
        'unit_kerja_id',
        'user_id',
        'jenis_bundel',
        'tahun_anggaran',
        'bulan_periode',
        'total_bruto',
        'total_pajak',
        'total_neto',
        'status_bundel',
        'ppk_skpd_id',
        'nomor_spp_sipd',
        'nomor_sp2d_sipd',
        'tanggal_sp2d',
        'checklist_bagian_a',
    ];

    protected function casts(): array
    {
        return [
            'tahun_anggaran' => 'integer',
            'bulan_periode' => 'integer',
            'total_bruto' => 'decimal:2',
            'total_pajak' => 'decimal:2',
            'total_neto' => 'decimal:2',
            'tanggal_sp2d' => 'date',
            'checklist_bagian_a' => 'array',
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

    public function ppk(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ppk_skpd_id');
    }

    /**
     * Mencari bundel SPJ berdasarkan ID numerik (diprioritaskan) atau nomor_bundel
     * secara aman di PostgreSQL maupun SQLite tanpa risiko SQLSTATE[22P02].
     *
     * @param  array<int, string>  $with
     */
    public static function resolveByIdOrNumber(string|int $identifier, array $with = [], bool $lockForUpdate = false): ?self
    {
        $key = (string) $identifier;

        if (preg_match('/^[1-9][0-9]*$/', $key) === 1 && filter_var($key, FILTER_VALIDATE_INT) !== false) {
            $byIdQuery = static::query()->with($with)->where('id', (int) $key);
            if ($lockForUpdate) {
                $byIdQuery->lockForUpdate();
            }

            $byId = $byIdQuery->first();
            if ($byId) {
                return $byId;
            }
        }

        $byNumberQuery = static::query()->with($with)->where('nomor_bundel', $key);
        if ($lockForUpdate) {
            $byNumberQuery->lockForUpdate();
        }

        return $byNumberQuery->first();
    }

    /**
     * @param  array<int, string>  $with
     */
    public static function resolveOrFailByIdOrNumber(string|int $identifier, array $with = [], bool $lockForUpdate = false): self
    {
        $record = static::resolveByIdOrNumber($identifier, $with, $lockForUpdate);
        if (! $record) {
            throw (new ModelNotFoundException)->setModel(static::class, [$identifier]);
        }

        return $record;
    }

    public function items(): HasMany
    {
        return $this->hasMany(PengajuanSpjItem::class, 'pengajuan_spj_id');
    }

    public function activeItems(): HasMany
    {
        return $this->hasMany(PengajuanSpjItem::class, 'pengajuan_spj_id')->where('status_item', 'AKTIF');
    }

    /**
     * Menghitung ulang total nominal kuitansi aktif di dalam bundel ini.
     */
    public function recalculateTotals(): void
    {
        $this->loadMissing(['activeItems.kuitansi']);

        $totalBruto = 0.0;
        $totalPajak = 0.0;
        $totalNeto = 0.0;

        foreach ($this->activeItems as $item) {
            if ($item->kuitansi) {
                $totalBruto += (float) $item->kuitansi->nominal_bruto;
                $totalPajak += (float) $item->kuitansi->total_pajak;
                $totalNeto += (float) $item->kuitansi->nominal_neto;
            }
        }

        $this->update([
            'total_bruto' => $totalBruto,
            'total_pajak' => $totalPajak,
            'total_neto' => $totalNeto,
        ]);
    }
}
