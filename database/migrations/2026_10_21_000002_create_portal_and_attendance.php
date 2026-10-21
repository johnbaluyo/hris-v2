<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_items', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('status', 20)->default('Active');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('private_files')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event_type', 60)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('all_day')->default(true);
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->timestamps();
        });
        Schema::create('calendar_event_user', function (Blueprint $table) {
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->primary(['calendar_event_id', 'user_id']);
        });
        Schema::create('event_mail_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('revision', 64);
            $table->timestamp('sent_at')->nullable();
            $table->unique(['calendar_event_id', 'user_id', 'revision']);
        });
        Schema::create('service_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('token_hash', 64)->unique();
            $table->json('scopes');
            $table->json('devices');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('raw_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_client_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source_key', 180)->nullable()->unique();
            $table->unsignedBigInteger('anviz_log_id')->nullable()->index();
            $table->string('source_epoch', 40)->nullable();
            $table->string('atms_id', 50)->index();
            $table->dateTime('punched_at')->index();
            $table->string('device', 100);
            $table->string('record_type', 50)->nullable();
            $table->json('wfh_location')->nullable();
            $table->timestamps();
            $table->index(['atms_id', 'punched_at']);
        });
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedTinyInteger('workweek');
            $table->string('policy_version', 40)->default('office-v1');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'starts_on', 'ends_on']);
        });
        Schema::create('daily_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('attendance_date');
            foreach (['time_in', 'lunch_out', 'lunch_in', 'time_out', 'ot_in', 'ot_out'] as $field) {
                $table->time($field)->nullable();
            }
            $table->unsignedTinyInteger('workweek')->nullable();
            $table->unsignedInteger('worked_minutes')->nullable();
            $table->unsignedInteger('late_minutes')->nullable();
            $table->unsignedInteger('undertime_minutes')->nullable();
            $table->unsignedInteger('overtime_minutes')->nullable();
            $table->string('policy_version', 40);
            $table->boolean('needs_review')->default(true);
            $table->boolean('is_override')->default(false);
            $table->text('notes')->nullable();
            $table->json('provenance');
            $table->timestamps();
            $table->unique(['user_id', 'attendance_date']);
        });
        Schema::create('attendance_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_attendance_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reason', 1000);
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('created_at');
        });
        Schema::create('attendance_remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('coverage');
            $table->unsignedInteger('fc_absence')->default(0);
            $table->string('remarks', 1000)->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'coverage']);
        });
        Schema::create('integration_runs', function (Blueprint $table) {
            $table->id();
            $table->string('partner');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('queued');
            $table->string('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['integration_runs', 'attendance_remarks', 'attendance_revisions', 'daily_attendances', 'work_schedules', 'raw_punches', 'service_clients', 'event_mail_deliveries', 'calendar_event_user', 'calendar_events', 'portal_items'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
