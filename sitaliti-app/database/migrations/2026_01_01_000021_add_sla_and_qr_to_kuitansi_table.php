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
        Schema::table('kuitansi', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique()->after('status');
            $table->timestamp('tenggat_sla')->nullable()->after('catatan_verifikasi');
            $table->foreignId('verifikator_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kuitansi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verifikator_id');
            $table->dropColumn(['qr_token', 'tenggat_sla']);
        });
    }
};

