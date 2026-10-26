<?php

namespace Tests\Feature\Hris;

use App\Models\CocEntry;
use App\Models\Division;
use App\Models\Holiday;
use App\Models\LeaveLedgerEntry;
use App\Models\OfficialBusiness;
use App\Models\RawPunch;
use App\Models\RequestStatus;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Attendance\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkforceRequestsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: User, 2: User, 3: User} */
    private function actors(): array
    {
        $division = Division::create(['name' => 'Example Division', 'acronym' => 'EX']);
        $staff = User::factory()->create(['division_id' => $division->id, 'role' => 'staff', 'workweek' => 5]);
        $chief = User::factory()->create(['division_id' => $division->id, 'role' => 'division_chief']);
        $hr = User::factory()->create(['division_id' => $division->id, 'role' => 'hr']);
        $ed = User::factory()->create(['role' => 'executive_director']);

        return [$staff, $chief, $hr, $ed];
    }

    public function test_module_pages_render_for_their_authorized_audiences(): void
    {
        [$staff, , $hr] = $this->actors();
        foreach (['/official-business', '/overtime', '/cto', '/leave'] as $path) {
            $this->actingAs($staff)->get($path)->assertOk();
        }
        $this->actingAs($hr)->get('/hr/payroll')->assertOk();
    }

    public function test_official_business_is_owner_scoped_and_has_guarded_one_level_approval(): void
    {
        [$staff, $chief] = $this->actors();
        $other = User::factory()->create();
        $this->actingAs($staff)->postJson('/official-business', ['business_date' => '2026-10-26', 'type' => 1, 'destination' => 'Regional office',
            'go_directly' => true, 'return_to_office' => false, 'purpose' => 'Documented coordination'])->assertCreated();
        $record = OfficialBusiness::firstOrFail();
        $this->assertDatabaseHas('request_statuses', ['request_type' => 'OB', 'request_id' => $record->id, 'status' => 'For DC Approval', 'actor_id' => $staff->id]);
        $this->actingAs($other)->getJson('/official-business/data')->assertJsonPath('total', 0);
        $this->postJson('/official-business/'.$record->id.'/transition', ['action' => 'approve_dc'])->assertForbidden();
        $this->actingAs($chief)->postJson('/official-business/'.$record->id.'/transition', ['action' => 'approve_dc'])->assertOk();
        $this->assertSame('Approved', RequestStatus::latest('id')->first()->status);
        $this->actingAs($staff)->getJson('/calendar/feed?calendar=personal&from=2026-10-01&to=2026-10-31')->assertOk()
            ->assertJsonFragment(['title' => 'Official business: Regional office']);
        $this->actingAs($staff)->get('/official-business/'.$record->id.'/print')->assertOk()->assertSee('FAD Form No. 01')->assertSee('CERTIFICATE OF APPEARANCE');
        $this->postJson('/official-business/'.$record->id.'/transition', ['action' => 'approve_dc'])->assertUnprocessable();
    }

    public function test_official_business_evidence_is_accepted_only_after_approval(): void
    {
        Storage::fake('local');
        [$staff, $chief] = $this->actors();
        $response = $this->actingAs($staff)->postJson('/official-business', ['business_date' => '2026-10-26', 'type' => 1, 'destination' => 'Regional office',
            'go_directly' => true, 'return_to_office' => false, 'purpose' => 'Documented coordination'])->assertCreated();
        $id = $response->json('id');
        $this->post('/official-business/'.$id.'/evidence', ['attachment' => UploadedFile::fake()->create('evidence.pdf', 50, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(409);
        $this->actingAs($chief)->postJson('/official-business/'.$id.'/transition', ['action' => 'approve_dc'])->assertOk();
        $this->actingAs($staff)->post('/official-business/'.$id.'/evidence', ['attachment' => UploadedFile::fake()->create('evidence.pdf', 50, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->assertNotNull(OfficialBusiness::findOrFail($id)->attachment_file_id);
    }

    public function test_only_latest_approved_request_state_changes_attendance_exemptions(): void
    {
        [$staff, $chief] = $this->actors();
        $staff->update(['atms_id' => '9090']);
        WorkSchedule::create(['user_id' => $staff->id, 'starts_on' => '2026-01-01', 'workweek' => 5, 'actor_id' => $staff->id]);
        foreach (['10:00:00', '17:00:00'] as $time) {
            RawPunch::create(['user_id' => $staff->id, 'atms_id' => '9090', 'punched_at' => '2026-10-26 '.$time, 'device' => 'READER']);
        }
        $response = $this->actingAs($staff)->postJson('/official-business', ['business_date' => '2026-10-26', 'type' => 1, 'destination' => 'Field',
            'go_directly' => true, 'return_to_office' => false, 'purpose' => 'Field coordination'])->assertCreated();
        $id = $response->json('id');
        $this->actingAs($chief)->postJson('/official-business/'.$id.'/transition', ['action' => 'approve_dc'])->assertOk();
        $approved = app(AttendanceService::class)->rebuild($staff, '2026-10-26');
        $this->assertSame(0, $approved->late_minutes);
        $this->assertSame(0, $approved->undertime_minutes);
        $this->actingAs($staff)->postJson('/official-business/'.$id.'/transition', ['action' => 'cancel'])->assertOk();
        $cancelled = app(AttendanceService::class)->rebuild($staff, '2026-10-26');
        $this->assertGreaterThan(0, $cancelled->late_minutes);
        $this->assertGreaterThan(0, $cancelled->undertime_minutes);
    }

    public function test_overtime_requires_dc_then_ed_and_does_not_create_coc_automatically(): void
    {
        [$staff, $chief, , $ed] = $this->actors();
        $this->actingAs($staff)->postJson('/overtime', ['dates' => ['2026-10-24'], 'day_type' => 1, 'start_time' => '08:00', 'requested_minutes' => 120,
            'overtime_type' => 3, 'purpose' => 'Authorized weekend deployment'])->assertCreated();
        $id = (int) DB::table('overtime_requests')->value('id');
        $this->actingAs($chief)->postJson('/overtime/'.$id.'/transition', ['action' => 'approve_dc'])->assertOk();
        $this->assertSame('For ED Approval', RequestStatus::latest('id')->first()->status);
        $this->actingAs($ed)->postJson('/overtime/'.$id.'/transition', ['action' => 'approve_ed'])->assertOk();
        $this->actingAs($staff)->get('/overtime/'.$id.'/print')->assertOk()->assertSee('REQUEST FOR AUTHORITY TO')->assertSee('DETAILS OF APPLICATION');
        $this->assertDatabaseCount('coc_entries', 0);
    }

    public function test_cto_derives_hours_consumes_fifo_once_and_restores_the_same_lot_on_cancel(): void
    {
        [$staff, $chief, $hr, $ed] = $this->actors();
        $lot = CocEntry::create(['user_id' => $staff->id, 'direction' => 'IN', 'minutes' => 600, 'remaining_minutes' => 600,
            'scope_date' => '2026-10-01', 'expires_on' => '2027-09-30', 'source_type' => 'adjustment', 'idempotency_key' => 'opening-example',
            'remarks' => 'Reviewed opening credit', 'actor_id' => $hr->id, 'created_at' => now()]);
        $response = $this->actingAs($staff)->postJson('/cto', ['dates' => [['date' => '2026-10-26', 'portion' => 'AM']]])->assertCreated();
        $id = $response->json('id');
        $this->assertDatabaseHas('cto_requests', ['id' => $id, 'total_minutes' => 240, 'balance_snapshot_minutes' => 600]);
        $this->get('/cto/'.$id.'/print')->assertOk()->assertSee('Compensatory Time - Off (CTO) Form')->assertSee('EARNED');
        $this->actingAs($hr)->postJson('/cto/'.$id.'/transition', ['action' => 'certify'])->assertOk();
        $this->actingAs($chief)->postJson('/cto/'.$id.'/transition', ['action' => 'approve_dc'])->assertOk();
        $this->actingAs($ed)->postJson('/cto/'.$id.'/transition', ['action' => 'approve_ed'])->assertOk();
        $this->assertSame(360, $lot->fresh()->remaining_minutes);
        $this->actingAs($ed)->postJson('/cto/'.$id.'/transition', ['action' => 'approve_ed'])->assertUnprocessable();
        $this->assertDatabaseCount('coc_allocations', 1);
        $this->actingAs($staff)->postJson('/cto/'.$id.'/transition', ['action' => 'cancel', 'reason' => 'Plans changed'])->assertOk();
        $this->assertSame(600, $lot->fresh()->remaining_minutes);
        $this->assertDatabaseHas('coc_entries', ['idempotency_key' => 'cto:'.$id.':reversal']);
    }

    public function test_leave_posts_one_exact_debit_and_one_cancellation_reversal(): void
    {
        [$staff, $chief, $hr, $ed] = $this->actors();
        $response = $this->actingAs($staff)->postJson('/leave', ['leave_type_id' => 1, 'commutation' => 'not_requested', 'detail_code' => '1', 'dates' => [
            ['date' => '2026-10-26'], ['date' => '2026-10-27'],
        ]])->assertCreated();
        $id = $response->json('id');
        $this->get('/leave/'.$id.'/print')->assertOk()->assertSee('APPLICATION FOR LEAVE')->assertSee('Civil Service Form No. 6');
        $this->actingAs($hr)->postJson('/leave/'.$id.'/transition', ['action' => 'certify'])->assertOk();
        $this->actingAs($chief)->postJson('/leave/'.$id.'/transition', ['action' => 'approve_dc'])->assertOk();
        $this->actingAs($ed)->postJson('/leave/'.$id.'/transition', ['action' => 'approve_ed', 'paid_days' => 1.5, 'unpaid_days' => .5])->assertOk();
        $debit = LeaveLedgerEntry::where('idempotency_key', 'leave:'.$id.':debit')->firstOrFail();
        $this->assertSame('1.500', $debit->days);
        $this->actingAs($staff)->postJson('/leave/'.$id.'/transition', ['action' => 'cancel'])->assertOk();
        $this->assertDatabaseHas('leave_ledger_entries', ['idempotency_key' => 'leave:'.$id.':reversal', 'direction' => 'IN']);
    }

    public function test_leave_actions_are_role_scoped_oic_is_delegated_and_history_is_returned(): void
    {
        [$staff, $chief, $hr, $director] = $this->actors();
        $otherStaff = User::factory()->create(['division_id' => $staff->division_id, 'role' => 'staff']);
        $response = $this->actingAs($staff)->postJson('/leave', ['leave_type_id' => 1, 'commutation' => 'not_requested', 'detail_code' => '1',
            'dates' => [['date' => '2026-10-26']]])->assertCreated();
        $id = $response->json('id');

        $ownerRow = collect($this->getJson('/leave/data')->json('data'))->firstWhere('id', $id);
        $this->assertSame(['cancel' => 'Cancel my application'], $ownerRow['available_actions']);
        $this->assertSame('filed', $ownerRow['action_history'][0]['action']);
        $this->actingAs($otherStaff)->postJson('/leave/'.$id.'/transition', ['action' => 'cancel'])->assertForbidden();

        $hrRow = collect($this->actingAs($hr)->getJson('/leave/data')->json('data'))->firstWhere('id', $id);
        $this->assertSame(['certify' => 'HR Certify', 'return' => 'Return to employee'], $hrRow['available_actions']);
        $this->actingAs($hr)->postJson('/leave/'.$id.'/transition', ['action' => 'certify'])->assertOk();

        $chiefRow = collect($this->actingAs($chief)->getJson('/leave/data')->json('data'))->firstWhere('id', $id);
        $this->assertSame(['return' => 'Return to employee', 'approve_dc' => 'Recommend as chief', 'reject_dc' => 'Disapprove as chief'], $chiefRow['available_actions']);
        $this->actingAs($hr)->postJson('/delegations', ['user_id' => $otherStaff->id, 'division_id' => $otherStaff->division_id, 'ability' => 'division-chief.oic',
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31'])->assertCreated();
        $actingChiefRow = collect($this->actingAs($otherStaff)->getJson('/leave/data')->json('data'))->firstWhere('id', $id);
        $this->assertSame(['return' => 'Return to employee', 'approve_dc' => 'Recommend as chief', 'reject_dc' => 'Disapprove as chief'], $actingChiefRow['available_actions']);
        $this->actingAs($chief)->postJson('/leave/'.$id.'/transition', ['action' => 'approve_dc'])->assertOk();
        $this->postJson('/leave/'.$id.'/transition', ['action' => 'approve_ed'])->assertForbidden();

        $directorRow = collect($this->actingAs($director)->getJson('/leave/data')->json('data'))->firstWhere('id', $id);
        $this->assertSame(['approve_ed' => 'Approve', 'reject_ed' => 'Disapprove'], $directorRow['available_actions']);
        $this->actingAs($hr)->postJson('/delegations', ['user_id' => $otherStaff->id, 'division_id' => $otherStaff->division_id, 'ability' => 'director.oic',
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31'])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->postJson('/delegations', ['user_id' => $chief->id, 'division_id' => $chief->division_id, 'ability' => 'director.oic',
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31'])->assertCreated();
        $this->assertSame('division_chief', $chief->role);
        $this->assertTrue($chief->hasAbility('requests.division'));
        $this->assertTrue($chief->hasAbility('requests.decide'));
        $oicRow = collect($this->actingAs($chief)->getJson('/leave/data')->json('data'))->firstWhere('id', $id);
        $this->assertSame(['approve_ed' => 'Approve as ED/OIC', 'reject_ed' => 'Disapprove as ED/OIC'], $oicRow['available_actions']);
        $this->postJson('/leave/'.$id.'/transition', ['action' => 'approve_ed', 'paid_days' => 1, 'unpaid_days' => 0])->assertOk();

        $history = collect($this->getJson('/leave/data')->json('data'))->firstWhere('id', $id)['action_history'];
        $this->assertSame(['approve_ed', 'approve_dc', 'certify', 'filed'], array_column($history, 'action'));
    }

    public function test_cao_actions_exist_only_for_the_directors_leave(): void
    {
        [$staff, , $hr, $director] = $this->actors();
        $cao = User::factory()->create(['role' => 'chief_admin_officer']);

        $staffLeave = $this->actingAs($staff)->postJson('/leave', ['leave_type_id' => 1, 'commutation' => 'not_requested', 'detail_code' => '1',
            'dates' => [['date' => '2026-10-26']]])->assertCreated()->json('id');
        $staffRow = collect($this->actingAs($cao)->getJson('/leave/data')->json('data'))->firstWhere('id', $staffLeave);
        $this->assertSame([], $staffRow['available_actions']);

        $directorLeave = $this->actingAs($director)->postJson('/leave', ['leave_type_id' => 1, 'commutation' => 'not_requested', 'detail_code' => '1',
            'dates' => [['date' => '2026-10-27']]])->assertCreated()->json('id');
        $this->actingAs($hr)->postJson('/leave/'.$directorLeave.'/transition', ['action' => 'certify'])->assertOk();
        $caoRow = collect($this->actingAs($cao)->getJson('/leave/data')->json('data'))->firstWhere('id', $directorLeave);
        $this->assertSame(['witness' => 'CAO witness and approve', 'reject_witness' => 'CAO disapprove'], $caoRow['available_actions']);
    }

    public function test_leave_details_are_type_specific_and_non_working_dates_require_acknowledgement(): void
    {
        [$staff] = $this->actors();
        $staff->forceFill(['workweek' => 4])->save();
        Holiday::create(['name' => 'Example Holiday', 'holiday_date' => '2026-10-22', 'type' => 'Regular']);
        $this->actingAs($staff)->postJson('/leave/date-check', ['dates' => ['2026-10-22', '2026-10-23']])->assertOk()
            ->assertJsonCount(2, 'warnings')->assertJsonFragment(['date' => '2026-10-22', 'reason' => 'Example Holiday']);
        $payload = ['leave_type_id' => 1, 'commutation' => 'requested', 'detail_code' => '1', 'dates' => [['date' => '2026-10-23']]];
        $this->postJson('/leave', $payload)->assertUnprocessable()->assertJsonValidationErrors('dates');
        $this->postJson('/leave', $payload + ['acknowledge_non_working_days' => true])->assertCreated();
        $this->postJson('/leave', ['leave_type_id' => 1, 'commutation' => 'not_requested', 'detail_code' => '3', 'dates' => [['date' => '2026-10-26']]])
            ->assertUnprocessable()->assertJsonValidationErrors('detail_code');
        $this->postJson('/leave', ['leave_type_id' => 1, 'commutation' => 'not_requested', 'detail_code' => '7', 'requested_days' => 3])->assertCreated();
    }
}
