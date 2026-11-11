<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Boot the model.
     * Secara otomatis menyinkronkan kolom role string dengan Spatie role.
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            $user->syncSpatieRole();
        });

        static::updated(function (User $user) {
            if ($user->wasChanged('role')) {
                $user->syncSpatieRole();
            }
        });
    }

    /**
     * Definisi default permissions per role.
     *
     * @return list<string>
     */
    public static function defaultRolePermissions(string $role): array
    {
        return match ($role) {
            'OPERATOR' => [
                'kuitansi.view',
                'kuitansi.create',
                'kuitansi.edit',
                'kuitansi.submit_pptk',
            ],
            'BPP' => [
                'kuitansi.view',
                'bundle.view',
                'bundle.create',
                'bundle.unbundle',
                'bundle.record_sp2d',
            ],
            'PPTK' => [
                'kuitansi.view',
                'kuitansi.pptk_approve',
                'kuitansi.pptk_reject',
                'verification.queue',
                'verification.audit',
            ],
            'VERIFIKATOR' => [
                'kuitansi.view',
                'verification.queue',
                'verification.audit',
                'verification.process_action',
                'verification.generate_qr',
            ],
            'LURAH' => [
                'kuitansi.view',
                'kuitansi.lurah_approve',
                'kuitansi.lurah_reject',
                'bundle.view',
                'budget.view',
                'verification.queue',
                'verification.audit',
            ],
            'KPA' => [
                'kuitansi.view',
                'bundle.view',
                'bundle.create',
                'bundle.unbundle',
                'bundle.record_sp2d',
                'dashboard.view_all',
            ],
            default => [],
        };
    }

    /**
     * Sinkronisasi nilai kolom 'role' ke Spatie role pivot table.
     */
    public function syncSpatieRole(): void
    {
        if (! empty($this->role)) {
            // Pastikan role ada di tabel roles agar tidak melempar RoleDoesNotExist
            $role = Role::firstOrCreate([
                'name' => $this->role,
                'guard_name' => 'web',
            ]);

            // Jika role belum memiliki permission (misal dalam unit test yang me-refresh database),
            // otomatis lampirkan permissions standar role tersebut.
            if ($role->permissions()->count() === 0) {
                $defaultPerms = static::defaultRolePermissions($this->role);
                foreach ($defaultPerms as $permName) {
                    Permission::firstOrCreate([
                        'name' => $permName,
                        'guard_name' => 'web',
                    ]);
                }
                $role->syncPermissions($defaultPerms);
            }

            if (! $this->hasRole($this->role)) {
                $this->assignRole($this->role);
            }
        }
    }

    /**
     * Nilai default atribut model.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'OPERATOR',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'unit_kerja_id',
        'role',
        'name',
        'nip',
        'jabatan',
        'email',
        'password',
    ];

    /**
     * Relasi ke unit kerja tempat pengguna bertugas.
     */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    /**
     * Penugasan subkegiatan untuk PPTK.
     */
    public function pptkSubkegiatanAssignments(): HasMany
    {
        return $this->hasMany(PptkSubkegiatanAssignment::class, 'user_id');
    }

    /**
     * Daftar kode subkegiatan yang ditugaskan ke pengguna ini.
     *
     * @return list<string>
     */
    public function assignedSubkegiatanCodes(): array
    {
        return $this->pptkSubkegiatanAssignments()->pluck('kode_sub_kegiatan')->all();
    }

    /**
     * Apakah pengguna dibatasi hanya pada scope satu kelurahan (Lurah atau staf kelurahan).
     */
    public function isKelurahanScoped(): bool
    {
        if ($this->role === 'LURAH') {
            return true;
        }

        return $this->unitKerja?->tipe === 'KELURAHAN';
    }

    /**
     * Apakah pengguna memiliki akses lintas seluruh SKPD (Verifikator, KPA Camat, BP Kecamatan).
     */
    public function isSkpdWide(): bool
    {
        if (in_array($this->role, ['VERIFIKATOR', 'KPA'], true)) {
            return true;
        }

        if ($this->role === 'BPP') {
            return $this->unitKerja?->tipe !== 'KELURAHAN';
        }

        return false;
    }

    /**
     * Apakah pengguna merupakan Bendahara Pengeluaran Pembantu di Kelurahan.
     */
    public function isKelurahanBpp(): bool
    {
        return $this->role === 'BPP' && $this->unitKerja?->tipe === 'KELURAHAN';
    }

    /**
     * Pengecekan akses PPTK terhadap kombinasi unit dan kode subkegiatan.
     */
    public function canAccessPptkSubkegiatan(int $unitId, string $kodeSubKegiatan): bool
    {
        if ((int) $this->unit_kerja_id !== $unitId) {
            return false;
        }

        $assignedCodes = $this->assignedSubkegiatanCodes();
        if (empty($assignedCodes)) {
            return true;
        }

        return in_array($kodeSubKegiatan, $assignedCodes, true);
    }

    /**
     * Otorisasi domain sentral apakah pengguna berhak mengakses suatu kuitansi.
     */
    public function canAccessReceipt(Kuitansi $kuitansi): bool
    {
        if ($this->isSkpdWide()) {
            return true;
        }

        $unitId = (int) $kuitansi->unit_kerja_id;

        if ($this->role === 'PPTK') {
            $kodeSubKegiatan = $kuitansi->rekening?->kode_sub_kegiatan;
            if ($kodeSubKegiatan === null) {
                $kuitansi->loadMissing('rekening');
                $kodeSubKegiatan = $kuitansi->rekening?->kode_sub_kegiatan ?? '';
            }

            return $this->canAccessPptkSubkegiatan($unitId, $kodeSubKegiatan);
        }

        return (int) $this->unit_kerja_id === $unitId;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
