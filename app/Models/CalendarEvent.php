<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 */
class CalendarEvent extends Model
{
    protected $fillable = ['user_id', 'division_id', 'event_type', 'title', 'description', 'all_day', 'starts_at', 'ends_at'];

    /** @return BelongsToMany<User, $this> */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['all_day' => 'boolean', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }
}
