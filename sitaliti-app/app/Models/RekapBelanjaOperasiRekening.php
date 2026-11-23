<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekapBelanjaOperasiRekening extends Model
{
    use HasFactory;

    protected $table = 'rekap_belanja_operasi_rekening';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'master_rekap_id',
        'kode_rekening',
    ];

    /**
     * Pos rekap Belanja Operasi pemilik mapping kode rekening ini.
     */
    public function masterRekap(): BelongsTo
    {
        return $this->belongsTo(MasterRekapBelanjaOperasi::class, 'master_rekap_id');
    }
}
