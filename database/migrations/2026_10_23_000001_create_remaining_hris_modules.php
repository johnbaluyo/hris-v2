<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_vacancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status')->default('Open for Application');
            $table->text('description')->nullable();
            $table->date('closes_on')->nullable();
            $table->timestamps();
        });
        Schema::create('exam_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('exam_type');
            $table->unsignedSmallInteger('time_limit');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_category_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('question_type');
            $table->text('question');
            $table->json('choices')->nullable();
            $table->foreignId('image_file_id')->nullable()->constrained('private_files')->restrictOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::create('vacancy_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_vacancy_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_category_id')->constrained()->restrictOnDelete();
            $table->unique(['recruitment_vacancy_id', 'exam_category_id'], 'vacancy_category_unique');
        });
        Schema::create('recruitment_evaluators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_vacancy_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role');
            $table->unique(['recruitment_vacancy_id', 'user_id'], 'vacancy_evaluator_unique');
        });
        Schema::create('applicant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->json('data');
            $table->timestamps();
        });
        Schema::create('recruitment_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_vacancy_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending application');
            $table->text('remark')->nullable();
            $table->boolean('allow_test')->default(false);
            $table->json('profile_snapshot');
            $table->timestamps();
            $table->unique(['recruitment_vacancy_id', 'user_id'], 'vacancy_applicant_unique');
        });
        Schema::create('recruitment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('recruitment_application_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('private_file_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 40);
            $table->string('side', 20);
            $table->timestamps();
        });
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_application_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_category_id')->constrained()->restrictOnDelete();
            $table->json('questions');
            $table->json('category_snapshot');
            $table->unsignedSmallInteger('time_limit');
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->json('answers')->nullable();
            $table->timestamps();
            $table->unique(['recruitment_application_id', 'exam_category_id'], 'application_category_attempt_unique');
        });
        Schema::create('employment_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('status')->default('For Acknowledgment');
            $table->string('verification_token', 64)->unique();
            $table->text('purpose');
            $table->json('snapshot');
            $table->json('compensation')->nullable();
            $table->unsignedBigInteger('annual_total_cents')->default(0);
            $table->unsignedBigInteger('benefit_total_cents')->default(0);
            $table->unsignedBigInteger('grand_total_cents')->default(0);
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
        Schema::create('certificate_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_certificate_id')->constrained()->restrictOnDelete();
            $table->foreignId('private_file_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('recognition_events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20);
            $table->date('coverage_start');
            $table->date('coverage_end');
            $table->date('recognition_date');
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->boolean('allow_self_vote')->default(false);
            $table->json('voter_types');
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('recognition_nominees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recognition_event_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->json('snapshot');
            $table->boolean('winner')->default(false);
            $table->unique(['recognition_event_id', 'user_id'], 'recognition_nominee_unique');
        });
        Schema::create('recognition_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recognition_event_id')->constrained()->restrictOnDelete();
            $table->foreignId('recognition_nominee_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['recognition_event_id', 'user_id'], 'recognition_voter_unique');
        });
        Schema::create('wfh_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->date('wfh_date');
            $table->string('status')->default('for review');
            $table->unsignedInteger('submitted_count')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->json('division_snapshot');
            $table->json('signer_snapshot')->nullable();
            $table->json('approver_snapshot')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['division_id', 'wfh_date']);
        });
        Schema::create('wfh_accomplishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->date('wfh_date');
            $table->text('accomplishment');
            $table->string('status')->default('for approval');
            $table->text('return_remarks')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'wfh_date']);
            $table->index(['division_id', 'wfh_date']);
        });
        Schema::create('wfh_report_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wfh_report_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('wfh_accomplishment_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('snapshot');
            $table->unique(['wfh_report_id', 'user_id']);
        });
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 80);
            $table->string('event_id', 120);
            $table->string('payload_hash', 64);
            $table->string('system', 100);
            $table->string('source', 100);
            $table->string('title');
            $table->text('message');
            $table->string('url', 2048)->nullable();
            $table->string('notification_type', 20);
            $table->string('recipient_email')->nullable();
            $table->unsignedTinyInteger('priority')->default(5);
            $table->string('badge_text', 40)->default('New');
            $table->string('badge_class', 30)->default('badge-info');
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['source_key', 'event_id']);
        });
        Schema::create('notification_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_notification_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('role')->nullable();
        });
        Schema::create('notification_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_notification_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->unique(['system_notification_id', 'user_id'], 'notification_recipient_unique');
            $table->index(['user_id', 'dismissed_at']);
        });
    }

    public function down(): void
    {
        foreach (['notification_receipts', 'notification_audiences', 'system_notifications', 'wfh_report_lines', 'wfh_accomplishments', 'wfh_reports', 'recognition_votes', 'recognition_nominees', 'recognition_events', 'certificate_files', 'employment_certificates', 'exam_attempts', 'recruitment_documents', 'recruitment_applications', 'applicant_profiles', 'recruitment_evaluators', 'vacancy_categories', 'exam_questions', 'exam_categories', 'recruitment_vacancies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
