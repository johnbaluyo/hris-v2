<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->whereNotNull('position_id')->select('position_id')->groupBy('position_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Assign a separate position to each employee before applying the one-to-one position constraint.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->unique('position_id', 'users_position_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_position_id_unique');
        });
    }
};
