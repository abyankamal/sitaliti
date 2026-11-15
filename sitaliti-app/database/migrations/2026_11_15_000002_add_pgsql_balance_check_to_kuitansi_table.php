<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE kuitansi ADD CONSTRAINT chk_keseimbangan CHECK (nominal_neto = nominal_bruto - total_pajak)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE kuitansi DROP CONSTRAINT IF EXISTS chk_keseimbangan');
        }
    }
};
