<?php

namespace Tests\Feature\Hris;

use App\Models\DailyAttendance;
use App\Models\PdsEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\HrisDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Tests\TestCase;

class HrisDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fictional_dataset_is_complete_valid_and_repeatable_without_external_side_effects(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 10:00:00 Asia/Manila');
        $this->travelTo(CarbonImmutable::now('Asia/Manila'));
        Mail::fake();
        Notification::fake();
        Queue::fake();
        Http::preventStrayRequests();
        $seeder = new HrisDemoSeeder;
        $seeder->seed('Synthetic-test-password-123!');

        $this->assertDatabaseCount('users', 50);
        $this->assertDatabaseCount('divisions', 5);
        $this->assertDatabaseCount('positions', 53);
        $this->assertDatabaseCount('pds_entries', 650);
        $this->assertDatabaseCount('work_schedules', 48);
        $this->assertDatabaseCount('position_assignments', 48);
        $this->assertDatabaseCount('portal_items', 9);
        $this->assertDatabaseCount('calendar_events', 5);
        $this->assertDatabaseCount('calendar_event_user', 6);
        $this->assertDatabaseCount('delegations', 1);
        $this->assertDatabaseCount('audit_events', 99);
        $this->assertDatabaseCount('integration_runs', 2);
        $this->assertSame(6, User::distinct()->count('role'));
        $this->assertGreaterThan(2000, DB::table('raw_punches')->count());
        $this->assertGreaterThan(700, DailyAttendance::count());
        $this->assertTrue(DailyAttendance::where('needs_review', true)->exists());
        $this->assertTrue(DailyAttendance::where('is_override', true)->exists());
        $this->assertTrue(DB::table('raw_punches')->where('device', 'WEB WFH')->exists());
        $this->assertSame(0, DB::table('raw_punches')->whereNull('user_id')->count());
        $this->assertSame(0, DB::table('service_clients')->whereNull('revoked_at')->count());
        $this->assertSame(0, DB::table('event_mail_deliveries')->count());
        foreach (PdsEntry::all() as $entry) {
            $rules = [];
            foreach (config('pds.'.$entry->section.'.fields') as $key => $rule) {
                $rules['data.'.$key] = $rule;
            }
            $validator = Validator::make(['data' => $entry->data], $rules);
            $this->assertFalse($validator->fails(), $entry->section.': '.$validator->errors()->toJson());
        }

        $admin = User::where('email', 'admin@demo.hris.test')->firstOrFail();
        $this->assertTrue(Hash::check('Synthetic-test-password-123!', $admin->password));
        $this->assertTrue($admin->hasAbility('accounts.manage'));
        $this->assertTrue(User::where('email', 'hr@demo.hris.test')->firstOrFail()->hasAbility('attendance.read'));
        $originalPassword = $admin->password;
        $admin->forceFill(['name' => 'Edited sample administrator'])->save();
        $entry = PdsEntry::where('user_id', $admin->id)->where('section', 'other')->firstOrFail();
        $entry->update(['data' => ['special_skills_hobbies' => 'Edited sample skill']]);
        $deleted = PdsEntry::where('user_id', $admin->id)->where('section', 'education')->firstOrFail();
        $deleted->delete();
        $counts = $this->counts();
        $this->travel(3)->days();
        $seeder->seed('Another-test-password-456!');
        $this->assertSame($counts, $this->counts());
        $this->assertSame('Edited sample administrator', $admin->fresh()->name);
        $this->assertSame($originalPassword, $admin->fresh()->password);
        $this->assertSame('Edited sample skill', $entry->fresh()->data['special_skills_hobbies']);
        $this->assertSoftDeleted($deleted);
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
        CarbonImmutable::setTestNow();
        $this->travelBack();
    }

    public function test_production_environment_is_rejected_before_any_inserts(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        try {
            (new HrisDemoSeeder)->seed('Synthetic-test-password-123!');
            $this->fail('Production seeding must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('restricted', $exception->getMessage());
            $this->assertDatabaseCount('users', 0);
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_legacy_database_target_is_rejected_without_connecting_or_writing(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.demo_guard' => ['driver' => 'mysql', 'database' => 'db_hris']]);
        $this->app->detectEnvironment(fn () => 'local');
        DB::setDefaultConnection('demo_guard');
        try {
            (new HrisDemoSeeder)->seed('Synthetic-test-password-123!');
            $this->fail('Legacy database seeding must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('db_hris must remain read-only', $exception->getMessage());
        } finally {
            DB::setDefaultConnection($original);
            $this->app->detectEnvironment(fn () => 'testing');
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_identity_collision_rolls_back_all_seeded_records(): void
    {
        User::factory()->create(['email' => 'hr@demo.hris.test', 'name' => 'Existing unrelated account']);
        try {
            (new HrisDemoSeeder)->seed('Synthetic-test-password-123!');
            $this->fail('Identity collision must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unrelated account', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('divisions', 0);
        $this->assertDatabaseCount('positions', 0);
        $this->assertDatabaseCount('pds_entries', 0);
    }

    public function test_cloned_connection_with_stale_name_is_rejected_before_connecting(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.demo_alias' => ['driver' => 'mysql', 'database' => 'db_hris_v2', 'name' => 'mysql']]);
        $this->app->detectEnvironment(fn () => 'local');
        DB::setDefaultConnection('demo_alias');
        try {
            (new HrisDemoSeeder)->seed('Synthetic-test-password-123!');
            $this->fail('A stale connection name must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('configured alias', $exception->getMessage());
        } finally {
            DB::setDefaultConnection($original);
            $this->app->detectEnvironment(fn () => 'testing');
        }
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $counts = [];
        foreach (['users', 'divisions', 'positions', 'pds_entries', 'work_schedules', 'position_assignments',
            'delegations', 'portal_items', 'calendar_events', 'calendar_event_user', 'raw_punches', 'daily_attendances',
            'attendance_revisions', 'attendance_remarks', 'service_clients', 'integration_runs', 'audit_events'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}
