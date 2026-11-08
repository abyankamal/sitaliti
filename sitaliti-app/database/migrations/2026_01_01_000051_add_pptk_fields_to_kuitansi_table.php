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
            $table->foreignId('pptk_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('tanggal_pptk_approve')
                ->nullable()
                ->after('tanggal_submit');

            $table->text('catatan_pptk')
                ->nullable()
                ->after('catatan_verifikasi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kuitansi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pptk_user_id');
            $table->dropColumn(['tanggal_pptk_approve', 'catatan_pptk']);
        });
    }
};

