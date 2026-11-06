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
        Schema::create('kuitansi', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_kuitansi', 50)->unique();
            $table->foreignId('unit_kerja_id')
                ->constrained('master_unit_kerja')
                ->cascadeOnDelete();
            $table->foreignId('anggaran_rekening_id')
                ->constrained('anggaran_rekening')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('bulan_anggaran'); // 1 s.d. 12
            $table->string('nomor_bukti_toko', 100)->nullable();
            $table->date('tanggal_transaksi');
            $table->text('uraian');
            $table->string('nama_penerima', 200);
            $table->string('npwp_penerima', 30)->nullable();
            $table->string('metode_pembayaran', 30)->default('NON_TUNAI_IBC'); // NON_TUNAI_IBC, TUNAI_UP
            $table->decimal('nominal_bruto', 15, 2);
            $table->decimal('total_pajak', 15, 2)->default(0.00);
            $table->decimal('nominal_neto', 15, 2);
            $table->string('status', 30)->default('DRAFT'); // DRAFT, MENUNGGU_PPTK, MENUNGGU_VERIFIKASI, LOLOS_VERIFIKASI, REVISI_MINOR, REVISI_MAYOR, DITOLAK, DIBUNDEL
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamp('tanggal_submit')->nullable();
            $table->timestamp('tanggal_verifikasi')->nullable();
            $table->timestamps();
        });

        // Terapkan Check Constraint di MySQL 8.0
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE kuitansi ADD CONSTRAINT chk_keseimbangan CHECK (nominal_neto = nominal_bruto - total_pajak)');
        }

        Schema::create('kuitansi_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kuitansi_id')
                ->constrained('kuitansi')
                ->cascadeOnDelete();
            $table->string('nama_barang', 255);
            $table->decimal('volume', 12, 2);
            $table->string('satuan', 50);
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('total_harga', 15, 2);
            $table->timestamps();
        });

        Schema::create('kuitansi_pajak', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kuitansi_id')
                ->constrained('kuitansi')
                ->cascadeOnDelete();
            $table->string('kode_pajak', 30); // PPN_11, PBJT_MAMIN_10, PPH_21, PPH_22, PPH_23, BEA_METERAI
            $table->string('nama_pajak', 100);
            $table->decimal('tarif', 6, 4)->default(0.0000);
            $table->string('tipe_tarif', 30)->default('PERSENTASE_DPP'); // PERSENTASE_DPP, PERSENTASE_BRUTO, NOMINAL_FLAT
            $table->decimal('dpp', 15, 2)->default(0.00);
            $table->decimal('nominal', 15, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('kuitansi_lampiran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kuitansi_id')
                ->constrained('kuitansi')
                ->cascadeOnDelete();
            $table->foreignId('anggaran_rekening_id')
                ->constrained('anggaran_rekening')
                ->cascadeOnDelete();
            $table->string('checklist_code', 30)->nullable();
            $table->string('nama_dokumen', 200);
            $table->string('file_path', 255);
            $table->string('file_name', 255);
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 100);
            $table->string('file_hash', 64)->index(); // SHA-256
            $table->timestamps();

            // Anti-Duplikasi Nota: hash yang sama pada rekening yang sama ditolak di tingkat database
            $table->unique(
                ['anggaran_rekening_id', 'file_hash'],
                'uq_rekening_file_hash'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kuitansi_lampiran');
        Schema::dropIfExists('kuitansi_pajak');
        Schema::dropIfExists('kuitansi_item');
        Schema::dropIfExists('kuitansi');
    }
};
