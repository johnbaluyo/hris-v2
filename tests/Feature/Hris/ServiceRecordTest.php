<?php

namespace Tests\Feature\Hris;

use App\Models\AuditEvent;
use App\Models\Division;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use App\Services\EmploymentHistory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServiceRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function position(string $title = 'Analyst'): Position
    {
        $division = Division::firstOrCreate(['name' => 'Research']);

        return Position::create(['title' => $title, 'division_id' => $division->id, 'classification' => 'Staff', 'plantilla' => false]);
    }

    /** @return array<string, mixed> */
    private function data(Position $position): array
    {
        return ['first_name' => 'Avery', 'last_name' => 'Example', 'email' => 'service-record@example.test',
            'employee_type' => 'Permanent', 'date_hired' => '2020-01-01', 'termination_date' => null,
            'assume_date' => '2020-01-01', 'division_id' => $position->division_id, 'position_id' => $position->id, 'workweek' => 5];
    }

    public function test_service_record_preserves_hiring_transfer_termination_and_rehiring_without_duplicate_events(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $first = $this->position();
        $second = $this->position('Senior Analyst');
        $third = $this->position('Research Officer');
        $data = $this->data($first);
        $this->actingAs($hr)->postJson('/hr/staff', $data)->assertCreated();
        $employee = User::where('email', $data['email'])->firstOrFail();
        $url = '/hr/staff/'.$employee->id;
        $history = app(EmploymentHistory::class);
        $this->putJson($url, $data)->assertOk();
        $this->assertSame(1, $history->query($employee)->count());

        $data['position_id'] = $second->id;
        $data['assume_date'] = '2021-06-01';
        $this->putJson($url, $data)->assertOk();
        $this->assertDatabaseHas('positions', ['id' => $first->id, 'status' => 'Open for Application']);
        $this->assertDatabaseHas('positions', ['id' => $second->id, 'status' => 'Filled Up']);
        $first->update(['title' => 'Renamed later']);

        $data['termination_date'] = '2022-12-31';
        $this->putJson($url, $data)->assertOk();
        $employee->forceFill(['is_archived' => true])->save();
        $data['position_id'] = $third->id;
        $data['date_hired'] = '2023-02-01';
        $data['assume_date'] = '2023-02-01';
        $data['termination_date'] = null;
        $this->putJson($url, $data)->assertOk();
        $this->putJson($url, $data)->assertOk();

        $this->assertSame(4, $history->query($employee)->count());
        $this->assertSame(3, PositionAssignment::where('user_id', $employee->id)->count());
        $this->assertTrue($employee->fresh()->isEligible());
        $this->assertDatabaseHas('work_schedules', ['user_id' => $employee->id, 'starts_on' => '2023-02-01']);
        $this->getJson($url.'/service-record')->assertOk()->assertJsonPath('total', 4)
            ->assertJsonPath('data.0.event', 'Rehired')->assertJsonPath('data.0.effective_date', '2023-02-01')
            ->assertJsonPath('data.0.position_title', 'Research Officer')
            ->assertJsonPath('data.1.event', 'Terminated')->assertJsonPath('data.1.effective_date', '2022-12-31')
            ->assertJsonPath('data.1.date_hired', '2020-01-01')
            ->assertJsonPath('data.2.event', 'Position change')->assertJsonPath('data.2.previous_position_title', 'Analyst')
            ->assertJsonPath('data.3.event', 'Hired')->assertJsonPath('data.3.effective_date', '2020-01-01')
            ->assertJsonPath('data.3.position_title', 'Analyst');
        $employee = $employee->fresh();
        $employee->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($employee)->getJson($url.'/service-record')->assertOk();
        $this->actingAs(User::factory()->create())->getJson($url.'/service-record')->assertForbidden();
    }

    public function test_a_new_employee_requires_a_position_and_hire_date_but_an_applicant_does_not(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $data = $this->data($this->position());
        $data['position_id'] = null;
        $data['date_hired'] = null;
        $this->actingAs($hr)->postJson('/hr/staff', $data)->assertUnprocessable()->assertJsonValidationErrors(['position_id', 'date_hired']);
        $this->assertDatabaseMissing('users', ['email' => $data['email']]);
        $data['employee_type'] = 'Applicant';
        $data['role'] = 'applicant';
        $admin = User::factory()->create(['role' => 'system_admin']);
        // HR is explicitly allowed to provision staff, but only account administrators change roles.
        $this->actingAs($admin);
        DB::table('ability_grants')->insert(['user_id' => $admin->id, 'ability' => 'staff.manage']);
        $this->postJson('/hr/staff', $data)->assertCreated();
    }

    public function test_non_plantilla_positions_are_exclusive_even_when_the_owner_is_archived_and_terminated(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $position = $this->position();
        User::factory()->create(['position_id' => $position->id, 'division_id' => $position->division_id,
            'is_archived' => true, 'date_hired' => '2019-01-01', 'termination_date' => '2020-01-01']);
        $this->actingAs($hr)->postJson('/hr/staff', $this->data($position))->assertUnprocessable()->assertJsonValidationErrors('position_id');
        $this->assertDatabaseMissing('users', ['email' => 'service-record@example.test']);
    }

    public function test_database_rejects_two_employees_assigned_to_the_same_position(): void
    {
        $position = $this->position();
        User::factory()->create(['position_id' => $position->id]);
        $this->expectException(QueryException::class);
        User::factory()->create(['position_id' => $position->id]);
    }

    public function test_clearing_a_past_termination_requires_a_new_hire_date(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $position = $this->position();
        $employee = User::factory()->create(['position_id' => $position->id, 'division_id' => $position->division_id,
            'date_hired' => '2020-01-01', 'termination_date' => '2021-01-01']);
        $data = $this->data($position);
        $data['email'] = $employee->email;
        $this->actingAs($hr)->putJson('/hr/staff/'.$employee->id, $data)->assertUnprocessable()->assertJsonValidationErrors('date_hired');
        $this->assertSame('2021-01-01', $employee->fresh()->termination_date->toDateString());
        $this->assertSame(0, app(EmploymentHistory::class)->query($employee)->count());
    }

    public function test_existing_service_records_are_backfilled_once_from_known_dates_and_position_history(): void
    {
        $first = $this->position();
        $second = $this->position('Senior Analyst');
        $actor = User::factory()->create(['role' => 'hr']);
        $employee = User::factory()->create(['position_id' => $second->id, 'division_id' => $second->division_id,
            'date_hired' => '2020-01-01', 'termination_date' => '2022-01-01']);
        PositionAssignment::create(['user_id' => $employee->id, 'position_id' => $first->id, 'actor_id' => $actor->id, 'assume_date' => '2020-01-01']);
        PositionAssignment::create(['user_id' => $employee->id, 'old_position_id' => $first->id, 'position_id' => $second->id,
            'actor_id' => $actor->id, 'assume_date' => '2021-01-01']);
        $history = app(EmploymentHistory::class);
        $history->backfill($employee, $actor->id);
        $history->backfill($employee, $actor->id);
        $this->assertSame(3, $history->query($employee)->count());
        $this->assertSame('Analyst', $history->query($employee)->where('action', 'employment.hired')->firstOrFail()->after['position_title']);
        $unknown = User::factory()->create(['date_hired' => null]);
        $history->backfill($unknown);
        $this->assertSame(0, $history->query($unknown)->count());
    }

    public function test_position_classification_accepts_only_staff_and_supervisory(): void
    {
        $admin = User::factory()->create(['role' => 'system_admin']);
        $division = Division::create(['name' => 'Research']);
        $data = ['title' => 'Analyst', 'division_id' => $division->id, 'plantilla' => false, 'status' => 'Open for Application'];
        $this->actingAs($admin)->postJson('/organization/positions', $data + ['classification' => 'Technical'])
            ->assertUnprocessable()->assertJsonValidationErrors('classification');
        foreach (['Staff', 'Supervisory'] as $classification) {
            $this->postJson('/organization/positions', $data + ['classification' => $classification])->assertOk();
        }
        $this->assertDatabaseCount('positions', 2);
    }

    public function test_service_record_print_includes_every_page_and_preserves_access_boundaries(): void
    {
        $employee = User::factory()->create();
        for ($i = 1; $i <= 12; $i++) {
            AuditEvent::create([
                'actor_id' => null, 'action' => 'employment.position_changed', 'subject_type' => User::class,
                'subject_id' => $employee->id, 'created_at' => now(),
                'after' => ['effective_date' => sprintf('2020-01-%02d', $i), 'position_title' => 'Print position '.sprintf('%02d', $i),
                    'event' => 'Hidden employment event', 'notes' => 'Hidden service record note',
                    'division' => 'Research', 'employee_type' => 'Permanent'],
            ]);
        }
        $url = '/hr/staff/'.$employee->id.'/service-record';
        $this->actingAs($employee)->getJson($url)->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('total', 12);
        $response = $this->get($url.'/print')->assertOk()->assertSee($employee->name)
            ->assertDontSee('Hidden employment event')->assertDontSee('Hidden service record note')
            ->assertDontSee('<th>Employment event</th>', false)->assertDontSee('<th>Notes</th>', false);
        for ($i = 1; $i <= 12; $i++) {
            $response->assertSee('Print position '.sprintf('%02d', $i));
        }
        $this->actingAs(User::factory()->create())->get($url.'/print')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'hr']))->get($url.'/print')->assertOk();
    }
}
