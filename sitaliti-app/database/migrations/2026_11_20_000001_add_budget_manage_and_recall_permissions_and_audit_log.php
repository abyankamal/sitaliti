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
        if (! Schema::hasTable('anggaran_audit_logs')) {
            Schema::create('anggaran_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('anggaran_rekening_id')
                    ->constrained('anggaran_rekening')
                    ->cascadeOnDelete();
                $table->foreignId('unit_kerja_id')
                    ->constrained('master_unit_kerja')
                    ->restrictOnDelete();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->string('actor_role', 30)->nullable();
                $table->string('action', 40);
                $table->decimal('old_pagu_perubahan', 15, 2)->default(0);
                $table->decimal('new_pagu_perubahan', 15, 2)->default(0);
                $table->json('old_alokasi')->nullable();
                $table->json('new_alokasi')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['anggaran_rekening_id', 'created_at']);
                $table->index(['unit_kerja_id', 'created_at']);
            });
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            $budgetManage = Permission::firstOrCreate(['name' => 'budget.manage', 'guard_name' => 'web']);
            $kuitansiRecall = Permission::firstOrCreate(['name' => 'kuitansi.recall', 'guard_name' => 'web']);

            foreach (['VERIFIKATOR', 'KPA', 'LURAH', 'OPERATOR'] as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                if ($role) {
                    $role->givePermissionTo($budgetManage);
                }
            }

            $operatorRole = Role::where('name', 'OPERATOR')->where('guard_name', 'web')->first();
            if ($operatorRole) {
                $operatorRole->givePermissionTo($kuitansiRecall);
            }

            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('anggaran_audit_logs');
    }
};
