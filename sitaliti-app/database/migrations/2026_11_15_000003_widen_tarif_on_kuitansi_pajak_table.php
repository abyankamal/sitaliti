<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kuitansi_pajak', function (Blueprint $table) {
            $table->decimal('tarif', 15, 4)->default(0.0000)->change();
        });
    }

    public function down(): void
    {
        Schema::table('kuitansi_pajak', function (Blueprint $table) {
            $table->decimal('tarif', 6, 4)->default(0.0000)->change();
        });
    }
};
