<?php

namespace Tests\Feature\Hris;

use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WfhScheduleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, bool, ?array{string, string, string}}> */
    public static function availabilityDates(): array
    {
        return [
            'Wednesday without an event' => ['2026-10-21 08:00:00+08:00', true, null],
            'Tuesday without an event' => ['2026-10-20 08:00:00+08:00', false, null],
            'Wednesday begins in Manila while UTC is Tuesday' => ['2026-10-20 16:00:00+00:00', true, null],
            'Wednesday ends in Manila while UTC is Wednesday' => ['2026-10-21 16:00:00+00:00', false, null],
            'single day WFH event' => ['2026-10-22 08:00:00+08:00', true, ['Work From Home', '2026-10-22', '2026-10-22']],
            'first day of WFH event' => ['2026-10-19 08:00:00+08:00', true, ['Work From Home', '2026-10-19', '2026-10-23']],
            'middle of WFH event' => ['2026-10-20 08:00:00+08:00', true, ['Work From Home', '2026-10-19', '2026-10-23']],
            'last day of WFH event' => ['2026-10-23 23:59:59+08:00', true, ['Work From Home', '2026-10-19', '2026-10-23']],
            'expired WFH event' => ['2026-10-24 08:00:00+08:00', false, ['Work From Home', '2026-10-19', '2026-10-23']],
            'future WFH event' => ['2026-10-20 08:00:00+08:00', false, ['Work From Home', '2026-10-22', '2026-10-23']],
            'other calendar type' => ['2026-10-22 08:00:00+08:00', false, ['Office', '2026-10-22', '2026-10-22']],
        ];
    }

    /** @param array{string, string, string}|null $event */
    #[DataProvider('availabilityDates')]
    public function test_pages_and_clock_share_wfh_availability(string $now, bool $available, ?array $event): void
    {
        $this->travelTo(CarbonImmutable::parse($now));
        $employee = User::factory()->create(['atms_id' => '9001']);
        if ($event !== null) {
            $admin = User::factory()->create(['role' => 'system_admin']);
            CalendarEvent::create(['user_id' => $admin->id, 'event_type' => $event[0], 'title' => 'Scheduled event',
                'all_day' => true, 'starts_at' => $event[1], 'ends_at' => $event[2]]);
        }

        $this->actingAs($employee);
        foreach (['/dashboard', '/ssi', '/attendance', '/attendance/'.$employee->id] as $url) {
            $page = $this->get($url)->assertOk()->assertViewHas('wfh', $available);
            $this->assertMatchesRegularExpression('/<button\b[^>]*id="clock-wfh"[^>]*>Record WFH punch<\/button>/', $page->getContent());
            $disabledButton = '/<button\b[^>]*id="clock-wfh"[^>]*\bdisabled\b[^>]*>/';
            if ($available) {
                $this->assertDoesNotMatchRegularExpression($disabledButton, $page->getContent());
            } else {
                $this->assertMatchesRegularExpression($disabledButton, $page->getContent());
            }
        }

        $response = $this->postJson('/attendance/clock', ['wfh' => true]);
        if ($available) {
            $response->assertCreated();
            $this->assertDatabaseHas('raw_punches', ['user_id' => $employee->id, 'device' => 'WEB WFH',
                'punched_at' => now('Asia/Manila')->format('Y-m-d H:i:s')]);
        } else {
            $response->assertUnprocessable();
            $this->assertDatabaseCount('raw_punches', 0);
            $this->postJson('/attendance/clock', ['wfh' => false])->assertUnprocessable();
        }
    }

    public function test_wfh_calendar_option_and_creation_require_calendar_management_permission(): void
    {
        $employee = User::factory()->create();
        $event = ['event_type' => 'Work From Home', 'title' => 'Additional WFH date', 'all_day' => false,
            'starts_at' => '2026-10-22T08:00', 'ends_at' => '2026-10-22T17:00', 'participants' => []];
        $this->actingAs($employee)->get('/calendar')->assertOk()->assertDontSee('value="Work From Home"', false);
        $this->postJson('/calendar/events', $event)->assertForbidden();

        $admin = User::factory()->create(['role' => 'system_admin']);
        $this->actingAs($admin)->get('/calendar')->assertOk()->assertSee('value="Work From Home"', false);
        $this->postJson('/calendar/events', $event)->assertOk();
        $this->assertDatabaseHas('calendar_events', ['event_type' => 'Work From Home', 'all_day' => true,
            'starts_at' => '2026-10-22 00:00:00', 'ends_at' => '2026-10-22 00:00:00']);
    }
}
