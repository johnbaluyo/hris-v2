<?php

namespace Tests\Feature\Hris;

use App\Jobs\SendAccountInvitation;
use App\Models\Division;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class IntegrationReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_recovery_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $known = $this->postJson('/forgot-password', ['email' => $user->email])->assertOk()->json();
        $unknown = $this->postJson('/forgot-password', ['email' => 'unknown@example.test'])->assertOk()->json();
        $this->assertSame($known, $unknown);
    }

    public function test_invitation_sends_a_password_setup_link_without_a_fixed_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        (new SendAccountInvitation($user->id))->handle();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_session_id_rotates_on_login_and_logout_removes_old_session_data(): void
    {
        $user = User::factory()->create();
        $this->withSession(['session_marker' => 'keep-until-logout']);
        $oldId = session()->getId();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertNotSame($oldId, session()->getId());
        $this->assertSame('keep-until-logout', session('session_marker'));
        $oldToken = session()->token();
        $this->post('/logout')->assertRedirect('/');
        $this->assertNull(session('session_marker'));
        $this->assertNotSame($oldToken, session()->token());
        $this->assertGuest();
    }

    public function test_monthly_reports_scope_division_chiefs_and_do_not_serialize_private_account_fields(): void
    {
        $division = Division::create(['name' => 'Research']);
        $chief = User::factory()->create(['role' => 'division_chief', 'division_id' => $division->id]);
        $employee = User::factory()->create(['division_id' => $division->id, 'name' => 'Visible Example']);
        $other = User::factory()->create(['name' => 'Private Other Division']);
        app(AttendanceService::class)->override($employee, '2026-10-20', ['time_in' => '08:00', 'time_out' => '17:00', 'workweek' => 5], $chief->id, 'Synthetic fixture');
        $data = $this->actingAs($chief)->getJson('/attendance/review/data?month=2026-10')->assertOk()->assertJsonPath('total', 2)->json('data');
        $this->assertContains($employee->id, array_column($data, 'id'));
        $this->assertNotContains($other->id, array_column($data, 'id'));
        $this->assertArrayNotHasKey('birthday', $data[0]);
        $this->assertArrayNotHasKey('email', $data[0]);
        $this->get('/attendance/review/export?month=2026-10')->assertForbidden();
    }

    public function test_system_administration_does_not_implicitly_grant_confidential_hr_access(): void
    {
        $admin = User::factory()->create(['role' => 'system_admin']);
        $employee = User::factory()->create();
        $this->actingAs($admin)->getJson('/profile/'.$employee->id.'/personal/data')->assertForbidden();
        $this->getJson('/attendance/'.$employee->id.'/data')->assertForbidden();
        $this->getJson('/attendance/review/data?month=2026-10')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_applicants_cannot_access_employee_records_or_clock(): void
    {
        $applicant = User::factory()->create(['role' => 'applicant']);
        $this->actingAs($applicant)->get('/dashboard')->assertOk()->assertSee('Your account');
        foreach (['/calendar', '/profile', '/attendance', '/attendance/review'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->postJson('/attendance/clock', ['wfh' => false])->assertUnprocessable();
    }

    public function test_employee_pickers_are_paginated_and_do_not_return_account_or_personal_data(): void
    {
        $user = User::factory()->create();
        User::factory()->count(12)->create(['name' => 'Lookup Example']);
        $data = $this->actingAs($user)->getJson('/employees/lookup?search=Lookup')->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('total', 12)->json('data');
        $this->assertSame(['id', 'name'], array_keys($data[0]));
        $this->getJson('/employees/lookup?search=Lookup&page=2')->assertJsonCount(2, 'data');
    }

    public function test_analyzer_runs_twice_without_duplicates_or_financial_effects(): void
    {
        $user = User::factory()->create(['atms_id' => '9001']);
        $args = ['--from' => '2026-10-20', '--to' => '2026-10-20', '--employee' => $user->id];
        $this->artisan('attendance:rebuild', $args)->assertSuccessful();
        $this->artisan('attendance:rebuild', $args)->assertSuccessful();
        $this->assertDatabaseCount('daily_attendances', 1);
        $this->assertDatabaseCount('attendance_revisions', 1);
        $this->assertDatabaseCount('raw_punches', 0);
    }
}
