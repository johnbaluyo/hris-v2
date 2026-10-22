<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->string('action', 40)->nullable()->after('actor_id');
        });
    }

    public function down(): void
    {
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->dropColumn('action');
        });
    }
};
