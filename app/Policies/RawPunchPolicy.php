<?php

namespace App\Policies;

use App\Models\RawPunch;
use App\Models\User;

class RawPunchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'system_admin' && $user->hasAbility('attendance.raw.manage');
    }

    public function update(User $user, RawPunch $punch): bool
    {
        return $this->viewAny($user);
    }
}
