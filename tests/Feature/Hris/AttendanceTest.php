<?php

namespace Tests\Feature\Hris;

use App\Models\RawPunch;
use App\Models\ServiceClient;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Attendance\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $token = 'synthetic-collector-token'): ServiceClient
    {
        return ServiceClient::create(['name' => 'Example collector', 'token_hash' => hash('sha256', $token), 'scopes' => ['attendance.ingest'], 'devices' => ['READER-01']]);
    }

    /** @return array<string, mixed> */
    private function punch(int $id = 1): array
    {
        return ['anviz_log_id' => $id, 'user_atms_id' => '0009001', 'time_log' => '2026-10-20 08:00:00', 'device' => 'READER-01'];
    }

    public function test_machine_endpoints_fail_closed_and_human_sessions_are_not_device_credentials(): void
    {
        $this->getJson('/api/dtr-time-log/max-id')->assertUnauthorized();
        $user = User::factory()->create(['role' => 'system_admin']);
        $this->actingAs($user)->getJson('/api/dtr-time-log/max-id')->assertUnauthorized();
        $client = $this->client();
        $client->update(['scopes' => ['notifications.publish']]);
        $this->withToken('synthetic-collector-token')->getJson('/api/dtr-time-log/max-id')->assertForbidden();
        $client->update(['revoked_at' => now()]);
        $this->getJson('/api/dtr-time-log/max-id')->assertUnauthorized();
    }

    public function test_ingestion_counts_rows_and_replays_without_duplicates_with_normalized_identity(): void
    {
        $this->client();
        User::factory()->create(['atms_id' => '9001']);
        $records = array_map(fn ($id) => $this->punch($id), range(1, 501));
        $this->withToken('synthetic-collector-token')->postJson('/api/dtr-time-log/batch-insert', ['records' => $records])->assertCreated()
            ->assertJsonPath('inserted_count', 501)->assertJsonPath('unmatched_count', 0);
        $this->postJson('/api/dtr-time-log/batch-insert', ['records' => $records])->assertCreated()->assertJsonPath('inserted_count', 0)->assertJsonPath('duplicate_count', 501);
        $this->assertDatabaseCount('raw_punches', 501);
        $this->assertDatabaseHas('raw_punches', ['atms_id' => '9001']);
        $this->getJson('/api/dtr-time-log/max-id')->assertJsonPath('max_id', 501);
    }

    public function test_conflicting_evidence_rolls_back_the_entire_batch_and_source_epochs_isolate_resets(): void
    {
        $this->client();
        $this->withToken('synthetic-collector-token')->postJson('/api/dtr-time-log/batch-insert', ['records' => [$this->punch()]])->assertCreated();
        $changed = $this->punch();
        $changed['time_log'] = '2026-10-20 09:00:00';
        $this->postJson('/api/dtr-time-log/batch-insert', ['records' => [$this->punch(2), $changed]])->assertUnprocessable();
        $this->assertDatabaseCount('raw_punches', 1);
        $this->postJson('/api/dtr-time-log/batch-insert', ['source_epoch' => 'reader-reset', 'records' => [$changed]])->assertCreated();
        $this->getJson('/api/dtr-time-log/max-id?source_epoch=reader-reset')->assertJsonPath('max_id', 1);
    }

    public function test_unknown_id_evidence_is_retained_and_malicious_keys_unregistered_devices_and_zero_ids_fail(): void
    {
        $this->client();
        $this->withToken('synthetic-collector-token')->postJson('/api/dtr-time-log/batch-insert', ['records' => [$this->punch()]])->assertCreated()->assertJsonPath('unmatched_count', 1);
        $record = $this->punch(2);
        $record['user_id'] = 123;
        $this->postJson('/api/dtr-time-log/batch-insert', ['records' => [$record]])->assertUnprocessable();
        unset($record['user_id']);
        $record['device'] = 'UNKNOWN';
        $this->postJson('/api/dtr-time-log/batch-insert', ['records' => [$record]])->assertUnprocessable();
        $record['device'] = 'READER-01';
        $record['user_atms_id'] = '0000';
        $this->postJson('/api/dtr-time-log/batch-insert', ['records' => [$record]])->assertUnprocessable();
        $this->assertDatabaseCount('raw_punches', 1);
    }

    public function test_employee_clock_identity_comes_from_the_session_and_other_records_are_forbidden(): void
    {
        $owner = User::factory()->create(['atms_id' => '9001']);
        $other = User::factory()->create(['atms_id' => '9002']);
        $this->actingAs($owner)->postJson('/attendance/clock', ['wfh' => false, 'user_atms_id' => $other->atms_id])->assertUnprocessable();
        $this->postJson('/attendance/clock', ['wfh' => false])->assertCreated();
        $this->assertDatabaseHas('raw_punches', ['atms_id' => '9001', 'device' => 'WEB APP']);
        $this->getJson('/attendance/'.$other->id.'/raw')->assertForbidden();
        $this->postJson('/attendance/'.$other->id.'/override', [])->assertForbidden();
        $this->get('/attendance/'.$other->id.'/report')->assertForbidden();
        $this->postJson('/attendance/clock', ['wfh' => true])->assertUnprocessable();
    }

    public function test_late_arriving_punches_rebuild_existing_dates_and_identical_reruns_add_no_revision(): void
    {
        $user = User::factory()->create(['atms_id' => '9001']);
        WorkSchedule::create(['user_id' => $user->id, 'starts_on' => '2026-01-01', 'workweek' => 5, 'actor_id' => $user->id]);
        foreach (['08:00:59', '17:00:59'] as $time) {
            RawPunch::create(['atms_id' => '9001', 'punched_at' => '2026-10-20 '.$time, 'device' => 'READER-01']);
        }
        $service = app(AttendanceService::class);
        $record = $service->rebuild($user, '2026-10-20');
        $this->assertEquals(480, $record->worked_minutes);
        $service->rebuild($user, '2026-10-20');
        $this->assertDatabaseCount('daily_attendances', 1);
        $this->assertDatabaseCount('attendance_revisions', 1);
        RawPunch::create(['atms_id' => '9001', 'punched_at' => '2026-10-20 18:00:00', 'device' => 'READER-01']);
        $updated = $service->rebuild($user, '2026-10-20');
        $this->assertSame('18:00:00', $updated->time_out);
        $this->assertEquals(540, $updated->worked_minutes);
        $this->assertDatabaseCount('attendance_revisions', 2);
    }

    public function test_one_punch_is_reviewable_and_manual_overrides_survive_the_analyzer(): void
    {
        $user = User::factory()->create(['atms_id' => '9001']);
        RawPunch::create(['atms_id' => '9001', 'punched_at' => '2026-10-20 08:00:00', 'device' => 'WEB APP']);
        $service = app(AttendanceService::class);
        $record = $service->rebuild($user, '2026-10-20');
        $this->assertTrue($record->needs_review);
        $this->assertNull($record->workweek);
        $this->assertNull($record->worked_minutes);
        $service->override($user, '2026-10-20', ['time_in' => '08:00', 'time_out' => '17:00', 'workweek' => 5], $user->id, 'Approved missing punch correction');
        $service->rebuild($user, '2026-10-20');
        $this->assertTrue($record->fresh()->is_override);
        $this->assertEquals(480, $record->fresh()->worked_minutes);
        $service->rebuild($user, '2026-10-20', $user->id, 'Explicitly replace correction', true);
        $this->assertFalse($record->fresh()->is_override);
        $this->assertDatabaseCount('attendance_revisions', 3);
    }

    public function test_effective_schedule_and_report_share_the_same_stored_result_and_scope(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $user = User::factory()->create();
        $this->actingAs($hr)->postJson('/attendance/'.$user->id.'/override', ['attendance_date' => '2026-10-20', 'time_in' => '09:30', 'time_out' => '18:00', 'workweek' => 5, 'reason' => 'Verified device repair'])->assertOk();
        $this->getJson('/attendance/'.$user->id.'/data?from=2026-10-01&to=2026-10-31')->assertJsonPath('summary.late_minutes', 30);
        $this->get('/attendance/'.$user->id.'/report?from=2026-10-01&to=2026-10-31')->assertOk()->assertSee('office-v1');
        $csv = $this->get('/attendance/'.$user->id.'/report?from=2026-10-01&to=2026-10-31&format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('office-v1', $csv);
        $this->assertStringContainsString('2026-10-20', $csv);
        $this->postJson('/attendance/'.$user->id.'/remarks', ['coverage' => '2026-10-15', 'fc_absence' => 0, 'remarks' => 'Reviewed'])->assertOk();
        $this->assertDatabaseHas('attendance_remarks', ['user_id' => $user->id, 'coverage' => '2026-10-01', 'fc_absence' => 0]);
    }

    public function test_changing_an_attendance_id_does_not_reassign_historical_punches(): void
    {
        $original = User::factory()->create(['atms_id' => '9001']);
        RawPunch::create(['atms_id' => '9001', 'punched_at' => '2026-10-20 08:00:00', 'device' => 'READER-01']);
        $original->forceFill(['atms_id' => '9002'])->save();
        $newEmployee = User::factory()->create(['atms_id' => '9001']);
        $this->actingAs($original)->getJson('/attendance/'.$original->id.'/raw?from=2026-10-20&to=2026-10-20')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($newEmployee)->getJson('/attendance/'.$newEmployee->id.'/raw?from=2026-10-20&to=2026-10-20')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_unknown_evidence_requires_explicit_audited_identity_reconciliation(): void
    {
        $punch = RawPunch::create(['atms_id' => '9001', 'punched_at' => '2026-10-20 08:00:00', 'device' => 'READER-01']);
        $this->assertNull($punch->user_id);
        $employee = User::factory()->create(['atms_id' => '9001']);
        $this->actingAs($employee)->getJson('/attendance/'.$employee->id.'/raw?from=2026-10-20&to=2026-10-20')->assertJsonPath('total', 0);
        $this->artisan('attendance:match-identity', ['employee' => $employee->id, 'atms_id' => '0009001', '--from' => '2026-10-20', '--to' => '2026-10-20', '--reason' => 'Verified collector identity'])->assertSuccessful();
        $this->assertEquals($employee->id, $punch->fresh()->user_id);
        $this->assertSame('9001', $punch->fresh()->atms_id);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $punch->id, 'action' => 'attendance.identity-reconciled']);
    }

    public function test_schedules_are_effective_on_each_date_and_mixed_wfh_uses_office_start(): void
    {
        $user = User::factory()->create(['atms_id' => '9001']);
        WorkSchedule::create(['user_id' => $user->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'workweek' => 5, 'actor_id' => $user->id]);
        WorkSchedule::create(['user_id' => $user->id, 'starts_on' => '2026-07-01', 'workweek' => 4, 'actor_id' => $user->id]);
        foreach (['2026-06-30', '2026-07-01'] as $date) {
            foreach ([['07:00:00', 'WEB WFH'], ['08:00:00', 'READER-01'], ['12:00:00', 'READER-01'], ['13:00:00', 'READER-01'], ['17:00:00', 'WEB WFH']] as [$time, $device]) {
                RawPunch::create(['atms_id' => '9001', 'punched_at' => $date.' '.$time, 'device' => $device]);
            }
        }
        $service = app(AttendanceService::class);
        $june = $service->rebuild($user, '2026-06-30');
        $july = $service->rebuild($user, '2026-07-01');
        $this->assertSame('08:00:00', $june->time_in);
        $this->assertSame('12:00:00', $june->lunch_out);
        $this->assertSame('13:00:00', $june->lunch_in);
        $this->assertSame(5, $june->workweek);
        $this->assertSame(4, $july->workweek);
        $this->assertEquals(0, $june->undertime_minutes);
        $this->assertEquals(120, $july->undertime_minutes);
    }
}
