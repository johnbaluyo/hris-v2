<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;

class CalendarEventPolicy
{
    public function view(User $user, CalendarEvent $event): bool
    {
        if ($user->role === 'applicant') {
            return false;
        }
        if ($event->user_id === $user->id || $event->participants()->where('users.id', $user->id)->exists()) {
            return true;
        }

        return match ($event->event_type) {
            'My Calendar' => false,
            'Executive Director Activities' => $user->hasAbility('calendar.manage') || $user->role === 'executive_director',
            default => true,
        };
    }

    public function update(User $user, CalendarEvent $event): bool
    {
        return $event->event_type === 'My Calendar'
            ? $user->id === $event->user_id
            : $user->hasAbility('calendar.manage');
    }

    public function delete(User $user, CalendarEvent $event): bool
    {
        return $this->update($user, $event);
    }
}
