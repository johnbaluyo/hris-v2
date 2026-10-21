<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_punches', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['user_id', 'punched_at']);
        });
        // Existing unmatched evidence stays quarantined; do not guess its historical employee.
    }

    public function down(): void
    {
        Schema::table('raw_punches', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'punched_at']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
