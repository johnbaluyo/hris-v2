<?php

namespace Tests\Feature\Hris;

use App\Models\RawPunch;
use App\Models\ServiceClient;
use App\Models\User;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RawAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-23 12:00:00', 'Asia/Manila'));
    }

    private function punch(): RawPunch
    {
        $employee = User::factory()->create(['atms_id' => '9001', 'name' => 'Example Employee']);

        return RawPunch::create(['user_id' => $employee->id, 'atms_id' => '9001', 'punched_at' => '2026-10-20 08:00:59', 'device' => 'READER-01']);
    }

    /** @return array<string, mixed> */
    private function correction(RawPunch $punch): array
    {
        return ['punched_at' => '2026-10-20T09:00:59', 'device' => 'READER-01', 'record_type' => 'Corrected',
            'reason' => 'Verified clock offset', 'version' => $punch->editVersion()];
    }

    public function test_only_admin_can_access_module_or_correct_punches_even_with_explicit_grants(): void
    {
        $punch = $this->punch();
        $this->getJson('/hr/raw-attendance/data')->assertUnauthorized();
        foreach (['hr', 'staff', 'division_chief', 'executive_director', 'chief_admin_officer', 'applicant'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            DB::table('ability_grants')->insert(['user_id' => $user->id, 'ability' => 'attendance.raw.manage']);
            $this->actingAs($user)->get('/hr/raw-attendance')->assertForbidden();
            $this->getJson('/hr/raw-attendance/data')->assertForbidden();
            $this->putJson('/hr/raw-attendance/'.$punch->id, $this->correction($punch))->assertForbidden();
            $this->getJson('/hr/raw-attendance/'.$punch->id.'/history')->assertForbidden();
        }
        $this->assertDatabaseCount('raw_punch_revisions', 0);
        $this->actingAs(User::factory()->create(['role' => 'system_admin']))->get('/hr/raw-attendance')->assertOk()->assertSee('Raw attendance punches');
    }

    public function test_admin_can_search_all_employees_including_former_and_unmatched_with_pagination(): void
    {
        $punch = $this->punch();
        $punch->employee->update(['is_archived' => true, 'termination_date' => '2026-10-01']);
        for ($i = 0; $i < 11; $i++) {
            RawPunch::create(['atms_id' => '9999', 'punched_at' => '2026-10-21 08:00:00', 'device' => 'UNMATCHED']);
        }
        $this->actingAs(User::factory()->create(['role' => 'system_admin']))->getJson('/hr/raw-attendance/data')->assertOk()
            ->assertJsonPath('total', 12)->assertJsonCount(10, 'data');
        $this->getJson('/hr/raw-attendance/data?search=Example%20Employee')->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $punch->id);
        $this->getJson('/hr/raw-attendance/data?matched=no')->assertJsonPath('total', 11)->assertJsonPath('data.0.employee_name', 'Unmatched employee');
        $this->getJson('/hr/raw-attendance/data?from=2026-10-20&to=2026-10-20')->assertJsonPath('total', 1);
        $this->getJson('/hr/raw-attendance/data?from=2026-10-22&to=2026-10-20')->assertUnprocessable();
    }

    public function test_correction_retains_original_evidence_records_actor_and_rejects_stale_versions(): void
    {
        $punch = $this->punch();
        $admin = User::factory()->create(['role' => 'system_admin']);
        $payload = $this->correction($punch);
        $this->actingAs($admin)->putJson('/hr/raw-attendance/'.$punch->id, $payload)->assertOk();
        $punch->refresh();
        $this->assertSame('08:00:59', CarbonImmutable::parse($punch->source_evidence['punched_at'])->format('H:i:s'));
        $this->assertSame('09:00:59', $punch->punched_at->format('H:i:s'));
        $this->assertDatabaseHas('raw_punch_revisions', ['raw_punch_id' => $punch->id, 'actor_id' => $admin->id, 'reason' => $payload['reason']]);
        $this->assertDatabaseHas('audit_events', ['action' => 'attendance.raw-punch.corrected', 'actor_id' => $admin->id]);
        $this->getJson('/hr/raw-attendance/'.$punch->id.'/history')->assertJsonPath('data.0.before.punched_at', '2026-10-20 08:00:59')
            ->assertJsonPath('data.0.after.punched_at', '2026-10-20 09:00:59')->assertJsonPath('data.0.actor.name', $admin->name);
        $this->putJson('/hr/raw-attendance/'.$punch->id, $payload)->assertConflict();
        $payload['version'] = $punch->editVersion();
        $this->putJson('/hr/raw-attendance/'.$punch->id, $payload)->assertOk();
        $this->assertDatabaseCount('raw_punch_revisions', 1);
        $payload['device'] = 'WEB APP';
        $payload['punched_at'] = '2026-10-20T09:00';
        $this->putJson('/hr/raw-attendance/'.$punch->id, $payload)->assertOk();
        $this->assertSame('READER-01', $punch->fresh()->source_evidence['device']);
        $this->assertDatabaseCount('raw_punch_revisions', 2);
    }

    public function test_moving_punch_rebuilds_both_dates_and_preserves_manual_overrides(): void
    {
        $punch = $this->punch();
        $employee = $punch->employee;
        $service = app(AttendanceService::class);
        $old = $service->rebuild($employee, '2026-10-20');
        $payload = $this->correction($punch);
        $payload['punched_at'] = '2026-10-21T08:15:59';
        $admin = User::factory()->create(['role' => 'system_admin']);
        $this->actingAs($admin)->putJson('/hr/raw-attendance/'.$punch->id, $payload)->assertOk();
        $this->assertNull($old->fresh()->time_in);
        $this->assertDatabaseHas('daily_attendances', ['user_id' => $employee->id, 'attendance_date' => '2026-10-21', 'time_in' => '08:15:59']);
        $override = $service->override($employee, '2026-10-21', ['time_in' => '08:00', 'time_out' => '17:00', 'workweek' => 5], $admin->id, 'Approved override');
        $before = $override->attributesToArray();
        $punch->refresh();
        $payload['version'] = $punch->editVersion();
        $payload['punched_at'] = '2026-10-21T09:00:59';
        $this->putJson('/hr/raw-attendance/'.$punch->id, $payload)->assertOk();
        $this->assertSame($before, $override->fresh()->attributesToArray());
    }

    public function test_unmatched_correction_does_not_create_a_daily_record_and_protected_fields_are_rejected(): void
    {
        $punch = RawPunch::create(['atms_id' => '9999', 'punched_at' => '2026-10-20 08:00:59', 'device' => 'READER-01']);
        $this->actingAs(User::factory()->create(['role' => 'system_admin']));
        $payload = $this->correction($punch);
        foreach (['user_id', 'atms_id', 'source_key', 'anviz_log_id', 'source_epoch', 'service_client_id', 'wfh_location', 'source_evidence', 'lock_version'] as $field) {
            $this->putJson('/hr/raw-attendance/'.$punch->id, $payload + [$field => '999'])->assertUnprocessable();
        }
        foreach (['reason' => '', 'punched_at' => '2027-01-01T08:00:00', 'version' => '', 'device' => ''] as $field => $value) {
            $this->putJson('/hr/raw-attendance/'.$punch->id, array_replace($payload, [$field => $value]))->assertUnprocessable();
        }
        $this->putJson('/hr/raw-attendance/'.$punch->id, $payload)->assertOk();
        $this->assertDatabaseCount('daily_attendances', 0);
        $this->assertDatabaseCount('raw_punch_revisions', 1);
        $this->assertNull($punch->fresh()->user_id);
    }

    public function test_collector_retries_compare_original_capture_and_never_overwrite_admin_corrections(): void
    {
        ServiceClient::create(['name' => 'Example collector', 'token_hash' => hash('sha256', 'example-token'), 'scopes' => ['attendance.ingest'], 'devices' => ['READER-01']]);
        $original = ['anviz_log_id' => 42, 'user_atms_id' => '9001', 'time_log' => '2026-10-20 08:00:59', 'device' => 'READER-01'];
        $this->withToken('example-token')->postJson('/api/dtr-time-log/batch-insert', ['records' => [$original]])->assertCreated();
        $punch = RawPunch::firstOrFail();
        $identity = $punch->only(['service_client_id', 'anviz_log_id', 'source_epoch', 'source_key', 'atms_id']);
        $this->actingAs(User::factory()->create(['role' => 'system_admin']))->putJson('/hr/raw-attendance/'.$punch->id, $this->correction($punch))->assertOk();
        $this->postJson('/api/dtr-time-log/batch-insert', ['records' => [$original]])->assertCreated()->assertJsonPath('duplicate_count', 1);
        $this->assertSame($identity, $punch->fresh()->only(array_keys($identity)));
        $this->assertSame('09:00:59', $punch->fresh()->punched_at->format('H:i:s'));
        $changed = array_replace($original, ['time_log' => '2026-10-20 09:00:59']);
        $this->postJson('/api/dtr-time-log/batch-insert', ['records' => [array_replace($original, ['anviz_log_id' => 43]), $changed]])->assertUnprocessable();
        $this->assertDatabaseCount('raw_punches', 1);
        $this->getJson('/api/dtr-time-log/max-id')->assertJsonPath('max_id', 42);
    }

    public function test_failed_recalculation_rolls_back_raw_correction_history_and_daily_records(): void
    {
        $punch = $this->punch();
        $before = $punch->refresh()->attributesToArray();
        $this->mock(AttendanceCalculator::class)->shouldReceive('calculate')->once()->andThrow(new \RuntimeException('Example calculation failure'));
        $this->actingAs(User::factory()->create(['role' => 'system_admin']))->putJson('/hr/raw-attendance/'.$punch->id, $this->correction($punch))->assertStatus(500);
        $this->assertSame($before, $punch->fresh()->attributesToArray());
        $this->assertDatabaseCount('raw_punch_revisions', 0);
        $this->assertDatabaseCount('daily_attendances', 0);
        $this->assertDatabaseMissing('audit_events', ['action' => 'attendance.raw-punch.corrected']);
    }
}
