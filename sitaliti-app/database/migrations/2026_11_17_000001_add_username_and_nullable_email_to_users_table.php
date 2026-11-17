<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 60)->nullable()->after('name');
            $table->string('email')->nullable()->change();
        });

        $existingUsers = DB::table('users')->select('id', 'email', 'username')->get();
        foreach ($existingUsers as $u) {
            if (empty($u->username)) {
                $base = ! empty($u->email)
                    ? Str::lower(Str::before($u->email, '@'))
                    : 'user_'.$u->id;

                $candidate = $base;
                $suffix = 1;
                while (DB::table('users')->where('username', $candidate)->where('id', '!=', $u->id)->exists()) {
                    $candidate = $base.'_'.$suffix++;
                }

                DB::table('users')->where('id', $u->id)->update(['username' => $candidate]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 60)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
