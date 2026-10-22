<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('category');
            $table->string('title');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['position_id', 'category', 'title']);
        });
        Schema::create('training_requirement_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('training_requirement_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('idp_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('plan_year');
            $table->json('coverage_years');
            $table->unsignedTinyInteger('purpose');
            $table->text('other_purpose')->nullable();
            $table->json('employee_snapshot')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'plan_year']);
        });
        Schema::create('idp_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idp_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_requirement_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('proposal');
            $table->string('title');
            $table->timestamps();
        });
        Schema::create('division_learning_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('plan_year');
            $table->decimal('total_budget', 15, 2)->default(0);
            $table->unsignedInteger('staff_count')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['division_id', 'plan_year']);
        });
        Schema::create('division_learning_plan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_learning_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('idp_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('budget', 15, 2)->default(0);
            $table->boolean('is_free')->default(false);
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['division_learning_plan_id', 'idp_item_id'], 'division_plan_item_unique');
        });

        Schema::create('saln_filings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('filing_type');
            $table->unsignedTinyInteger('compliance_type');
            $table->date('compliance_date');
            $table->timestamps();
            $table->index(['user_id', 'compliance_date']);
        });
        Schema::create('saln_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saln_filing_id')->constrained()->cascadeOnDelete();
            $table->string('section', 30);
            $table->json('data');
            $table->decimal('declared_amount', 15, 2)->nullable();
            $table->timestamps();
            $table->index(['saln_filing_id', 'section']);
        });

        Schema::create('ipcrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('overall_rating', 4, 2)->nullable();
            $table->json('employee_snapshot')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'period_start', 'period_end']);
        });
        Schema::create('ipcr_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('category');
            $table->text('task');
            $table->timestamps();
        });
        Schema::create('ipcr_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_output_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->timestamps();
        });
        Schema::create('ipcr_accomplishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_indicator_id')->constrained()->cascadeOnDelete();
            $table->text('accomplishment');
            $table->unsignedTinyInteger('quality')->nullable();
            $table->unsignedTinyInteger('efficiency')->nullable();
            $table->unsignedTinyInteger('timeliness')->nullable();
            $table->foreignId('rated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('concerns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->text('response')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('staff_acknowledged_at')->nullable();
            $table->timestamps();
        });
        Schema::create('concern_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concern_id')->constrained()->cascadeOnDelete();
            $table->foreignId('private_file_id')->constrained()->restrictOnDelete();
            $table->string('side', 20);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['concern_id', 'private_file_id']);
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->boolean('agency_wide')->default(true)->after('type');
            $table->time('suspension_time')->nullable()->after('agency_wide');
            $table->string('source')->nullable()->after('suspension_time');
            $table->timestamp('archived_at')->nullable()->after('source')->index();
        });
        Schema::create('holiday_user', function (Blueprint $table) {
            $table->foreignId('holiday_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->primary(['holiday_id', 'user_id']);
        });

        Schema::create('personnel_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 60);
            $table->unsignedSmallInteger('document_year');
            $table->string('period', 20)->default('annual');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'document_type', 'document_year']);
        });
        Schema::create('personnel_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('private_file_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['personnel_document_versions', 'personnel_documents', 'holiday_user', 'concern_attachments', 'concerns', 'ipcr_accomplishments', 'ipcr_indicators', 'ipcr_outputs', 'ipcrs', 'saln_entries', 'saln_filings', 'division_learning_plan_lines', 'division_learning_plans', 'idp_items', 'idp_plans', 'training_requirement_links', 'training_requirements'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('holidays', fn (Blueprint $table) => $table->dropIndex('holidays_archived_at_index'));
        Schema::table('holidays', fn (Blueprint $table) => $table->dropColumn(['agency_wide', 'suspension_time', 'source', 'archived_at']));
    }
};
