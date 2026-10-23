<?php

namespace Tests\Feature\Hris;

use App\Models\AuditEvent;
use App\Models\OfficePunchWindow;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfficePunchAccessTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['starts_at' => '2026-10-23T08:00', 'ends_at' => '2026-10-23T10:00', 'reason' => 'Example temporary office capture'];
    }

    public static function boundaries(): array
    {
        return [
            'before start' => ['2026-10-22T23:59:59+00:00', false],
            'at Manila start' => ['2026-10-23T00:00:00+00:00', true],
            'during window' => ['2026-10-23T01:59:59+00:00', true],
            'at end' => ['2026-10-23T02:00:00+00:00', false],
            'after end' => ['2026-10-23T02:00:01+00:00', false],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_button_and_endpoint_enforce_manila_window_boundaries(string $now, bool $available): void
    {
        $this->travelTo(CarbonImmutable::parse($now));
        $admin = User::factory()->create(['role' => 'system_admin']);
        $employee = User::factory()->create(['atms_id' => '9001']);
        OfficePunchWindow::create($this->payload() + ['created_by' => $admin->id]);
        foreach (['/attendance', '/dashboard', '/ssi'] as $url) {
            $page = $this->actingAs($employee)->get($url)->assertOk()->assertViewHas('officeAccess', fn ($access) => $access['office'] === $available);
            $hidden = '/<button\b[^>]*id="clock-office"[^>]*\bhidden\b[^>]*>/';
            if ($available) {
                $this->assertDoesNotMatchRegularExpression($hidden, $page->getContent());
            } else {
                $this->assertMatchesRegularExpression($hidden, $page->getContent());
            }
            if ($url !== '/attendance') {
                $page->assertSeeInOrder(['View my attendance', 'id="clock-office"'], false);
            }
        }
        $this->getJson('/attendance/clock-availability')->assertOk()->assertJsonPath('office', $available);
        $this->postJson('/attendance/clock', ['wfh' => false])->assertStatus($available ? 201 : 422);
        $this->assertDatabaseCount('raw_punches', $available ? 1 : 0);
    }

    public function test_only_admin_can_schedule_or_revoke_and_missing_windows_disable_office_punches(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 08:30:00', 'Asia/Manila'));
        $staff = User::factory()->create(['atms_id' => '9001']);
        $hr = User::factory()->create(['role' => 'hr']);
        $admin = User::factory()->create(['role' => 'system_admin']);
        foreach ([$staff, $hr] as $user) {
            $this->actingAs($user)->postJson('/attendance/office-punch-windows', $this->payload())->assertForbidden();
            $this->getJson('/attendance/office-punch-windows')->assertForbidden();
            $this->get('/attendance')->assertOk()->assertDontSee('Set office punch duration');
        }
        $this->actingAs($staff)->postJson('/attendance/clock', ['wfh' => false])->assertUnprocessable();
        $this->actingAs($admin)->get('/attendance')->assertOk()->assertSee('Set office punch duration');
        $id = $this->postJson('/attendance/office-punch-windows', $this->payload())->assertCreated()->json('id');
        $this->actingAs($staff)->postJson('/attendance/clock', ['wfh' => false])->assertCreated();
        $this->postJson('/attendance/office-punch-windows/'.$id.'/revoke')->assertForbidden();
        $this->actingAs($admin)->postJson('/attendance/office-punch-windows/'.$id.'/revoke')->assertOk();
        $this->postJson('/attendance/office-punch-windows/'.$id.'/revoke')->assertOk();
        $this->actingAs($staff)->postJson('/attendance/clock', ['wfh' => false])->assertUnprocessable();
        $this->assertSame(1, AuditEvent::where('action', 'office-punch-window.revoked')->count());
        $this->assertSame($admin->id, OfficePunchWindow::findOrFail($id)->created_by);
    }

    public function test_windows_apply_to_every_employee_and_revoking_one_overlap_does_not_close_another(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 08:30:00', 'Asia/Manila'));
        $admin = User::factory()->create(['role' => 'system_admin']);
        $employee = User::factory()->create(['atms_id' => '9001']);
        $other = User::factory()->create(['atms_id' => '9002']);
        $payload = $this->payload();
        $first = $this->actingAs($admin)->postJson('/attendance/office-punch-windows', $payload)->assertCreated()->json('id');
        $second = $this->postJson('/attendance/office-punch-windows', $payload)->assertCreated()->json('id');
        $this->postJson('/attendance/office-punch-windows/'.$first.'/revoke')->assertOk();
        $this->actingAs($employee)->getJson('/attendance/clock-availability')->assertJsonPath('office', true);
        $this->actingAs($other)->postJson('/attendance/clock', ['wfh' => false])->assertCreated();
        $this->actingAs($admin)->postJson('/attendance/office-punch-windows/'.$second.'/revoke')->assertOk();
        $this->actingAs($employee)->getJson('/attendance/clock-availability')->assertJsonPath('office', false);
    }

    public function test_invalid_expired_windows_and_employee_specific_scopes_are_rejected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 08:30:00', 'Asia/Manila'));
        $admin = User::factory()->create(['role' => 'system_admin']);
        $this->actingAs($admin);
        foreach ([['ends_at' => '2026-10-23T07:00'], ['ends_at' => '2026-10-23T08:15'], ['all_employees' => false, 'employee_ids' => []]] as $changes) {
            $this->postJson('/attendance/office-punch-windows', array_merge($this->payload(), $changes))->assertUnprocessable();
        }
        $employee = User::factory()->create();
        $this->postJson('/attendance/office-punch-windows', $this->payload() + ['employee_ids' => [$employee->id]])->assertUnprocessable();
        $this->assertDatabaseCount('office_punch_windows', 0);
    }

    public function test_existing_windows_also_follow_the_global_access_rule(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 08:30:00', 'Asia/Manila'));
        $admin = User::factory()->create(['role' => 'system_admin']);
        $originalEmployee = User::factory()->create();
        $otherEmployee = User::factory()->create(['atms_id' => '9002']);
        $window = OfficePunchWindow::create($this->payload() + ['all_employees' => false, 'created_by' => $admin->id]);
        $window->employees()->attach($originalEmployee->id);
        $this->actingAs($otherEmployee)->getJson('/attendance/clock-availability')->assertJsonPath('office', true);
        $this->postJson('/attendance/clock', ['wfh' => false])->assertCreated();
    }
}
