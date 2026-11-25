<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('master_tarif_pajak')) {
            Schema::create('master_tarif_pajak', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('tahun_anggaran');
                $table->string('kode_pajak', 40);
                $table->string('nama_pajak', 150);
                $table->decimal('tarif', 8, 4)->default(0);
                $table->decimal('ambang_batas', 15, 2)->nullable();
                $table->decimal('nominal_tetap', 15, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tahun_anggaran', 'kode_pajak'], 'uq_tarif_pajak_tahun_kode');
                $table->index(['tahun_anggaran', 'is_active'], 'idx_tarif_pajak_tahun_active');
            });
        }

        if (! Schema::hasTable('master_sbm_item')) {
            Schema::create('master_sbm_item', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('tahun_anggaran');
                $table->string('kategori_belanja', 30);
                $table->string('kode_sbm', 40);
                $table->string('uraian_standar', 255);
                $table->string('satuan', 40);
                $table->decimal('harga_maksimal', 15, 2);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tahun_anggaran', 'kode_sbm'], 'uq_sbm_tahun_kode');
                $table->index(['tahun_anggaran', 'kategori_belanja', 'is_active'], 'idx_sbm_tahun_kat_active');
            });
        }

        if (! Schema::hasTable('pengaturan_kepatuhan')) {
            Schema::create('pengaturan_kepatuhan', function (Blueprint $table) {
                $table->id();
                $table->string('key', 80)->unique();
                $table->string('value', 255);
                $table->string('description', 255)->nullable();
                $table->foreignId('updated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_kepatuhan');
        Schema::dropIfExists('master_sbm_item');
        Schema::dropIfExists('master_tarif_pajak');
    }
};
