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
        Schema::create('sppd_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_kerja_id')
                ->constrained('master_unit_kerja')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('anggaran_rekening_id')
                ->nullable()
                ->constrained('anggaran_rekening')
                ->nullOnDelete();
            $table->foreignId('kuitansi_id')
                ->nullable()
                ->unique()
                ->constrained('kuitansi')
                ->nullOnDelete();
            $table->string('nomor_surat_tugas', 100)->nullable();
            $table->text('maksud_perjalanan');
            $table->string('alat_angkut', 150)->nullable();
            $table->string('tempat_berangkat', 200);
            $table->string('tempat_tujuan', 255);
            $table->date('tanggal_berangkat');
            $table->date('tanggal_kembali');
            $table->string('pemberi_perintah_nama', 200);
            $table->string('pemberi_perintah_nip', 30)->nullable();
            $table->string('pemberi_perintah_jabatan', 150);
            $table->string('dikeluarkan_di', 100)->default('Garut');
            $table->date('tanggal_penetapan')->nullable();
            $table->text('laporan_hasil')->nullable();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->timestamps();

            $table->index(['unit_kerja_id', 'status'], 'idx_sppd_assignments_unit_status');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            DB::statement('ALTER TABLE sppd_assignments ADD CONSTRAINT chk_sppd_assignment_tanggal_valid CHECK (tanggal_kembali >= tanggal_berangkat)');
        }

        Schema::create('sppd_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sppd_assignment_id')
                ->constrained('sppd_assignments')
                ->cascadeOnDelete();
            $table->foreignId('pegawai_id')
                ->constrained('pegawai')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->string('nomor_spd', 100)->nullable();
            $table->string('tingkat_biaya', 100)->nullable();
            $table->json('pengikut')->nullable();
            $table->decimal('dibayar_semula', 15, 2)->default(0);
            $table->text('keterangan_lain')->nullable();
            $table->timestamps();

            $table->unique(['sppd_assignment_id', 'pegawai_id'], 'uq_sppd_assignment_pegawai');
            $table->index('pegawai_id', 'idx_sppd_assignment_peserta_pegawai');
        });

        Schema::create('sppd_peserta_biaya', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sppd_peserta_id')
                ->constrained('sppd_peserta')
                ->cascadeOnDelete();
            $table->string('uraian_biaya', 255);
            $table->decimal('nominal', 15, 2)->default(0);
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sppd_peserta_biaya');
        Schema::dropIfExists('sppd_peserta');
        Schema::dropIfExists('sppd_assignments');
    }
};
