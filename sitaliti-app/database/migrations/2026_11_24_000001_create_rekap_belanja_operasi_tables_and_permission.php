<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('master_rekap_belanja_operasi')) {
            Schema::create('master_rekap_belanja_operasi', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 30)->unique();
                $table->string('nama', 150);
                $table->text('deskripsi')->nullable();
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'urutan']);
            });
        }

        if (! Schema::hasTable('rekap_belanja_operasi_rekening')) {
            Schema::create('rekap_belanja_operasi_rekening', function (Blueprint $table) {
                $table->id();
                $table->foreignId('master_rekap_id')
                    ->constrained('master_rekap_belanja_operasi')
                    ->cascadeOnDelete();
                $table->string('kode_rekening', 64)->unique();
                $table->timestamps();

                $table->index('master_rekap_id');
            });
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            $permission = Permission::firstOrCreate([
                'name' => 'rekap_belanja_operasi.manage',
                'guard_name' => 'web',
            ]);

            foreach (['BP', 'VERIFIKATOR'] as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                if ($role) {
                    $role->givePermissionTo($permission);
                }
            }

            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_belanja_operasi_rekening');
        Schema::dropIfExists('master_rekap_belanja_operasi');

        if (Schema::hasTable('permissions')) {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            Permission::where('name', 'rekap_belanja_operasi.manage')
                ->where('guard_name', 'web')
                ->delete();

            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }
};
