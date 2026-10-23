<?php

namespace App\Support;

use App\Models\User;

class EmployeeSnapshot
{
    /** @return array<string, mixed> */
    public static function for(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'position' => $user->position?->title,
            'division' => $user->division?->name, 'division_id' => $user->division_id,
            'salary_grade' => $user->position?->salary_grade, 'hired_on' => $user->date_hired?->toDateString(),
            'terminated_on' => $user->termination_date?->toDateString(), 'employee_type' => $user->employee_type];
    }
}
