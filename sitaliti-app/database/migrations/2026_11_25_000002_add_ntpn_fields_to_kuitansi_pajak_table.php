<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kuitansi_pajak')) {
            Schema::table('kuitansi_pajak', function (Blueprint $table) {
                if (! Schema::hasColumn('kuitansi_pajak', 'kode_billing')) {
                    $table->string('kode_billing', 30)->nullable()->after('nominal');
                }
                if (! Schema::hasColumn('kuitansi_pajak', 'ntpn')) {
                    $table->string('ntpn', 30)->nullable()->after('kode_billing')->index('idx_kuitansi_pajak_ntpn');
                }
                if (! Schema::hasColumn('kuitansi_pajak', 'tanggal_setor')) {
                    $table->date('tanggal_setor')->nullable()->after('ntpn');
                }
                if (! Schema::hasColumn('kuitansi_pajak', 'bukti_setor_path')) {
                    $table->string('bukti_setor_path')->nullable()->after('tanggal_setor');
                }
                if (! Schema::hasColumn('kuitansi_pajak', 'ntpn_updated_by')) {
                    $table->foreignId('ntpn_updated_by')
                        ->nullable()
                        ->after('bukti_setor_path')
                        ->constrained('users')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('kuitansi_pajak', 'ntpn_updated_at')) {
                    $table->timestamp('ntpn_updated_at')->nullable()->after('ntpn_updated_by');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('kuitansi_pajak')) {
            Schema::table('kuitansi_pajak', function (Blueprint $table) {
                if (Schema::hasColumn('kuitansi_pajak', 'ntpn_updated_by')) {
                    $table->dropConstrainedForeignId('ntpn_updated_by');
                }
                $columns = ['kode_billing', 'ntpn', 'tanggal_setor', 'bukti_setor_path', 'ntpn_updated_at'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('kuitansi_pajak', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
