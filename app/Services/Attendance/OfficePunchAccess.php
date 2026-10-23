<?php

namespace App\Services\Attendance;

use App\Models\OfficePunchWindow;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OfficePunchAccess
{
    /** @return Builder<OfficePunchWindow> */
    private function windows(): Builder
    {
        return OfficePunchWindow::whereNull('revoked_at');
    }

    public function isAvailable(User $employee): bool
    {
        return $employee->role !== 'applicant' && $employee->isEligible() && $this->windows()
            ->where('starts_at', '<=', now('Asia/Manila'))->where('ends_at', '>', now('Asia/Manila'))->exists();
    }

    /** @return array{office: bool, next_change_at: string|null, server_time: string} */
    public function availability(User $employee): array
    {
        $next = $this->windows()->where('ends_at', '>', now('Asia/Manila'))->get()
            ->flatMap(fn (OfficePunchWindow $window) => [$window->starts_at, $window->ends_at])
            ->filter(fn ($time) => $time->gt(now('Asia/Manila')))->sort()->first();

        return ['office' => $this->isAvailable($employee), 'next_change_at' => $next?->toIso8601String(), 'server_time' => now('Asia/Manila')->toIso8601String()];
    }
}
