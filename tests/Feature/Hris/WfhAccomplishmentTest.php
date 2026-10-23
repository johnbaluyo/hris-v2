<?php

namespace Tests\Feature\Hris;

use App\Models\Delegation;
use App\Models\Division;
use App\Models\LeaveRequest;
use App\Models\RawPunch;
use App\Models\User;
use App\Models\WfhAccomplishment;
use App\Models\WfhReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WfhAccomplishmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_fallbacks_use_latest_full_day_leave_before_onsite_and_preserve_unknown_evidence(): void
    {
        $division = Division::create(['name' => 'Fallback division']);
        $chief = User::factory()->create(['role' => 'division_chief', 'division_id' => $division->id]);
        $submitter = User::factory()->create(['division_id' => $division->id, 'employee_type' => 'Permanent']);
        $absent = User::factory()->create(['division_id' => $division->id, 'employee_type' => 'Permanent', 'atms_id' => '1234']);
        $wfhOnly = User::factory()->create(['division_id' => $division->id, 'employee_type' => 'Permanent', 'atms_id' => '5678']);
        WfhAccomplishment::create(['user_id' => $submitter->id, 'division_id' => $division->id, 'wfh_date' => '2026-10-21', 'accomplishment' => 'Approved work', 'status' => 'approved']);
        $leave = LeaveRequest::create(['user_id' => $absent->id, 'leave_type_id' => 1, 'filed_on' => '2026-10-20', 'requested_days' => '1.000', 'created_by' => $absent->id]);
        $leave->dates()->create(['leave_date' => '2026-10-21', 'day_fraction' => '1.000']);
        $leave->statuses()->create(['request_type' => 'LEAVE', 'status' => 'Approved', 'actor_id' => $chief->id, 'created_at' => now()]);
        RawPunch::create(['user_id' => $absent->id, 'atms_id' => '001234', 'punched_at' => '2026-10-21 08:00:00', 'device' => 'READER']);
        RawPunch::create(['user_id' => $wfhOnly->id, 'atms_id' => '5678', 'punched_at' => '2026-10-21 08:00:00', 'device' => 'WEB WFH']);
        $payload = ['division_id' => $division->id, 'wfh_date' => '2026-10-21'];
        $this->actingAs($chief)->postJson('/wfh-accomplishments/consolidate', $payload)->assertOk();
        $report = WfhReport::firstOrFail();
        $this->assertSame('on approved leave', $report->lines()->where('user_id', $absent->id)->first()->snapshot['accomplishment']);
        $this->assertSame('no record', $report->lines()->where('user_id', $wfhOnly->id)->first()->snapshot['accomplishment']);
        $leave->statuses()->create(['request_type' => 'LEAVE', 'status' => 'Cancelled', 'actor_id' => $absent->id, 'created_at' => now()]);
        $this->postJson('/wfh-accomplishments/consolidate', $payload)->assertOk();
        $this->assertSame('worked onsite', $report->lines()->where('user_id', $absent->id)->first()->snapshot['accomplishment']);
    }

    public function test_dated_delegates_cannot_review_their_own_submission_or_act_after_expiry(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00', 'Asia/Manila'));
        $division = Division::create(['name' => 'Delegated division']);
        $delegate = User::factory()->create(['role' => 'staff', 'division_id' => $division->id, 'employee_type' => 'Permanent']);
        $employee = User::factory()->create(['role' => 'staff', 'division_id' => $division->id, 'employee_type' => 'Permanent']);
        $hr = User::factory()->create(['role' => 'hr']);
        Delegation::create(['user_id' => $delegate->id, 'division_id' => $division->id, 'ability' => 'division-chief.oic', 'period_start' => '2026-10-01', 'period_end' => '2026-10-23', 'actor_id' => $hr->id]);
        $payload = ['wfh_date' => '2026-10-21', 'accomplishment' => 'Example delegated work'];
        $this->actingAs($delegate)->postJson('/wfh-accomplishments', $payload)->assertCreated();
        $own = WfhAccomplishment::firstOrFail();
        $this->postJson('/wfh-accomplishments/'.$own->id.'/transition', ['action' => 'approve'])->assertForbidden();
        $this->postJson('/wfh-accomplishments', $payload + ['employee_id' => $employee->id])->assertCreated();
        $other = WfhAccomplishment::where('user_id', $employee->id)->firstOrFail();
        $this->travel(1)->days();
        $this->postJson('/wfh-accomplishments/'.$other->id.'/transition', ['action' => 'approve'])->assertForbidden();
    }

    public function test_return_resubmit_consolidate_sign_and_head_approval_freeze_report_contents(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00', 'Asia/Manila'));
        $division = Division::create(['name' => 'Original Division', 'acronym' => 'OD']);
        $employee = User::factory()->create(['name' => 'Original Employee', 'division_id' => $division->id, 'employee_type' => 'Permanent']);
        $chief = User::factory()->create(['role' => 'division_chief', 'division_id' => $division->id]);
        $head = User::factory()->create(['role' => 'executive_director']);
        $payload = ['wfh_date' => '2026-10-21', 'accomplishment' => 'Original work accomplished'];
        $this->actingAs($employee)->get('/wfh-accomplishments')->assertOk();
        $this->postJson('/wfh-accomplishments', $payload)->assertCreated();
        $record = WfhAccomplishment::firstOrFail();
        $this->postJson('/wfh-accomplishments', $payload)->assertUnprocessable();
        $this->postJson('/wfh-accomplishments/'.$record->id.'/transition', ['action' => 'approve'])->assertForbidden();
        $this->actingAs($chief)->postJson('/wfh-accomplishments/'.$record->id.'/transition', ['action' => 'return'])->assertUnprocessable();
        $this->postJson('/wfh-accomplishments/'.$record->id.'/transition', ['action' => 'return', 'reason' => 'Add detail'])->assertOk();
        $this->actingAs($employee)->putJson('/wfh-accomplishments/'.$record->id, $payload)->assertCreated();
        $this->assertNull($record->fresh()->return_remarks);
        $this->actingAs($chief)->postJson('/wfh-accomplishments/'.$record->id.'/transition', ['action' => 'approve'])->assertOk();
        $this->postJson('/wfh-accomplishments/consolidate', ['division_id' => $division->id, 'wfh_date' => '2026-10-21'])->assertOk();
        $report = WfhReport::firstOrFail();
        $url = '/wfh-accomplishments/reports/'.$report->id.'/transition';
        $this->postJson($url, ['action' => 'sign'])->assertOk();
        $this->postJson($url, ['action' => 'sign'])->assertOk();
        $this->actingAs($employee)->putJson('/wfh-accomplishments/'.$record->id, $payload)->assertUnprocessable();
        $other = User::factory()->create(['division_id' => $division->id, 'employee_type' => 'Permanent']);
        $this->actingAs($other)->postJson('/wfh-accomplishments', $payload)->assertUnprocessable();
        $this->get('/wfh-accomplishments/reports/'.$report->id.'/print')->assertForbidden();
        $this->actingAs($head)->postJson($url, ['action' => 'approve'])->assertOk();
        $this->postJson($url, ['action' => 'approve'])->assertOk();
        $employee->forceFill(['name' => 'Renamed Employee', 'division_id' => null, 'termination_date' => '2026-10-22'])->save();
        $division->update(['name' => 'Renamed Division']);
        $this->get('/wfh-accomplishments/reports/'.$report->id.'/print')->assertOk()->assertSee('Original Employee')->assertSee('Original Division')->assertSee('Original work accomplished')->assertDontSee('Renamed Employee');
        $this->get('/wfh-accomplishments/agency/2026-10-21/print')->assertOk()->assertSee('Original work accomplished');
        $this->assertSame(1, $report->fresh()->submitted_count);
    }

    public function test_dates_intern_exclusion_and_cross_division_chief_scope_are_enforced(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00', 'Asia/Manila'));
        $division = Division::create(['name' => 'Example Division', 'acronym' => 'ED']);
        $otherDivision = Division::create(['name' => 'Other Division', 'acronym' => 'OD']);
        $employee = User::factory()->create(['division_id' => $division->id, 'employee_type' => 'Permanent']);
        $chief = User::factory()->create(['role' => 'division_chief', 'division_id' => $otherDivision->id]);
        $payload = ['wfh_date' => '2026-10-21', 'accomplishment' => 'Example work'];
        $this->actingAs($employee)->postJson('/wfh-accomplishments', array_merge($payload, ['wfh_date' => '2026-10-20']))->assertUnprocessable();
        $this->postJson('/wfh-accomplishments', array_merge($payload, ['wfh_date' => '2026-10-28']))->assertUnprocessable();
        $this->postJson('/wfh-accomplishments', $payload)->assertCreated();
        $record = WfhAccomplishment::firstOrFail();
        $this->actingAs($chief)->getJson('/wfh-accomplishments/data')->assertJsonCount(0, 'data');
        $this->postJson('/wfh-accomplishments/'.$record->id.'/transition', ['action' => 'approve'])->assertForbidden();
        $this->postJson('/wfh-accomplishments', $payload + ['employee_id' => $employee->id])->assertForbidden();
        $this->actingAs(User::factory()->create(['employee_type' => 'Intern', 'division_id' => $division->id]))->postJson('/wfh-accomplishments', $payload)->assertUnprocessable();
    }
}
