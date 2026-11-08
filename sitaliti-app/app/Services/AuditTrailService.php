<?php

namespace App\Services;

use App\Models\Kuitansi;
use App\Models\KuitansiRiwayat;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditTrailService
{
    /**
     * Merekam mutasi status atau aksi finansial kuitansi ke dalam tabel audit kuitansi_riwayat.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(
        Kuitansi $kuitansi,
        string $action,
        ?string $statusLama = null,
        ?string $statusBaru = null,
        ?string $catatan = null,
        ?User $user = null,
        ?array $metadata = null,
        ?string $ipAddress = null
    ): KuitansiRiwayat {
        $actor = $user ?? Auth::user();
        $ip = $ipAddress ?? Request::ip();

        return KuitansiRiwayat::create([
            'kuitansi_id' => $kuitansi->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'status_lama' => $statusLama,
            'status_baru' => $statusBaru,
            'catatan' => $catatan,
            'metadata' => $metadata,
            'ip_address' => $ip,
        ]);
    }
}

