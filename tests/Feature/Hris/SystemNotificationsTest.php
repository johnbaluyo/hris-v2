<?php

namespace Tests\Feature\Hris;

use App\Models\NotificationReceipt;
use App\Models\ServiceClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_credential_provisioning_does_not_replace_attendance_credentials(): void
    {
        $collector = ServiceClient::create(['name' => 'Example existing collector', 'token_hash' => hash('sha256', 'synthetic-collector'), 'scopes' => ['attendance.ingest'], 'devices' => ['READER']]);
        $this->artisan('notifications:client', ['name' => $collector->name])->assertFailed();
        $this->assertSame(['attendance.ingest'], $collector->fresh()->scopes);
        $this->artisan('notifications:client', ['name' => 'Example notice producer'])->assertSuccessful();
        $producer = ServiceClient::where('name', 'Example notice producer')->firstOrFail();
        $this->assertSame(['notifications.publish'], $producer->scopes);
        $this->assertSame([], $producer->devices);
        $this->artisan('notifications:client', ['name' => $producer->name, '--revoke' => true])->assertSuccessful();
        $this->assertNotNull($producer->fresh()->revoked_at);
    }

    public function test_role_membership_is_snapshotted_and_expiry_command_retains_receipts(): void
    {
        $admin = User::factory()->create(['role' => 'system_admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $payload = ['event_id' => 'role-event', 'title' => 'Role notice', 'message' => 'Example role message', 'notification_type' => 'role_based', 'target_roles' => ['staff'], 'expires_at' => now()->addHour()->toIso8601String()];
        $this->actingAs($admin)->postJson('/api/notifications/create', $payload)->assertCreated();
        $newStaff = User::factory()->create(['role' => 'staff']);
        $staff->forceFill(['role' => 'hr'])->save();
        $this->postJson('/api/notifications/create', $payload)->assertCreated();
        $this->assertDatabaseCount('notification_receipts', 1);
        $this->actingAs($newStaff)->getJson('/api/notifications/count')->assertJsonPath('total', 0);
        $this->actingAs($staff->fresh())->getJson('/api/notifications/count')->assertJsonPath('total', 1);
        $this->travel(2)->hours();
        $this->artisan('notifications:expire')->assertSuccessful();
        $this->artisan('notifications:expire')->assertSuccessful();
        $this->assertDatabaseCount('notification_receipts', 1);
        $this->assertDatabaseHas('system_notifications', ['is_active' => false]);
    }

    public function test_broadcast_receipts_are_independent_idempotent_and_expire_without_read_mutation(): void
    {
        $admin = User::factory()->create(['role' => 'system_admin']);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $payload = ['title' => 'Example notice', 'message' => 'Example message', 'notification_type' => 'broadcast', 'url' => '/dashboard', 'expires_at' => now()->addHour()->toIso8601String()];
        $this->actingAs($a)->postJson('/api/notifications/create', $payload)->assertForbidden();
        $id = $this->actingAs($admin)->postJson('/api/notifications/create', $payload)->assertCreated()->json('notification_id');
        $this->actingAs($a)->get('/notifications')->assertOk();
        $this->getJson('/api/notifications/count')->assertJsonPath('total', 1)->assertJsonPath('unread', 1);
        $this->postJson('/api/notifications/read', ['id' => $id])->assertOk();
        $read = NotificationReceipt::where('user_id', $a->id)->first()->read_at;
        $this->travel(1)->minute();
        $this->postJson('/api/notifications/read', ['id' => $id])->assertOk();
        $this->assertEquals($read, NotificationReceipt::where('user_id', $a->id)->first()->read_at);
        $this->postJson('/api/notifications/dismiss-all', [])->assertJsonPath('count', 1);
        $this->getJson('/api/notifications/count')->assertJsonPath('total', 0);
        $this->actingAs($b)->getJson('/api/notifications/count')->assertJsonPath('total', 1)->assertJsonPath('unread', 1);
        $this->travel(1)->hours();
        $this->getJson('/api/notifications/count')->assertJsonPath('total', 0);
        $this->assertDatabaseHas('system_notifications', ['id' => $id, 'is_active' => true]);
    }

    public function test_personal_scope_typed_audiences_and_destinations_are_enforced(): void
    {
        $admin = User::factory()->create(['role' => 'system_admin']);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $payload = ['title' => 'Personal', 'message' => 'Private message', 'notification_type' => 'personal', 'user_id' => $a->id];
        $this->actingAs($admin)->postJson('/api/notifications/create', $payload + ['url' => 'javascript:alert(1)'])->assertUnprocessable();
        $this->postJson('/api/notifications/create', $payload + ['url' => '//untrusted.example/path'])->assertUnprocessable();
        $this->postJson('/api/notifications/create', $payload + ['user_email' => $b->email])->assertUnprocessable();
        $id = $this->postJson('/api/notifications/create', $payload)->assertCreated()->json('notification_id');
        $this->postJson('/api/notifications/create', ['title' => 'Bad mixed audience', 'message' => 'Example', 'notification_type' => 'role_based', 'target_users' => [1, '1']])->assertUnprocessable();
        $this->actingAs($b)->getJson('/api/notifications')->assertJsonCount(0, 'notifications');
        $this->postJson('/api/notifications/read', ['id' => $id])->assertNotFound();
        $this->actingAs($a)->getJson('/api/notifications')->assertJsonPath('notifications.0.notification_id', $id);
    }

    public function test_external_producers_require_separate_scope_and_stable_event_id(): void
    {
        $recipient = User::factory()->create();
        $client = ServiceClient::create(['name' => 'Example producer', 'token_hash' => hash('sha256', 'synthetic-notice-token'), 'scopes' => ['attendance.ingest'], 'devices' => []]);
        $payload = ['event_id' => 'example-event-1', 'title' => 'Partner task', 'message' => 'Example task', 'notification_type' => 'personal', 'user_email' => $recipient->email, 'url' => '/dashboard'];
        $this->postJson('/api/notifications/external-create', $payload)->assertUnauthorized();
        $this->withToken('synthetic-notice-token')->postJson('/api/notifications/external-create', $payload)->assertForbidden();
        $client->update(['scopes' => ['notifications.publish']]);
        $id = $this->postJson('/api/notifications/external-create', $payload)->assertCreated()->json('notification_id');
        $this->postJson('/api/notifications/external-create', $payload)->assertCreated()->assertJsonPath('notification_id', $id);
        $this->postJson('/api/notifications/external-create', array_merge($payload, ['message' => 'Conflicting event']))->assertConflict();
        $this->assertDatabaseCount('system_notifications', 1);
        $this->assertDatabaseCount('notification_receipts', 1);
        $this->postJson('/api/notifications/external-create', array_merge($payload, ['notification_type' => 'broadcast']))->assertForbidden();
        $this->postJson('/api/notifications/external-create', array_merge($payload, ['event_id' => 'other', 'url' => 'https://untrusted.example/path']))->assertUnprocessable();
    }
}
