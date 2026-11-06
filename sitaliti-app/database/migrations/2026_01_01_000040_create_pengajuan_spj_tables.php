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
        Schema::create('pengajuan_spj', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_bundel', 60)->unique();
            $table->foreignId('unit_kerja_id')
                ->constrained('master_unit_kerja')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('jenis_bundel', 30)->default('SPP_GU'); // SPP_GU, SPP_TU, SPP_LS, LPJ_BPP
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->unsignedTinyInteger('bulan_periode'); // 1 s.d. 12
            $table->decimal('total_bruto', 15, 2)->default(0.00);
            $table->decimal('total_pajak', 15, 2)->default(0.00);
            $table->decimal('total_neto', 15, 2)->default(0.00);
            $table->string('status_bundel', 40)->default('DRAFT'); // DRAFT, MENUNGGU_PPK, DISETUJUI_PPK, SYNCED_SIPD
            $table->foreignId('ppk_skpd_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('nomor_spp_sipd', 100)->nullable();
            $table->string('nomor_sp2d_sipd', 100)->nullable();
            $table->date('tanggal_sp2d')->nullable();
            $table->json('checklist_bagian_a')->nullable();
            $table->timestamps();
        });

        Schema::create('pengajuan_spj_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_spj_id')
                ->constrained('pengajuan_spj')
                ->cascadeOnDelete();
            $table->foreignId('kuitansi_id')
                ->constrained('kuitansi')
                ->cascadeOnDelete();
            $table->unsignedInteger('no_urut_ordner')->default(1);
            $table->string('status_item', 20)->default('AKTIF'); // AKTIF, DIKELUARKAN
            $table->unsignedBigInteger('active_kuitansi_id')
                ->storedAs("CASE WHEN status_item = 'AKTIF' THEN kuitansi_id ELSE NULL END")
                ->nullable();
            $table->timestamps();

            // Proteksi Anti-Klaim Ganda: 1 Kuitansi hanya boleh AKTIF di maksimal 1 Bundel SPJ
            $table->unique('active_kuitansi_id', 'uq_satu_kuitansi_aktif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengajuan_spj_item');
        Schema::dropIfExists('pengajuan_spj');
    }
};
