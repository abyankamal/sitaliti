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
            $table->string('golongan_asn', 50)->default('GOLONGAN_III')->after('metode_pembayaran');
            $table->boolean('is_sewa_kendaraan')->default(false)->after('golongan_asn');
            $table->boolean('is_pkp')->default(true)->after('is_sewa_kendaraan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kuitansi', function (Blueprint $table) {
            $table->dropColumn(['golongan_asn', 'is_sewa_kendaraan', 'is_pkp']);
        });
    }
};
