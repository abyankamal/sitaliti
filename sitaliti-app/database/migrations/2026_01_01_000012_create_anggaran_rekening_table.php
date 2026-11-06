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
        Schema::create('anggaran_rekening', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_kerja_id')
                ->constrained('master_unit_kerja')
                ->cascadeOnDelete();
            $table->string('kode_sub_kegiatan', 50)->index();
            $table->string('nama_sub_kegiatan')->nullable();
            $table->string('kode_rekening', 50)->index();
            $table->string('nama_rekening');
            $table->decimal('pagu_murni', 15, 2)->default(0.00);
            $table->decimal('pagu_perubahan', 15, 2)->default(0.00);
            $table->string('kategori_belanja', 20)->nullable()->index(); // B.1 - B.6
            $table->boolean('is_leaf')->default(true);
            $table->timestamps();

            $table->unique(
                ['unit_kerja_id', 'kode_sub_kegiatan', 'kode_rekening'],
                'uq_rekening_unit_subkeg'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anggaran_rekening');
    }
};
