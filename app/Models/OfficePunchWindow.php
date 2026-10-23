<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable|null $revoked_at
 */
class OfficePunchWindow extends Model
{
    protected $fillable = ['starts_at', 'ends_at', 'all_employees', 'reason', 'created_by', 'revoked_at', 'revoked_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'all_employees' => 'boolean', 'revoked_at' => 'immutable_datetime'];
    }

    /** @return BelongsToMany<User, $this> */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'office_punch_window_users');
    }
}
