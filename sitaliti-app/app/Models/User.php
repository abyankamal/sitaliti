<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

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
     * Sinkronisasi nilai kolom 'role' ke Spatie role pivot table.
     */
    public function syncSpatieRole(): void
    {
        if (! empty($this->role)) {
            // Pastikan role ada di tabel roles agar tidak melempar RoleDoesNotExist
            Role::firstOrCreate([
                'name' => $this->role,
                'guard_name' => 'web',
            ]);

            if (! $this->hasRole($this->role)) {
                $this->assignRole($this->role);
            }
        }
    }

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
