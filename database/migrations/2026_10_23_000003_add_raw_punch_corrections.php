<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_punches', function (Blueprint $table) {
            $table->unsignedInteger('lock_version')->default(0);
            $table->json('source_evidence')->nullable();
        });
        Schema::create('raw_punch_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_punch_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 900);
            $table->json('before');
            $table->json('after');
            $table->timestamp('created_at');
            $table->index(['raw_punch_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_punch_revisions');
        Schema::table('raw_punches', function (Blueprint $table) {
            $table->dropColumn(['lock_version', 'source_evidence']);
        });
    }
};
