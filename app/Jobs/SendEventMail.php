<?php

namespace App\Jobs;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendEventMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $eventId, public int $userId, public string $revision) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $query = DB::table('event_mail_deliveries')->where('calendar_event_id', $this->eventId)->where('user_id', $this->userId)->where('revision', $this->revision);
            $delivery = (clone $query)->lockForUpdate()->first();
            $event = CalendarEvent::find($this->eventId);
            $user = User::find($this->userId);
            if (! $delivery || $delivery->sent_at || ! $event || ! $user || ! $event->participants()->where('users.id', $user->id)->exists()) {
                return;
            }
            Mail::raw($event->title."\n".$event->starts_at->format('Y-m-d H:i').' – '.$event->ends_at->format('Y-m-d H:i')." Asia/Manila\n".$event->description,
                fn ($message) => $message->to($user->email)->subject('Calendar: '.$event->title));
            $query->update(['sent_at' => now()]);
        });
    }
}
