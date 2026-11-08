<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
            ],
            'VERIFIKATOR' => [
                'kuitansi.view',
                'verification.queue',
                'verification.audit',
                'verification.process_action',
                'verification.generate_qr',
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
