<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('acronym', 30)->nullable();
            $table->timestamps();
        });
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('code')->nullable()->unique();
            $table->boolean('plantilla')->default(false);
            $table->string('classification')->nullable();
            $table->string('status')->default('Open for Application')->index();
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('extension', 30)->nullable();
            $table->string('nickname')->nullable();
            $table->string('role')->default('staff')->index();
            $table->string('employee_type')->nullable()->index();
            $table->foreignId('division_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('atms_id', 50)->nullable()->unique();
            $table->date('date_hired')->nullable();
            $table->date('termination_date')->nullable()->index();
            $table->boolean('is_archived')->default(false)->index();
            $table->unsignedTinyInteger('workweek')->nullable();
            $table->unsignedTinyInteger('step_increment')->nullable();
            $table->date('birthday')->nullable();
            $table->string('sex', 50)->nullable();
            $table->string('mobile_no', 50)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_person_no', 50)->nullable();
        });
        Schema::create('ability_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ability');
            $table->unique(['user_id', 'ability']);
        });
        Schema::create('position_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('old_position_id')->nullable()->constrained('positions')->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->date('assume_date')->index();
            $table->timestamps();
        });
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->string('ability');
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'ability', 'period_start', 'period_end']);
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at');
            $table->index(['subject_type', 'subject_id']);
        });
        Schema::create('private_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('purpose');
            $table->string('original_name');
            $table->string('path')->unique();
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64);
            $table->timestamps();
        });
        Schema::create('pds_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('section', 40);
            $table->json('data');
            $table->foreignId('file_id')->nullable()->constrained('private_files')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'section', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['pds_entries', 'private_files', 'audit_events', 'delegations', 'position_assignments', 'ability_grants'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['employee_type']);
            $table->dropIndex(['termination_date']);
            $table->dropIndex(['is_archived']);
            $table->dropUnique(['atms_id']);
            $table->dropConstrainedForeignId('division_id');
            $table->dropConstrainedForeignId('position_id');
            $table->dropColumn(['first_name', 'middle_name', 'last_name', 'extension', 'nickname', 'role', 'employee_type', 'atms_id', 'date_hired', 'termination_date', 'is_archived', 'workweek', 'step_increment', 'birthday', 'sex', 'mobile_no', 'contact_person', 'contact_person_no']);
        });
        Schema::dropIfExists('positions');
        Schema::dropIfExists('divisions');
    }
};
