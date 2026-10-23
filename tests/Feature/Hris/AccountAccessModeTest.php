<?php

namespace Tests\Feature\Hris;

use App\Models\AuditEvent;
use App\Models\Division;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\WfhAccomplishment;
use App\Models\WfhReport;
use App\Services\Requests\RequestWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountAccessModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_defaults_to_admin_access_with_switch_next_to_theme_toggle(): void
    {
        $admin = User::factory()->create(['role' => 'system_admin']);
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSeeInOrder(['data-theme-toggle', 'aria-label="Account access"'], false);
        foreach (['accounts.manage', 'portal.manage', 'attendance.raw.manage', 'attendance.office-punch.manage'] as $ability) {
            $this->assertTrue($admin->hasAbility($ability), $ability);
        }
        $this->get('/hr/raw-attendance')->assertOk();
        $this->get('/hr/staff')->assertForbidden();
        $this->assertFalse($admin->hasAbility('attendance.correct'));
        $this->get('/settings/profile')->assertOk()->assertSee('aria-label="Account access"', false);
    }

    public function test_staff_mode_is_enforced_on_reads_writes_and_existing_grants_and_can_be_switched_back(): void
    {
        $admin = User::factory()->create(['role' => 'system_admin']);
        $employee = User::factory()->create();
        foreach (['staff.manage', 'attendance.correct', 'accounts.manage', 'attendance.raw.manage', 'requests.certify'] as $ability) {
            DB::table('ability_grants')->insert(['user_id' => $admin->id, 'ability' => $ability]);
        }
        $this->actingAs($admin)->post('/account/access-mode', ['mode' => 'staff'])->assertRedirect('/dashboard')
            ->assertSessionHas('account_access', ['user_id' => $admin->id, 'mode' => 'staff']);
        $this->get('/dashboard')->assertOk()->assertDontSee('HR ADMINISTRATION')->assertSee('aria-label="Account access"', false);
        $this->getJson('/hr/staff/data')->assertForbidden();
        $this->getJson('/portal/data')->assertForbidden();
        $this->getJson('/hr/raw-attendance/data')->assertForbidden();
        $this->getJson('/attendance/office-punch-windows')->assertForbidden();
        $this->getJson('/attendance/'.$employee->id.'/data')->assertForbidden();
        $this->postJson('/attendance/'.$employee->id.'/override', [])->assertForbidden();
        $this->postJson('/hr/recruitment/vacancies', [])->assertForbidden();
        $this->getJson('/attendance/'.$admin->id.'/data')->assertOk();
        $this->get('/profile')->assertOk();
        $this->get('/careers')->assertOk()->assertDontSee('id="new-vacancy"', false);
        $this->assertSame('system_admin', $admin->fresh()->role);
        $this->assertFalse($admin->isDirty('role'));
        $this->post('/account/access-mode', ['mode' => 'admin'])->assertRedirect('/dashboard');
        $this->get('/hr/raw-attendance')->assertOk();
        $this->get('/hr/staff')->assertOk();
        $this->assertDatabaseHas('audit_events', ['action' => 'account.access-mode.changed', 'actor_id' => $admin->id]);
        $this->assertSame(['access_mode' => 'staff'], AuditEvent::where('action', 'account.access-mode.changed')->firstOrFail()->after);
    }

    public function test_hr_mode_has_hr_tools_without_admin_privileges_and_ignores_extra_grants(): void
    {
        $admin = User::factory()->create(['role' => 'system_admin']);
        foreach (['attendance.raw.manage', 'attendance.office-punch.manage', 'accounts.manage', 'portal.manage'] as $ability) {
            DB::table('ability_grants')->insert(['user_id' => $admin->id, 'ability' => $ability]);
        }
        $this->actingAs($admin)->post('/account/access-mode', ['mode' => 'hr'])->assertRedirect('/dashboard');
        $this->get('/hr/staff')->assertOk();
        $this->get('/hr/recruitment')->assertOk();
        $this->get('/hr/payroll')->assertOk();
        $this->get('/hr/raw-attendance')->assertForbidden();
        $this->get('/portal/manage')->assertForbidden();
        $this->postJson('/attendance/office-punch-windows', [])->assertForbidden();
        $this->assertTrue($admin->hasAbility('attendance.correct'));
        $this->assertFalse($admin->hasAbility('accounts.manage'));
        $admin->name = 'Example administrator';
        $admin->save();
        $this->assertSame('system_admin', $admin->fresh()->role);
        $this->post('/account/access-mode', ['mode' => 'admin'])->assertRedirect('/dashboard');
        $this->get('/portal/manage')->assertOk();
    }

    public function test_non_admins_cannot_switch_even_with_forged_session_modes_or_management_grants(): void
    {
        $this->postJson('/account/access-mode', ['mode' => 'admin'])->assertUnauthorized();
        foreach (['staff', 'hr', 'division_chief', 'executive_director', 'chief_admin_officer', 'applicant'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            DB::table('ability_grants')->insert(['user_id' => $user->id, 'ability' => 'accounts.manage']);
            $this->actingAs($user)->withSession(['account_access' => ['user_id' => $user->id, 'mode' => 'admin']])
                ->post('/account/access-mode', ['mode' => 'admin'])->assertForbidden()->assertSessionMissing('account_access');
            $this->get('/dashboard')->assertOk()->assertDontSee('aria-label="Account access"', false);
            $this->assertSame($role, $user->fresh()->role);
        }
    }

    public function test_mode_validation_ownership_and_session_isolation(): void
    {
        $admin = User::factory()->create(['role' => 'system_admin']);
        $other = User::factory()->create(['role' => 'system_admin']);
        $this->actingAs($admin)->postJson('/account/access-mode', ['mode' => 'executive_director'])->assertUnprocessable();
        $this->postJson('/account/access-mode', ['mode' => 'admin', 'user_id' => $other->id])->assertUnprocessable();
        $this->post('/account/access-mode', ['mode' => 'staff'])->assertRedirect('/dashboard');
        $this->actingAs($other)->get('/hr/raw-attendance')->assertOk();
        $this->actingAs($admin)->withSession(['account_access' => ['user_id' => $admin->id, 'mode' => 'invalid']])->get('/hr/raw-attendance')->assertForbidden();
        $this->post('/account/access-mode', ['mode' => 'admin'])->assertRedirect('/dashboard');
        $this->post('/account/access-mode', ['mode' => 'admin'])->assertRedirect('/dashboard');
        $this->assertDatabaseCount('audit_events', 2);
        $this->post('/logout')->assertRedirect('/');
        $this->actingAs($admin->fresh())->get('/hr/raw-attendance')->assertOk();
    }

    public function test_hr_mode_can_certify_requests_and_staff_mode_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'system_admin']);
        $employee = User::factory()->create();
        $leave = LeaveRequest::create(['user_id' => $employee->id, 'leave_type_id' => 1, 'filed_on' => today()->toDateString(), 'requested_days' => '1.000', 'created_by' => $employee->id]);
        $workflow = app(RequestWorkflow::class);
        $workflow->start('LEAVE', $leave, $employee);
        $this->actingAs($admin)->post('/account/access-mode', ['mode' => 'staff']);
        $this->postJson('/leave/'.$leave->id.'/transition', ['action' => 'certify'])->assertForbidden();
        $this->post('/account/access-mode', ['mode' => 'hr']);
        $this->postJson('/leave/'.$leave->id.'/transition', ['action' => 'certify'])->assertOk();
        $this->assertSame('For DC Approval', $workflow->current('LEAVE', $leave->id)->status);
    }

    public function test_admin_can_use_own_staff_wfh_feature_without_changing_permanent_role(): void
    {
        $division = Division::create(['name' => 'Example division']);
        $admin = User::factory()->create(['role' => 'system_admin', 'division_id' => $division->id, 'employee_type' => 'Permanent']);
        $this->actingAs($admin)->post('/account/access-mode', ['mode' => 'staff']);
        $this->postJson('/wfh-accomplishments', ['wfh_date' => '2026-10-21', 'accomplishment' => 'Example completed staff work'])->assertCreated();
        $this->assertDatabaseHas('wfh_accomplishments', ['user_id' => $admin->id, 'accomplishment' => 'Example completed staff work']);
        $this->assertSame('system_admin', $admin->fresh()->role);
        $chief = User::factory()->create(['role' => 'division_chief', 'division_id' => $division->id]);
        $accomplishment = WfhAccomplishment::where('user_id', $admin->id)->firstOrFail();
        $this->actingAs($chief)->postJson('/wfh-accomplishments/'.$accomplishment->id.'/transition', ['action' => 'approve'])->assertOk();
        $this->postJson('/wfh-accomplishments/consolidate', ['division_id' => $division->id, 'wfh_date' => '2026-10-21'])->assertOk();
        $this->assertTrue(WfhReport::firstOrFail()->lines()->where('user_id', $admin->id)->where('wfh_accomplishment_id', $accomplishment->id)->exists());
    }

    public function test_new_staff_model_matches_database_default_without_reloading(): void
    {
        $staff = User::factory()->create();
        $this->assertSame('staff', $staff->role);
        $this->assertSame('staff', $staff->accessRole());
        $this->actingAs($staff)->get('/dashboard')->assertOk()->assertDontSee('aria-label="Account access"', false);
    }
}
