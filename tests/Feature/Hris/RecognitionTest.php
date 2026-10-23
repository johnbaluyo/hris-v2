<?php

namespace Tests\Feature\Hris;

use App\Models\DailyAttendance;
use App\Models\RecognitionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecognitionTest extends TestCase
{
    use RefreshDatabase;

    private function event(User $hr, array $nominees): RecognitionEvent
    {
        $this->actingAs($hr)->postJson('/recognition', ['name' => 'Example recognition', 'type' => 'voting',
            'coverage_start' => '2026-01-01', 'coverage_end' => '2026-09-30', 'recognition_date' => '2026-12-01',
            'opens_at' => now()->subHour()->format('Y-m-d H:i:s'), 'closes_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'allow_self_vote' => false, 'voter_types' => ['Permanent'], 'nominee_ids' => array_map(fn ($user) => $user->id, $nominees)])->assertOk();

        return RecognitionEvent::latest('id')->firstOrFail();
    }

    public function test_ballot_membership_window_uniqueness_and_private_counts(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $voter = User::factory()->create(['employee_type' => 'Permanent']);
        $nominee = User::factory()->create(['employee_type' => 'Permanent']);
        $event = $this->event($hr, [$voter, $nominee]);
        $foreign = $this->event($hr, [$nominee]);
        $foreignNominee = $foreign->nominees()->firstOrFail();
        $ownNominee = $event->nominees()->where('user_id', $voter->id)->first();
        $otherNominee = $event->nominees()->where('user_id', $nominee->id)->first();
        $url = '/recognition/'.$event->id.'/vote';
        $this->actingAs($voter)->get('/recognition')->assertOk();
        $this->postJson($url, ['nominee_id' => $foreignNominee->id])->assertNotFound();
        $this->postJson($url, ['nominee_id' => $ownNominee->id])->assertUnprocessable();
        $this->postJson($url, ['nominee_id' => $otherNominee->id])->assertOk();
        $this->postJson($url, ['nominee_id' => $otherNominee->id])->assertOk();
        $this->assertDatabaseCount('recognition_votes', 1);
        $this->getJson('/recognition/data')->assertDontSee('votes_count');
        $this->actingAs($hr)->getJson('/recognition/data')->assertSee('votes_count');
        $this->postJson('/recognition/'.$event->id.'/publish', ['winner_ids' => [$otherNominee->id]])->assertUnprocessable();
        $event->update(['closes_at' => now()->subMinute()]);
        $this->postJson('/recognition/'.$event->id.'/publish', ['winner_ids' => [$foreignNominee->id]])->assertUnprocessable();
        $this->postJson('/recognition/'.$event->id.'/publish', ['winner_ids' => [$otherNominee->id]])->assertOk();
        $this->assertTrue($otherNominee->fresh()->winner);
        $this->actingAs($nominee)->postJson($url, ['nominee_id' => $ownNominee->id])->assertForbidden();
        $this->postJson('/recognition/'.$event->id.'/archive', [])->assertForbidden();
        $this->actingAs($hr)->postJson('/recognition/'.$event->id.'/archive', [])->assertOk();
        $this->assertDatabaseCount('recognition_votes', 1);
    }

    public function test_attendance_statistics_sort_large_durations_numerically_and_accept_leap_day(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $long = User::factory()->create(['name' => 'Long duration', 'employee_type' => 'Permanent', 'date_hired' => '2020-01-01']);
        $short = User::factory()->create(['name' => 'Short duration', 'employee_type' => 'Permanent', 'date_hired' => '2020-01-01']);
        foreach ([[$long, 6001], [$short, 5999]] as [$user, $minutes]) {
            DailyAttendance::create(['user_id' => $user->id, 'attendance_date' => '2024-02-29', 'late_minutes' => $minutes,
                'undertime_minutes' => 0, 'policy_version' => 'example', 'needs_review' => false, 'provenance' => []]);
        }
        $url = '/recognition/attendance?from=2024-02-01&to=2024-02-29&include_recent_hires=0';
        $this->actingAs($long)->get($url)->assertForbidden();
        $this->actingAs($hr)->get($url)->assertOk()->assertSeeInOrder(['Short duration', 'Long duration']);
    }
}
