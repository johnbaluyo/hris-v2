<?php

namespace Tests\Feature\Hris;

use App\Models\Division;
use App\Models\IdpItem;
use App\Models\IpcrAccomplishment;
use App\Models\PdsEntry;
use App\Models\Position;
use App\Models\RequestStatus;
use App\Models\TrainingRequirement;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Attendance\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DevelopmentRecordsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{staff: User, other: User, chief: User, hr: User, position: Position, division: Division} */
    private function actors(): array
    {
        $division = Division::create(['name' => 'Development Test Division', 'acronym' => 'DTD']);
        $position = Position::create(['division_id' => $division->id, 'title' => 'Development Analyst', 'classification' => 'Staff', 'status' => 'active']);
        $otherPosition = Position::create(['division_id' => $division->id, 'title' => 'Other Development Analyst', 'classification' => 'Staff', 'status' => 'active']);

        return [
            'staff' => User::factory()->create(['role' => 'staff', 'division_id' => $division->id, 'position_id' => $position->id, 'workweek' => 5]),
            'other' => User::factory()->create(['role' => 'staff', 'division_id' => $division->id, 'position_id' => $otherPosition->id, 'workweek' => 5]),
            'chief' => User::factory()->create(['role' => 'division_chief', 'division_id' => $division->id]),
            'hr' => User::factory()->create(['role' => 'hr']),
            'position' => $position,
            'division' => $division,
        ];
    }

    public function test_module_pages_render_for_authorized_users(): void
    {
        $actors = $this->actors();
        foreach (['/learning-development', '/idp', '/saln', '/ipcr', '/concerns', '/personnel-documents'] as $path) {
            $this->actingAs($actors['staff'])->get($path)->assertOk();
        }
        $this->actingAs($actors['hr'])->get('/hr/references')->assertOk();
        $this->actingAs($actors['staff'])->get('/hr/references')->assertForbidden();
    }

    public function test_idp_is_private_until_scoped_review_and_plan_lines_keep_stable_totals(): void
    {
        $actors = $this->actors();
        $requirement = TrainingRequirement::create(['position_id' => $actors['position']->id, 'category' => 3, 'title' => 'Secure application delivery', 'created_by' => $actors['hr']->id]);
        $response = $this->actingAs($actors['staff'])->postJson('/idp', ['plan_year' => 2027, 'coverage_years' => [2027, 2028], 'purpose' => 1,
            'items' => [['training_requirement_id' => $requirement->id, 'proposal' => 'Complete secure coding program', 'title' => 'Secure coding']]])->assertCreated();
        $id = $response->json('id');
        $stableItem = IdpItem::firstOrFail();
        $this->putJson("/idp/{$id}", ['plan_year' => 2027, 'coverage_years' => [2027, 2028], 'purpose' => 1,
            'items' => [['id' => $stableItem->id, 'training_requirement_id' => $requirement->id, 'proposal' => 'Updated secure coding program', 'title' => 'Secure coding'],
                ['training_requirement_id' => null, 'proposal' => 'Improve facilitation', 'title' => 'Facilitation workshop']]])->assertOk();
        $this->assertDatabaseHas('idp_items', ['id' => $stableItem->id, 'proposal' => 'Updated secure coding program']);
        $this->actingAs($actors['other'])->getJson('/idp/data')->assertJsonPath('total', 0);
        $this->actingAs($actors['staff'])->postJson("/idp/{$id}/submit")->assertOk();
        $this->actingAs($actors['chief'])->getJson('/idp/data')->assertJsonPath('total', 1);
        $this->postJson("/idp/{$id}/approve")->assertOk();

        $plan = $this->postJson('/learning-development/plans', ['division_id' => $actors['division']->id, 'plan_year' => 2027])->assertCreated()->json('id');
        $this->putJson("/learning-development/plans/{$plan}/lines", ['lines' => [['idp_item_id' => $stableItem->id, 'budget' => 12500.55, 'is_free' => false]]])->assertOk();
        $this->assertDatabaseHas('division_learning_plans', ['id' => $plan, 'total_budget' => 12500.55, 'staff_count' => 1]);
        $this->putJson("/learning-development/plans/{$plan}/lines", ['lines' => [['idp_item_id' => $stableItem->id, 'budget' => 99999, 'is_free' => true]]])->assertOk();
        $this->assertDatabaseHas('division_learning_plans', ['id' => $plan, 'total_budget' => 0, 'staff_count' => 1]);

        $training = PdsEntry::create(['user_id' => $actors['staff']->id, 'section' => 'learning', 'data' => ['title' => 'Secure coding'], 'actor_id' => $actors['staff']->id]);
        $this->actingAs($actors['hr'])->postJson("/learning-development/requirements/{$requirement->id}/training/{$training->id}")->assertOk();
        $this->actingAs($actors['staff'])->getJson("/learning-development/positions/{$actors['position']->id}/matrix")
            ->assertOk()->assertJsonCount(1, 'fulfilled')->assertJsonCount(0, 'unfulfilled');
        $this->actingAs($actors['other'])->getJson("/learning-development/positions/{$actors['position']->id}/matrix")->assertForbidden();
    }

    public function test_saln_totals_exact_cents_and_denies_other_staff(): void
    {
        $actors = $this->actors();
        $id = $this->actingAs($actors['staff'])->postJson('/saln', ['filing_type' => 1, 'compliance_type' => 1, 'compliance_date' => '2026-12-31'])->assertCreated()->json('id');
        foreach ([['real_property', '100.10'], ['personal_property', '50.25'], ['liability', '20.05']] as [$section, $amount]) {
            $this->postJson("/saln/{$id}/entries", ['section' => $section, 'data' => ['description' => $section], 'declared_amount' => $amount])->assertCreated();
        }
        $this->getJson("/saln/{$id}")->assertOk()->assertJsonPath('total_assets', '150.35')->assertJsonPath('net_worth', '130.30');
        $this->actingAs($actors['other'])->getJson("/saln/{$id}")->assertForbidden();
        $this->actingAs($actors['hr'])->getJson("/saln/{$id}")->assertOk();
    }

    public function test_ipcr_hierarchy_review_scope_and_server_rating(): void
    {
        $actors = $this->actors();
        $id = $this->actingAs($actors['staff'])->postJson('/ipcr', ['period_start' => '2026-01-01', 'period_end' => '2026-06-30'])->assertCreated()->json('id');
        $output = $this->postJson("/ipcr/{$id}/outputs", ['category' => 1, 'task' => 'Deliver tested services'])->assertCreated()->json('id');
        $indicator = $this->postJson("/ipcr/{$id}/outputs/{$output}/indicators", ['description' => 'Release accepted on schedule'])->assertCreated()->json('id');
        $accomplishment = $this->postJson("/ipcr/{$id}/indicators/{$indicator}/accomplishments", ['accomplishment' => 'Released and accepted'])->assertCreated()->json('id');
        $this->actingAs($actors['other'])->getJson('/ipcr/data')->assertJsonPath('total', 0);
        $this->actingAs($actors['staff'])->postJson("/ipcr/{$id}/submit")->assertOk();
        $this->actingAs($actors['chief'])->postJson("/ipcr/{$id}/accomplishments/{$accomplishment}/rate", ['quality' => 4, 'efficiency' => 4, 'timeliness' => 4])
            ->assertOk()->assertJsonPath('rating.overall', 1.6);
        $this->assertSame($actors['chief']->id, IpcrAccomplishment::findOrFail($accomplishment)->rated_by);
        $this->assertSame('For Review', RequestStatus::where('request_type', 'IPCR')->latest('id')->value('status'));
    }

    public function test_concerns_and_documents_have_private_files_versions_and_audited_transitions(): void
    {
        Storage::fake('local');
        $actors = $this->actors();
        $concern = $this->actingAs($actors['staff'])->post('/concerns', ['subject' => 'Workplace equipment', 'description' => 'A detailed equipment concern.',
            'attachment' => UploadedFile::fake()->create('same.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $this->actingAs($actors['other'])->getJson('/concerns/data')->assertJsonPath('total', 0);
        $this->actingAs($actors['hr'])->postJson("/concerns/{$concern}/transition", ['action' => 'acknowledge'])->assertOk();
        $this->postJson("/concerns/{$concern}/transition", ['action' => 'resolve', 'response' => 'Replacement has been issued.'])->assertOk();
        $this->actingAs($actors['staff'])->postJson("/concerns/{$concern}/transition", ['action' => 'staff_acknowledge'])->assertOk();
        $this->assertDatabaseHas('request_statuses', ['request_type' => 'CONCERN', 'request_id' => $concern, 'status' => 'Staff Acknowledged']);

        $document = $this->post('/personnel-documents', ['document_type' => 'SALN', 'document_year' => 2026, 'period' => 'annual',
            'file' => UploadedFile::fake()->create('same.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $this->actingAs($actors['other'])->getJson('/personnel-documents/data')->assertJsonPath('total', 0);
        $this->actingAs($actors['hr'])->postJson("/personnel-documents/{$document}/transition", ['action' => 'return', 'reason' => 'Replace the signature page.'])->assertOk();
        $this->actingAs($actors['staff'])->post('/personnel-documents', ['document_type' => 'SALN', 'document_year' => 2026, 'period' => 'annual',
            'file' => UploadedFile::fake()->create('same.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->assertDatabaseCount('personnel_document_versions', 2);
        $this->assertDatabaseHas('request_statuses', ['request_type' => 'UPLOADS', 'request_id' => $document, 'action' => 'resubmit']);
    }

    public function test_scoped_holidays_affect_only_covered_attendance_and_salary_periods_do_not_overlap(): void
    {
        $actors = $this->actors();
        foreach ([$actors['staff'], $actors['other']] as $employee) {
            WorkSchedule::create(['user_id' => $employee->id, 'starts_on' => '2026-01-01', 'workweek' => 5, 'actor_id' => $actors['hr']->id]);
        }
        $holiday = $this->actingAs($actors['hr'])->postJson('/hr/references/holidays', ['name' => 'Employee-specific special order', 'holiday_date' => '2026-10-26',
            'type' => 'Special Order', 'agency_wide' => false, 'source' => 'Office Order 10-2026', 'employee_ids' => [$actors['staff']->id]])->assertCreated()->json('id');
        $covered = app(AttendanceService::class)->rebuild($actors['staff'], '2026-10-26');
        $uncovered = app(AttendanceService::class)->rebuild($actors['other'], '2026-10-26');
        $this->assertFalse($covered->needs_review);
        $this->assertTrue($uncovered->needs_review);
        $this->assertContains($holiday, $covered->provenance['calendar_reference_ids']);
        $this->assertNotContains($holiday, $uncovered->provenance['calendar_reference_ids']);

        $this->actingAs($actors['hr'])->postJson('/hr/references/salaries', ['grade' => 10, 'step' => 1, 'monthly_salary' => 40000,
            'effective_from' => '2026-01-01', 'effective_to' => '2026-12-31'])->assertCreated();
        $this->postJson('/hr/references/salaries', ['grade' => 10, 'step' => 1, 'monthly_salary' => 41000,
            'effective_from' => '2026-06-01'])->assertUnprocessable()->assertJsonValidationErrors('effective_from');
    }
}
