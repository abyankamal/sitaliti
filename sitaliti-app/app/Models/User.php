<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
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
        static::creating(function (User $user) {
            if (blank($user->username)) {
                $base = ! blank($user->email)
                    ? Str::lower(Str::before((string) $user->email, '@'))
                    : Str::slug((string) ($user->name ?: 'user'), '.');

                $user->username = $base !== '' ? $base : 'user_'.Str::lower(Str::random(6));
            } else {
                $user->username = Str::lower((string) $user->username);
            }
        });

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
     * Jika parameter $role null, mengembalikan seluruh associative array role => permissions.
     *
     * @return array<string, list<string>>|list<string>
     */
    public static function defaultRolePermissions(?string $role = null): array
    {
        $all = [
            'BPP' => [
                'kuitansi.view',
                'kuitansi.create',
                'kuitansi.edit',
                'kuitansi.submit_pptk',
                'kuitansi.recall',
                'bundle.view',
                'bundle.create',
                'bundle.unbundle',
                'budget.manage',
            ],
            'BP' => [
                'kuitansi.view',
                'kuitansi.create',
                'kuitansi.edit',
                'kuitansi.submit_pptk',
                'verification.queue',
                'verification.audit',
                'verification.process_action',
                'verification.generate_qr',
                'bundle.view',
                'bundle.create',
                'bundle.unbundle',
                'bundle.record_sp2d',
                'budget.manage',
                'rekap_belanja_operasi.manage',
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
                'kuitansi.create',
                'kuitansi.edit',
                'kuitansi.submit_pptk',
                'verification.queue',
                'verification.audit',
                'verification.process_action',
                'verification.generate_qr',
                'bundle.view',
                'user.manage',
                'budget.manage',
                'rekap_belanja_operasi.manage',
                'dashboard.view_all',
            ],
            'LURAH' => [
                'kuitansi.view',
                'bundle.view',
                'budget.view',
            ],
            'KPA' => [
                'kuitansi.view',
                'bundle.view',
                'budget.view',
                'dashboard.view_all',
            ],
        ];

        if ($role === null) {
            return $all;
        }

        return $all[$role] ?? [];
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

            if (! $this->hasRole($this->role) || $this->roles()->count() !== 1) {
                $this->syncRoles([$this->role]);
            }
        }
    }

    /**
     * Nilai default atribut model.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'BPP',
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
        'username',
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
     * Alias untuk pptkSubkegiatanAssignments.
     */
    public function pptkAssignments(): HasMany
    {
        return $this->pptkSubkegiatanAssignments();
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
     * Alias getter untuk assignedSubkegiatanCodes.
     *
     * @return list<string>
     */
    public function getAssignedSubkegiatanCodes(): array
    {
        return $this->assignedSubkegiatanCodes();
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
     * Apakah pengguna memiliki akses baca/monitoring lintas seluruh SKPD (Verifikator, KPA Camat, BP Kecamatan).
     */
    public function isSkpdWide(): bool
    {
        return in_array($this->role, ['VERIFIKATOR', 'KPA', 'BP'], true);
    }

    /**
     * Apakah pengguna dapat menginput kuitansi dan mengelola pagu lintas seluruh unit kerja (Kasubag Keuangan).
     */
    public function canInputCrossUnit(): bool
    {
        return $this->role === 'VERIFIKATOR';
    }

    /**
     * Apakah kuitansi yang diajukan oleh pengguna langsung berstatus final (LOLOS_VERIFIKASI).
     */
    public function canAutoFinalizeReceipt(): bool
    {
        return in_array($this->role, ['BP', 'VERIFIKATOR'], true);
    }

    /**
     * Apakah pengguna berwenang memverifikasi kuitansi BPP Kelurahan.
     */
    public function canVerifyReceipt(): bool
    {
        return in_array($this->role, ['BP', 'VERIFIKATOR'], true);
    }

    /**
     * Apakah pengguna merupakan peran monitoring read-only murni (Lurah atau Camat/KPA).
     */
    public function isReadOnlyRole(): bool
    {
        return in_array($this->role, ['LURAH', 'KPA'], true);
    }

    /**
     * Apakah pengguna merupakan Bendahara Pengeluaran Pembantu di Kelurahan.
     */
    public function isKelurahanBpp(): bool
    {
        return $this->role === 'BPP' && $this->unitKerja?->tipe === 'KELURAHAN';
    }

    /**
     * Apakah pengguna berhak membuat/mengubah/mengajukan suatu kuitansi (write scope).
     */
    public function canMutateReceipt(Kuitansi $kuitansi): bool
    {
        if ($this->isReadOnlyRole()) {
            return false;
        }

        if ($this->canInputCrossUnit()) {
            return true;
        }

        if (! in_array($this->role, ['BPP', 'BP'], true) || $this->unit_kerja_id === null) {
            return false;
        }

        return (int) $this->unit_kerja_id === (int) $kuitansi->unit_kerja_id;
    }

    /**
     * Apakah pengguna berhak menginput kuitansi pada suatu rekening anggaran (write scope).
     */
    public function canInputRekening(AnggaranRekening $rekening): bool
    {
        if ($this->isReadOnlyRole()) {
            return false;
        }

        if ($this->canInputCrossUnit()) {
            return true;
        }

        if (! in_array($this->role, ['BPP', 'BP'], true) || $this->unit_kerja_id === null) {
            return false;
        }

        return (int) $this->unit_kerja_id === (int) $rekening->unit_kerja_id;
    }

    /**
     * Apakah pengguna berhak mengelola pagu/rekening pada unit kerja tertentu.
     */
    public function canManageUnitBudget(int $unitId): bool
    {
        if ($this->isReadOnlyRole()) {
            return false;
        }

        if ($this->canInputCrossUnit()) {
            return true;
        }

        if (! in_array($this->role, ['BPP', 'BP'], true) || $this->unit_kerja_id === null) {
            return false;
        }

        return (int) $this->unit_kerja_id === $unitId;
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

        if ($this->unit_kerja_id === null) {
            return false;
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

        if ((int) $this->unit_kerja_id !== $unitId) {
            return false;
        }

        return true;
    }

    /**
     * Otorisasi domain sentral apakah pengguna berhak mengakses suatu rekening anggaran.
     */
    public function canAccessRekening(AnggaranRekening $rekening): bool
    {
        if ($this->isSkpdWide()) {
            return true;
        }

        if ($this->unit_kerja_id === null) {
            return false;
        }

        if ($this->role === 'PPTK') {
            return $this->canAccessPptkSubkegiatan(
                (int) $rekening->unit_kerja_id,
                (string) $rekening->kode_sub_kegiatan
            );
        }

        return (int) $this->unit_kerja_id === (int) $rekening->unit_kerja_id;
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
