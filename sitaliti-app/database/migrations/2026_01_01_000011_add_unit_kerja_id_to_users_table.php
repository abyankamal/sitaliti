<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('unit_kerja_id')
                ->nullable()
                ->after('id')
                ->constrained('master_unit_kerja')
                ->nullOnDelete();
            $table->string('role', 50)->default('OPERATOR')->after('unit_kerja_id');
            $table->string('nip', 30)->nullable()->after('name');
            $table->string('jabatan', 100)->nullable()->after('nip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_kerja_id');
            $table->dropColumn(['role', 'nip', 'jabatan']);
        });
    }
};
