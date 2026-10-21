<?php

namespace Tests\Feature\Hris;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarFeedTest extends TestCase
{
    use RefreshDatabase;

    private function event(User $owner, string $title, string $type = 'My Calendar'): CalendarEvent
    {
        return CalendarEvent::create(['user_id' => $owner->id, 'title' => $title, 'event_type' => $type,
            'all_day' => true, 'starts_at' => '2026-10-21 00:00:00', 'ends_at' => '2026-10-22 00:00:00']);
    }

    public function test_month_feed_includes_all_events_beyond_ten_rows_with_exclusive_all_day_ends_and_no_private_leakage(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        for ($i = 1; $i <= 12; $i++) {
            $this->event($user, 'Visible '.$i);
        }
        $this->event($other, 'Private other event');
        $shared = $this->event($other, 'Invited event');
        $shared->participants()->attach($user->id);
        $this->actingAs($user)->getJson('/calendar/feed?from=2026-10-01&to=2026-10-31&calendar=personal')->assertOk()
            ->assertJsonCount(13)->assertJsonPath('0.start', '2026-10-21')->assertJsonPath('0.end', '2026-10-23')
            ->assertJsonPath('0.extendedProps.can_edit', true)->assertDontSee('Private other event')->assertSee('Invited event');
    }

    public function test_office_and_executive_scopes_keep_the_same_permissions_as_the_event_list(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'system_admin']);
        $this->event($admin, 'Office workshop', 'Office');
        $this->event($admin, 'Executive meeting', 'Executive Director Activities');
        $base = '/calendar/feed?from=2026-10-01&to=2026-10-31&calendar=';
        $this->actingAs($user)->getJson($base.'office')->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'Office workshop');
        $this->getJson($base.'ed')->assertOk()->assertJsonCount(0);
        $this->actingAs($admin)->getJson($base.'ed')->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'Executive meeting');
        $this->getJson('/calendar/feed?from=2026-01-01&to=2026-12-31')->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'applicant']))->getJson($base.'office')->assertForbidden();
    }
}
