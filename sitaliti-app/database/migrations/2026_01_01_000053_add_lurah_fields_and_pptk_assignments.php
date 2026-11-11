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
            $table->foreignId('lurah_user_id')
                ->nullable()
                ->after('pptk_user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('tanggal_lurah_approve')
                ->nullable()
                ->after('tanggal_pptk_approve');

            $table->text('catatan_lurah')
                ->nullable()
                ->after('catatan_pptk');
        });

        Schema::create('pptk_subkegiatan_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unit_kerja_id')->constrained('master_unit_kerja')->cascadeOnDelete();
            $table->string('kode_sub_kegiatan', 50);
            $table->timestamps();

            $table->unique(['user_id', 'unit_kerja_id', 'kode_sub_kegiatan'], 'uq_pptk_subkegiatan_assignment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pptk_subkegiatan_assignments');

        Schema::table('kuitansi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lurah_user_id');
            $table->dropColumn(['tanggal_lurah_approve', 'catatan_lurah']);
        });
    }
};
