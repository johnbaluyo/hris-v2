<?php

namespace Tests\Feature\Hris;

use App\Models\Division;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** @return array<string, mixed> */
    private function staffData(): array
    {
        return ['first_name' => 'Avery', 'last_name' => 'Example', 'email' => 'avery@example.test', 'employee_type' => 'Permanent',
            'atms_id' => '0009001', 'date_hired' => '2020-01-01', 'assume_date' => today('Asia/Manila')->toDateString(), 'workweek' => 5, 'birthday' => '1990-02-01'];
    }

    public function test_public_registration_is_closed_on_both_methods(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'Injected', 'email' => 'injected@example.test', 'password' => 'password'])->assertNotFound();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_archived_and_terminated_accounts_cannot_authenticate_or_keep_a_session(): void
    {
        $archived = User::factory()->create(['is_archived' => true]);
        $this->post('/login', ['email' => $archived->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $terminated = User::factory()->create(['termination_date' => today('Asia/Manila')]);
        $this->post('/login', ['email' => $terminated->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->actingAs($archived)->getJson('/hr/staff/data')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_login_failures_are_generic_and_throttled(): void
    {
        $user = User::factory()->create();
        $known = $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable()->json('errors.email');
        $unknown = $this->postJson('/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertUnprocessable()->json('errors.email');
        $this->assertSame($known, $unknown);
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong']);
        }
        $response->assertTooManyRequests();
    }

    public function test_staff_cannot_call_hr_endpoints_or_grant_themselves_roles(): void
    {
        $employee = User::factory()->create();
        $this->actingAs($employee)->getJson('/hr/staff/data')->assertForbidden();
        $this->postJson('/hr/staff', $this->staffData() + ['role' => 'system_admin'])->assertForbidden();
        $this->postJson('/organization/divisions', ['name' => 'Unauthorized'])->assertForbidden();
    }

    public function test_hr_creates_a_normalized_employee_without_a_shared_password(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $division = Division::create(['name' => 'Research']);
        $position = Position::create(['division_id' => $division->id, 'title' => 'Analyst']);
        $this->actingAs($hr)->postJson('/hr/staff', $this->staffData() + ['division_id' => $division->id, 'position_id' => $position->id])->assertCreated();
        $employee = User::where('email', 'avery@example.test')->firstOrFail();
        $this->assertSame('9001', $employee->atms_id);
        $this->assertSame('Avery Example', $employee->name);
        $this->assertDatabaseHas('work_schedules', ['user_id' => $employee->id, 'workweek' => 5]);
        $this->assertDatabaseHas('audit_events', ['action' => 'staff.created', 'subject_id' => $employee->id]);
        $this->assertFalse(Hash::check('password', $employee->password));
    }

    public function test_assignment_history_is_atomic_and_does_not_repeat_for_unchanged_positions(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $division = Division::create(['name' => 'Research']);
        $first = Position::create(['title' => 'Analyst', 'division_id' => $division->id, 'plantilla' => true]);
        $second = Position::create(['title' => 'Senior analyst', 'division_id' => $division->id]);
        $data = $this->staffData() + ['division_id' => $division->id, 'position_id' => $first->id];
        $this->actingAs($hr)->postJson('/hr/staff', $data)->assertCreated();
        $employee = User::where('email', $data['email'])->firstOrFail();
        $this->putJson('/hr/staff/'.$employee->id, $data)->assertOk();
        $this->assertSame(1, PositionAssignment::where('user_id', $employee->id)->count());
        $this->assertSame(1, $employee->pdsEntries()->where('section', 'personal')->count());
        $data['position_id'] = $second->id;
        $this->putJson('/hr/staff/'.$employee->id, $data)->assertOk();
        $this->assertDatabaseHas('position_assignments', ['user_id' => $employee->id, 'old_position_id' => $first->id, 'position_id' => $second->id, 'actor_id' => $hr->id]);
        $this->assertSame(2, PositionAssignment::where('user_id', $employee->id)->count());
    }

    public function test_duplicate_identity_and_exclusive_occupancy_fail_at_save_time(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $division = Division::create(['name' => 'Research']);
        $position = Position::create(['title' => 'Chief', 'division_id' => $division->id, 'plantilla' => true]);
        User::factory()->create(['division_id' => $division->id, 'position_id' => $position->id, 'atms_id' => '9001']);
        $this->actingAs($hr)->postJson('/hr/staff', $this->staffData())->assertUnprocessable()->assertJsonValidationErrors('atms_id');
        $data = $this->staffData();
        $data['atms_id'] = '9002';
        $data += ['division_id' => $division->id, 'position_id' => $position->id];
        $this->postJson('/hr/staff', $data)->assertUnprocessable()->assertJsonValidationErrors('position_id');
        $this->assertDatabaseMissing('users', ['email' => $data['email']]);
    }

    public function test_null_termination_and_numeric_zero_are_not_filtered_away(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $employee = User::factory()->create(['termination_date' => today()->addDay()]);
        $data = $this->staffData();
        $data['email'] = $employee->email;
        $data['termination_date'] = null;
        $division = Division::create(['name' => 'Research']);
        $position = Position::create(['division_id' => $division->id, 'title' => 'Analyst']);
        $data += ['division_id' => $division->id, 'position_id' => $position->id];
        $this->actingAs($hr)->putJson('/hr/staff/'.$employee->id, $data)->assertOk();
        $this->assertNull($employee->fresh()->termination_date);
        $this->patchJson('/hr/staff/'.$employee->id.'/archive', ['is_archived' => true])->assertOk();
        $this->assertTrue($employee->fresh()->is_archived);
        $this->patchJson('/hr/staff/'.$employee->id.'/archive', ['is_archived' => false])->assertOk();
        $this->assertFalse($employee->fresh()->is_archived);
        $this->assertDatabaseHas('users', ['id' => $employee->id]);
    }

    public function test_directory_search_filter_and_ten_row_pagination(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        User::factory()->count(12)->create(['name' => 'Findable Example']);
        $this->actingAs($hr)->getJson('/hr/staff/data?search=Findable')->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('total', 12);
        $this->getJson('/hr/staff/data?search=Findable&page=99')->assertOk()->assertJsonPath('current_page', 2)->assertJsonCount(2, 'data');
    }

    public function test_inclusive_delegations_are_scoped_and_overlap_is_rejected(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $division = Division::create(['name' => 'Research']);
        $delegate = User::factory()->create();
        $employee = User::factory()->create(['division_id' => $division->id]);
        $other = User::factory()->create();
        $data = ['user_id' => $delegate->id, 'division_id' => $division->id, 'ability' => 'attendance.division', 'period_start' => today()->toDateString(), 'period_end' => today()->toDateString()];
        $this->actingAs($hr)->postJson('/delegations', $data)->assertCreated();
        $this->postJson('/delegations', $data)->assertUnprocessable();
        $this->actingAs($delegate)->getJson('/attendance/'.$employee->id.'/data')->assertOk();
        $this->getJson('/attendance/'.$other->id.'/data')->assertForbidden();
        $this->travel(1)->days();
        $this->getJson('/attendance/'.$employee->id.'/data')->assertForbidden();
        $this->assertDatabaseCount('delegations', 1);
    }

    public function test_all_module_screens_render_for_authorized_users(): void
    {
        $user = User::factory()->create(['role' => 'system_admin']);
        DB::table('ability_grants')->insert(['user_id' => $user->id, 'ability' => 'staff.manage']);
        $this->actingAs($user);
        foreach (['/dashboard', '/hr/staff', '/organization/divisions', '/organization/positions', '/delegations', '/profile', '/calendar', '/portal/manage', '/attendance'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_new_positions_and_assigned_position_edits_accept_browser_string_division_ids(): void
    {
        $admin = User::factory()->create(['role' => 'system_admin']);
        $division = Division::create(['name' => 'Research']);
        $position = Position::create(['title' => 'Analyst', 'division_id' => $division->id]);
        $employee = User::factory()->create(['division_id' => $division->id, 'position_id' => $position->id]);
        $data = ['id' => null, 'title' => 'Research Assistant', 'division_id' => (string) $division->id,
            'code' => 'RESEARCH-NEW', 'classification' => 'Staff', 'plantilla' => false, 'status' => 'Open for Application'];

        $this->actingAs($admin)->postJson('/organization/positions', $data)->assertOk();
        $this->assertDatabaseCount('positions', 2);
        $this->assertDatabaseHas('positions', ['title' => 'Research Assistant', 'code' => 'RESEARCH-NEW', 'division_id' => $division->id]);

        $this->postJson('/organization/positions', [
            'id' => (string) $position->id, 'title' => 'Senior Analyst', 'division_id' => (string) $division->id,
            'classification' => 'Staff', 'plantilla' => false, 'status' => 'Filled Up',
        ])->assertOk();
        $this->assertDatabaseCount('positions', 2);
        $this->assertDatabaseHas('positions', ['id' => $position->id, 'title' => 'Senior Analyst', 'division_id' => $division->id]);
        $this->assertSame($position->id, $employee->fresh()->position_id);
    }

    public function test_an_assigned_position_still_cannot_move_to_a_different_division(): void
    {
        $admin = User::factory()->create(['role' => 'system_admin']);
        $division = Division::create(['name' => 'Research']);
        $other = Division::create(['name' => 'Operations']);
        $position = Position::create(['title' => 'Analyst', 'division_id' => $division->id]);
        $employee = User::factory()->create(['division_id' => $division->id, 'position_id' => $position->id]);

        $this->actingAs($admin)->postJson('/organization/positions', [
            'id' => (string) $position->id, 'title' => 'Moved Analyst', 'division_id' => (string) $other->id,
            'classification' => 'Staff', 'plantilla' => false, 'status' => 'Filled Up',
        ])->assertUnprocessable()->assertJsonValidationErrors('division_id');
        $this->assertDatabaseCount('positions', 1);
        $this->assertDatabaseHas('positions', ['id' => $position->id, 'title' => 'Analyst', 'division_id' => $division->id]);
        $this->assertSame($division->id, $employee->fresh()->division_id);
        $this->assertDatabaseCount('audit_events', 0);
    }
}
