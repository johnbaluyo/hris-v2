<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property CarbonImmutable|null $date_hired
 * @property CarbonImmutable|null $termination_date
 * @property CarbonImmutable|null $birthday
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_archived' => 'boolean',
            'date_hired' => 'immutable_date:Y-m-d',
            'termination_date' => 'immutable_date:Y-m-d',
            'birthday' => 'immutable_date:Y-m-d',
            'division_id' => 'integer',
            'position_id' => 'integer',
            'workweek' => 'integer',
        ];
    }

    public function isEligible(): bool
    {
        return ! $this->is_archived && ($this->termination_date === null || $this->termination_date->isAfter(today('Asia/Manila')));
    }

    public function hasAbility(string $ability): bool
    {
        if (! $this->isEligible() || $this->role === 'applicant') {
            return false;
        }

        return in_array($ability, config('hris.roles.'.$this->role, []), true)
            || DB::table('ability_grants')->where('user_id', $this->id)->where('ability', $ability)->exists();
    }

    public function canViewAttendanceOf(self $employee): bool
    {
        if ($this->role === 'applicant' || ! $this->isEligible()) {
            return false;
        }
        if ($this->id === $employee->id || $this->hasAbility('attendance.read')) {
            return true;
        }
        if ($employee->division_id === null) {
            return false;
        }

        return ($this->hasAbility('attendance.division') && $this->division_id === $employee->division_id)
            || Delegation::where('user_id', $this->id)->where('division_id', $employee->division_id)
                ->where('ability', 'attendance.division')->whereDate('period_start', '<=', today('Asia/Manila'))
                ->whereDate('period_end', '>=', today('Asia/Manila'))->exists();
    }

    /** @return BelongsTo<Division, $this> */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /** @return HasMany<PdsEntry, $this> */
    public function pdsEntries(): HasMany
    {
        return $this->hasMany(PdsEntry::class);
    }

    /** @return HasMany<PositionAssignment, $this> */
    public function positionAssignments(): HasMany
    {
        return $this->hasMany(PositionAssignment::class);
    }

    /** @return HasMany<DailyAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(DailyAttendance::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
