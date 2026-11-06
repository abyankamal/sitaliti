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
        Schema::create('anggaran_kas_bulanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anggaran_rekening_id')
                ->constrained('anggaran_rekening')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('bulan'); // 1 s.d. 12
            $table->decimal('alokasi_pagu', 15, 2)->default(0.00);
            $table->decimal('terkunci_bulan', 15, 2)->default(0.00);
            $table->decimal('realisasi_bulan', 15, 2)->default(0.00);
            $table->timestamps();

            $table->unique(
                ['anggaran_rekening_id', 'bulan'],
                'uq_rekening_bulan'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anggaran_kas_bulanan');
    }
};
