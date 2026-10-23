<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_punch_windows', function (Blueprint $table) {
            $table->id();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->boolean('all_employees')->default(true);
            $table->string('reason', 1000);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('office_punch_window_users', function (Blueprint $table) {
            $table->foreignId('office_punch_window_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->primary(['office_punch_window_id', 'user_id'], 'office_punch_window_user_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_punch_window_users');
        Schema::dropIfExists('office_punch_windows');
    }
};
