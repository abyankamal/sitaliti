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
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('status_kepegawaian', 20)->default('ASN')->after('unit_kerja_id')->index();
            $table->string('nip', 30)->nullable()->change();
            $table->string('nik', 20)->nullable()->after('nip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn(['nik', 'status_kepegawaian']);
            $table->string('nip', 30)->nullable(false)->change();
        });
    }
};
