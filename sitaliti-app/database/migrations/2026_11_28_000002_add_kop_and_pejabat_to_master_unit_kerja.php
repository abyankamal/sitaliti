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
        Schema::table('master_unit_kerja', function (Blueprint $table) {
            $table->string('kop_alamat', 255)->nullable()->after('tipe');
            $table->string('kop_telepon_email', 255)->nullable()->after('kop_alamat');
            $table->string('kop_kode_pos', 20)->nullable()->after('kop_telepon_email');
            $table->string('pejabat_nama', 200)->nullable()->after('kop_kode_pos');
            $table->string('pejabat_nip', 30)->nullable()->after('pejabat_nama');
            $table->string('pejabat_jabatan', 150)->nullable()->after('pejabat_nip');
            $table->string('bendahara_nama', 200)->nullable()->after('pejabat_jabatan');
            $table->string('bendahara_nip', 30)->nullable()->after('bendahara_nama');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_unit_kerja', function (Blueprint $table) {
            $table->dropColumn([
                'kop_alamat',
                'kop_telepon_email',
                'kop_kode_pos',
                'pejabat_nama',
                'pejabat_nip',
                'pejabat_jabatan',
                'bendahara_nama',
                'bendahara_nip',
            ]);
        });
    }
};
