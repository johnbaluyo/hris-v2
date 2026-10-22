<?php

namespace App\Services\Attendance;

use App\Models\Delegation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AttendanceScope
{
    /** @return Builder<User> */
    public function employees(User $actor): Builder
    {
        $query = User::query()->where('role', '!=', 'applicant');
        if (! $actor->isEligible() || $actor->role === 'applicant') {
            return $query->whereRaw('1 = 0');
        }
        if ($actor->hasAbility('attendance.read')) {
            return $query;
        }
        $divisions = $actor->role === 'staff'
            ? Delegation::where('user_id', $actor->id)->where('ability', 'division-chief.oic')
                ->whereDate('period_start', '<=', today('Asia/Manila'))->whereDate('period_end', '>=', today('Asia/Manila'))->pluck('division_id')->all()
            : [];
        if ($actor->role === 'division_chief' && $actor->division_id !== null) {
            $divisions[] = $actor->division_id;
        }

        return $query->where(fn ($q) => $q->whereKey($actor->id)->orWhereIn('division_id', $divisions));
    }
}
