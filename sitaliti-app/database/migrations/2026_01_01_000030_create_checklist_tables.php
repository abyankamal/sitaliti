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
        Schema::create('master_checklist_item', function (Blueprint $table) {
            $table->id();
            $table->string('kategori_belanja', 10)->index(); // B.1 s.d. B.6
            $table->string('kode_item', 50)->unique(); // DOC_B1_01, etc.
            $table->string('nama_item', 255);
            $table->text('deskripsi')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kuitansi_verifikasi_checklist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kuitansi_id')
                ->constrained('kuitansi')
                ->cascadeOnDelete();
            $table->foreignId('master_checklist_item_id')
                ->constrained('master_checklist_item')
                ->cascadeOnDelete();
            $table->foreignId('kuitansi_lampiran_id')
                ->nullable()
                ->constrained('kuitansi_lampiran')
                ->nullOnDelete();
            $table->boolean('is_verified')->default(false);
            $table->string('status_kesesuaian', 30)->default('BELUM_DIPERIKSA'); // SESUAI, TIDAK_SESUAI, BELUM_DIPERIKSA
            $table->text('catatan')->nullable();
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['kuitansi_id', 'master_checklist_item_id'],
                'uq_kuitansi_checklist'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kuitansi_verifikasi_checklist');
        Schema::dropIfExists('master_checklist_item');
    }
};
