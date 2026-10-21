<?php

namespace App\Policies;

use App\Models\User;

class EmployeePolicy
{
    public function manage(User $actor): bool
    {
        return $actor->hasAbility('staff.manage');
    }

    public function readPds(User $actor, User $employee): bool
    {
        return ($actor->id === $employee->id && $actor->role !== 'applicant') || $actor->hasAbility('pds.read');
    }

    public function writePds(User $actor, User $employee): bool
    {
        return ($actor->id === $employee->id && $actor->role !== 'applicant') || $actor->hasAbility('pds.write');
    }

    public function attendance(User $actor, User $employee): bool
    {
        return $actor->canViewAttendanceOf($employee);
    }

    public function correctAttendance(User $actor, User $employee): bool
    {
        return $actor->hasAbility('attendance.correct');
    }
}
