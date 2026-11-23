<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_kerja_id')
                ->nullable()
                ->constrained('master_unit_kerja')
                ->nullOnDelete();
            $table->string('nip', 30)->unique();
            $table->string('nama', 200);
            $table->string('jabatan', 150)->nullable();
            $table->string('golongan', 50)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('kuitansi', function (Blueprint $table) {
            $table->string('sppd_foto_path', 255)->nullable()->after('catatan_verifikasi');
            $table->string('sppd_foto_name', 255)->nullable()->after('sppd_foto_path');
            $table->unsignedBigInteger('sppd_foto_size')->nullable()->after('sppd_foto_name');
            $table->string('sppd_foto_mime', 100)->nullable()->after('sppd_foto_size');
        });

        Schema::create('kuitansi_sppd_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kuitansi_id')
                ->constrained('kuitansi')
                ->cascadeOnDelete();
            $table->foreignId('pegawai_id')
                ->constrained('pegawai')
                ->restrictOnDelete();
            $table->date('tanggal_berangkat');
            $table->date('tanggal_pulang');
            $table->string('tujuan', 255)->nullable();
            $table->string('foto_path', 255)->nullable();
            $table->string('foto_name', 255)->nullable();
            $table->unsignedBigInteger('foto_size')->nullable();
            $table->string('foto_mime', 100)->nullable();
            $table->timestamps();

            $table->unique(['kuitansi_id', 'pegawai_id'], 'uq_kuitansi_sppd_pegawai');
            $table->index(['pegawai_id', 'tanggal_berangkat', 'tanggal_pulang'], 'idx_sppd_peserta_schedule');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            DB::statement('ALTER TABLE kuitansi_sppd_peserta ADD CONSTRAINT chk_sppd_tanggal_valid CHECK (tanggal_pulang >= tanggal_berangkat)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kuitansi_sppd_peserta');

        Schema::table('kuitansi', function (Blueprint $table) {
            $table->dropColumn([
                'sppd_foto_path',
                'sppd_foto_name',
                'sppd_foto_size',
                'sppd_foto_mime',
            ]);
        });

        Schema::dropIfExists('pegawai');
    }
};
