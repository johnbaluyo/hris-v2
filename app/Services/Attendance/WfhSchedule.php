<?php

namespace App\Services\Attendance;

use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;

class WfhSchedule
{
    public function isAvailableToday(): bool
    {
        return $this->isAvailableOn(today('Asia/Manila')->toDateString());
    }

    public function isAvailableOn(string $date): bool
    {
        $today = CarbonImmutable::parse($date, 'Asia/Manila');

        return $today->isWednesday() || CalendarEvent::where('event_type', 'Work From Home')
            ->whereDate('starts_at', '<=', $today->toDateString())
            ->whereDate('ends_at', '>=', $today->toDateString())->exists();
    }
}
