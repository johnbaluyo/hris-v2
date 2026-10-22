<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('request_type', 20);
            $table->unsignedBigInteger('request_id');
            $table->string('status', 50);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at');
            $table->index(['request_type', 'request_id', 'id']);
        });

        Schema::create('official_businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('filed_on');
            $table->date('business_date')->index();
            $table->unsignedTinyInteger('type');
            $table->time('departure_time')->nullable();
            $table->time('return_time')->nullable();
            $table->string('origin')->nullable();
            $table->string('destination');
            $table->boolean('go_directly')->default(false);
            $table->boolean('return_to_office')->default(true);
            $table->text('purpose');
            $table->text('amendment_reason')->nullable();
            $table->foreignId('attachment_file_id')->nullable()->constrained('private_files')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'business_date']);
        });
        Schema::create('official_business_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('official_business_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->json('before');
            $table->text('reason');
            $table->timestamp('created_at');
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('filed_on');
            $table->unsignedTinyInteger('day_type');
            $table->time('start_time');
            $table->unsignedInteger('requested_minutes');
            $table->unsignedTinyInteger('overtime_type');
            $table->text('purpose');
            $table->foreignId('attachment_file_id')->nullable()->constrained('private_files')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('overtime_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_request_id')->constrained()->cascadeOnDelete();
            $table->date('overtime_date')->index();
            $table->unsignedInteger('rendered_minutes')->nullable();
            $table->unique(['overtime_request_id', 'overtime_date']);
        });

        Schema::create('coc_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('direction', 10);
            $table->unsignedInteger('minutes');
            $table->unsignedInteger('remaining_minutes')->default(0);
            $table->date('scope_date');
            $table->date('expires_on')->nullable()->index();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->text('remarks');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['user_id', 'direction', 'scope_date']);
        });
        Schema::create('cto_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('filed_on');
            $table->unsignedInteger('total_minutes');
            $table->unsignedInteger('balance_snapshot_minutes');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('cto_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cto_request_id')->constrained()->cascadeOnDelete();
            $table->date('cto_date')->index();
            $table->string('portion', 10);
            $table->unsignedInteger('minutes');
            $table->unique(['cto_request_id', 'cto_date', 'portion']);
        });
        Schema::create('coc_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cto_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('coc_entry_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('minutes');
            $table->timestamp('reversed_at')->nullable();
            $table->unique(['cto_request_id', 'coc_entry_id']);
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('accumulation', 30)->default('carry');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        DB::table('leave_types')->insert([
            ['id' => 1, 'code' => 'VL', 'name' => 'Vacation Leave', 'accumulation' => 'carry', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'SL', 'name' => 'Sick Leave', 'accumulation' => 'carry', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'code' => 'FL', 'name' => 'Mandatory/Forced Leave', 'accumulation' => 'yearly', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'code' => 'SPL', 'name' => 'Special Privilege Leave', 'accumulation' => 'yearly', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'code' => 'SOLO', 'name' => 'Solo Parent Leave', 'accumulation' => 'yearly', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'code' => 'EL', 'name' => 'Emergency Leave', 'accumulation' => 'yearly', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 14, 'code' => 'WL', 'name' => 'Wellness Leave', 'accumulation' => 'yearly', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('filed_on');
            $table->string('detail_code', 20)->nullable();
            $table->text('details')->nullable();
            $table->string('commutation', 30)->nullable();
            $table->decimal('requested_days', 8, 3);
            $table->decimal('paid_days', 8, 3)->nullable();
            $table->decimal('unpaid_days', 8, 3)->nullable();
            $table->text('recommendation_reason')->nullable();
            $table->text('return_message')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('leave_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->date('leave_date')->index();
            $table->decimal('day_fraction', 4, 3)->default(1);
            $table->unique(['leave_request_id', 'leave_date']);
        });
        Schema::create('leave_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->string('direction', 10);
            $table->decimal('days', 8, 3);
            $table->date('scope_date');
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->text('activity');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['user_id', 'leave_type_id', 'scope_date']);
        });

        Schema::create('salary_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('grade');
            $table->unsignedTinyInteger('step');
            $table->decimal('monthly_salary', 14, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unique(['grade', 'step', 'effective_from']);
        });
        Schema::table('positions', function (Blueprint $table) {
            $table->unsignedTinyInteger('salary_grade')->nullable()->after('classification');
        });
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('payroll_number')->unique();
            $table->string('fund_cluster');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('Draft');
            $table->string('calculation_version', 40)->default('manual-reviewed-v1');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->json('employee_snapshot');
            $table->decimal('basic_salary', 14, 2);
            $table->decimal('pera', 14, 2)->default(0);
            $table->decimal('rata', 14, 2)->default(0);
            $table->decimal('other_compensation', 14, 2)->default(0);
            $table->decimal('statutory_deductions', 14, 2)->default(0);
            $table->decimal('other_deductions', 14, 2)->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('undertime_minutes')->default(0);
            $table->decimal('absence_days', 8, 3)->default(0);
            $table->decimal('gross', 14, 2);
            $table->decimal('total_deductions', 14, 2);
            $table->decimal('net', 14, 2);
            $table->json('calculation_inputs');
            $table->timestamps();
            $table->unique(['payroll_id', 'user_id']);
        });
        Schema::create('loan_categories', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('name');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'name']);
        });
        Schema::create('payroll_loan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->unique(['payroll_line_id', 'loan_category_id']);
        });
    }

    public function down(): void
    {
        foreach (['payroll_loan_lines', 'loan_categories', 'payroll_lines', 'payrolls', 'salary_grades', 'leave_ledger_entries', 'leave_dates', 'leave_requests', 'leave_types', 'coc_allocations', 'cto_dates', 'cto_requests', 'coc_entries', 'overtime_dates', 'overtime_requests', 'official_business_revisions', 'official_businesses', 'request_statuses'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('positions', fn (Blueprint $table) => $table->dropColumn('salary_grade'));
    }
};
