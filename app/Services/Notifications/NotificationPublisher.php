<?php

namespace App\Services\Notifications;

use App\Models\NotificationReceipt;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationPublisher
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $recipients
     * @param  array<int, string>  $roles
     * @param  array<int, int>  $explicitUsers
     */
    public function publish(array $data, array $recipients, array $roles = [], array $explicitUsers = []): SystemNotification
    {
        sort($recipients);
        sort($roles);
        sort($explicitUsers);
        $hash = hash('sha256', json_encode([$data, $data['notification_type'] === 'personal' ? $recipients : $explicitUsers, $roles], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $recipients, $roles, $hash) {
            $notice = SystemNotification::firstOrCreate(['source_key' => $data['source_key'], 'event_id' => $data['event_id']],
                $data + ['payload_hash' => $hash]);
            abort_unless(hash_equals($notice->payload_hash, $hash), 409, 'This event ID was already used with different content.');
            if ($notice->wasRecentlyCreated) {
                foreach ($roles as $role) {
                    DB::table('notification_audiences')->insert(['system_notification_id' => $notice->id, 'role' => $role]);
                }
                foreach ($recipients as $id) {
                    DB::table('notification_audiences')->insert(['system_notification_id' => $notice->id, 'user_id' => $id]);
                    NotificationReceipt::create(['system_notification_id' => $notice->id, 'user_id' => $id]);
                }
            }

            return $notice;
        });
    }

    public function afterCommit(User $recipient, string $event, string $title, string $message, string $url): void
    {
        DB::afterCommit(function () use ($recipient, $event, $title, $message, $url) {
            try {
                $this->publish(['source_key' => 'HRIS', 'event_id' => $event, 'system' => 'HRIS', 'source' => 'HRIS',
                    'notification_type' => 'personal', 'title' => $title, 'message' => $message, 'url' => $url], [$recipient->id]);
            } catch (\Throwable $exception) {
                report($exception);
            }
        });
    }
}
