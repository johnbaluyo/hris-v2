<?php

namespace Tests\Feature\Hris;

use App\Jobs\SendEventMail;
use App\Jobs\SyncPartnerUsers;
use App\Models\CalendarEvent;
use App\Models\IntegrationRun;
use App\Models\PortalItem;
use App\Models\RawPunch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_hides_inactive_apps_and_noncurrent_wellness_and_handles_leading_zero_identity(): void
    {
        $user = User::factory()->create(['atms_id' => '901']);
        foreach ([['application', 'Visible application', 'Active', null, null], ['application', 'Hidden application', 'Inactive', null, null],
            ['wellness', 'Current wellness', 'Active', today()->toDateString(), today()->toDateString()],
            ['wellness', 'Future wellness', 'Active', today()->addDay()->toDateString(), today()->addDays(2)->toDateString()]] as [$kind, $title, $status, $start, $end]) {
            PortalItem::create(['kind' => $kind, 'title' => $title, 'status' => $status, 'starts_on' => $start, 'ends_on' => $end, 'author_id' => $user->id, 'url' => 'https://example.test']);
        }
        RawPunch::create(['atms_id' => '901', 'punched_at' => today()->setTime(8, 1), 'device' => 'Reader']);
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Visible application')->assertDontSee('Hidden application')->assertSee('Current wellness')->assertDontSee('Future wellness')->assertSee('8:01 AM');
    }

    public function test_content_administration_is_not_available_to_staff_and_urls_are_validated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/portal/items', [])->assertForbidden();
        $admin = User::factory()->create(['role' => 'system_admin']);
        $this->actingAs($admin)->postJson('/portal/items', ['kind' => 'application', 'title' => 'Unsafe', 'status' => 'Active', 'url' => 'javascript:alert(1)'])->assertUnprocessable();
        $this->postJson('/portal/items', ['kind' => 'application', 'title' => 'Valid application', 'status' => 'Active', 'url' => 'https://example.test:8443/app/subpath?query=x#section'])->assertOk();
    }

    public function test_birthday_panel_uses_manila_date_and_excludes_archived_terminated_and_applicant_accounts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-21 00:30:00', 'Asia/Manila'));
        $user = User::factory()->create();
        User::factory()->create(['name' => 'Birthday Employee', 'birthday' => '1990-10-21', 'employee_type' => 'Permanent']);
        User::factory()->create(['name' => 'Archived Birthday', 'birthday' => '1990-10-21', 'employee_type' => 'Permanent', 'is_archived' => true]);
        User::factory()->create(['name' => 'Terminated Birthday', 'birthday' => '1990-10-21', 'employee_type' => 'Permanent', 'termination_date' => '2026-10-20']);
        User::factory()->create(['name' => 'Applicant Birthday', 'birthday' => '1990-10-21', 'employee_type' => 'Applicant', 'role' => 'applicant']);
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Birthday Employee')->assertDontSee('Archived Birthday')->assertDontSee('Terminated Birthday')->assertDontSee('Applicant Birthday');
    }

    public function test_private_event_details_mutations_and_list_membership_are_authorized(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = CalendarEvent::create(['user_id' => $owner->id, 'event_type' => 'My Calendar', 'title' => 'Private event', 'starts_at' => '2026-10-21 00:00:00', 'ends_at' => '2026-10-21 00:00:00']);
        $this->actingAs($other)->getJson('/calendar/events/'.$event->id)->assertForbidden();
        $this->deleteJson('/calendar/events/'.$event->id)->assertForbidden();
        $this->getJson('/calendar/data?calendar=personal')->assertJsonCount(0, 'data');
        $event->participants()->attach($other->id);
        $this->getJson('/calendar/data?calendar=personal')->assertJsonPath('data.0.title', 'Private event');
        $this->getJson('/calendar/events/'.$event->id)->assertOk();
        $this->deleteJson('/calendar/events/'.$event->id)->assertForbidden();
    }

    public function test_all_day_feed_end_is_exclusive_and_timed_events_keep_manila_time(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/calendar/events', ['event_type' => 'My Calendar', 'title' => 'Three days', 'all_day' => true,
            'starts_at' => '2026-10-20T00:00', 'ends_at' => '2026-10-22T00:00', 'participants' => []])->assertOk();
        $this->getJson('/calendar/data')->assertJsonPath('data.0.feed_start', '2026-10-20')->assertJsonPath('data.0.feed_end', '2026-10-23');
        $this->postJson('/calendar/events', ['event_type' => 'My Calendar', 'title' => 'Timed', 'all_day' => false,
            'starts_at' => '2026-10-25T08:30', 'ends_at' => '2026-10-25T09:30', 'participants' => []])->assertOk();
        $this->getJson('/calendar/data?search=Timed')->assertJsonPath('data.0.feed_start', '2026-10-25T08:30:00+08:00');
    }

    public function test_personal_events_cannot_be_promoted_to_office_events_by_staff(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/calendar/events', ['event_type' => 'Office', 'title' => 'Unauthorized', 'all_day' => true,
            'starts_at' => '2026-10-21', 'ends_at' => '2026-10-21'])->assertForbidden();
    }

    public function test_executive_events_are_private_and_office_feed_excludes_wfh(): void
    {
        $admin = User::factory()->create(['role' => 'system_admin']);
        $staff = User::factory()->create();
        foreach (['Office', 'Executive Director Activities', 'Work From Home'] as $type) {
            CalendarEvent::create(['user_id' => $admin->id, 'event_type' => $type, 'title' => $type, 'starts_at' => '2026-10-21', 'ends_at' => '2026-10-21']);
        }
        $this->actingAs($staff)->getJson('/calendar/data?calendar=office')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Office');
        $this->getJson('/calendar/data?calendar=ed')->assertJsonCount(0, 'data');
        $this->actingAs($admin)->getJson('/calendar/data?calendar=ed')->assertJsonCount(1, 'data');
    }

    public function test_participant_mail_is_not_sent_twice_on_job_retries(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['role' => 'system_admin']);
        $participant = User::factory()->create();
        $event = CalendarEvent::create(['user_id' => $owner->id, 'event_type' => 'Office', 'title' => 'Office meeting', 'starts_at' => '2026-10-21 08:00:00', 'ends_at' => '2026-10-21 09:00:00']);
        $event->participants()->attach($participant->id);
        DB::table('event_mail_deliveries')->insert(['calendar_event_id' => $event->id, 'user_id' => $participant->id, 'revision' => 'revision-one']);
        $job = new SendEventMail($event->id, $participant->id, 'revision-one');
        $job->handle();
        $job->handle();
        $this->assertNotNull(DB::table('event_mail_deliveries')->first()->sent_at);
        // Raw mail uses the mailer facade rather than a Mailable; verify the persisted deduplication marker.
        $this->assertDatabaseCount('event_mail_deliveries', 1);
    }

    public function test_sync_requires_permission_and_records_success_with_opt_in_405_compatibility(): void
    {
        Queue::fake();
        $staff = User::factory()->create();
        $this->actingAs($staff)->postJson('/integration/sync', ['partner' => 'ssi'])->assertForbidden();
        config(['hris.partners.ssi' => ['url' => 'https://ssi.example.test/subapp', 'token' => 'synthetic-service-token', 'allow_get_fallback' => true]]);
        Http::preventStrayRequests();
        Http::fake(['ssi.example.test/*' => Http::sequence()->push([], 405)->push(['message' => 'ok'], 200)]);
        $run = IntegrationRun::create(['partner' => 'ssi', 'actor_id' => $staff->id]);
        (new SyncPartnerUsers($run->id))->handle();
        $this->assertSame('succeeded', $run->fresh()->status);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->url() === 'https://ssi.example.test/subapp/api/sync-users');
    }

    public function test_sync_handles_missing_configuration_rejection_and_timeout_without_secret_leaks(): void
    {
        $user = User::factory()->create();
        config(['hris.partners.ssi' => ['url' => null, 'token' => null]]);
        $run = IntegrationRun::create(['partner' => 'ssi', 'actor_id' => $user->id]);
        (new SyncPartnerUsers($run->id))->handle();
        $this->assertSame('failed', $run->fresh()->status);
        config(['hris.partners.ssi' => ['url' => 'https://ssi.example.test', 'token' => 'synthetic-token']]);
        Http::fake(['ssi.example.test/*' => Http::response(['message' => 'private upstream details'], 500)]);
        $rejected = IntegrationRun::create(['partner' => 'ssi', 'actor_id' => $user->id]);
        (new SyncPartnerUsers($rejected->id))->handle();
        $this->assertSame('failed', $rejected->fresh()->status);
        $this->assertStringNotContainsString('private upstream', $rejected->fresh()->message);
        Http::fake(['ssi.example.test/*' => Http::failedConnection()]);
        $timeout = IntegrationRun::create(['partner' => 'ssi', 'actor_id' => $user->id]);
        (new SyncPartnerUsers($timeout->id))->handle();
        $this->assertSame('failed', $timeout->fresh()->status);
    }
}
