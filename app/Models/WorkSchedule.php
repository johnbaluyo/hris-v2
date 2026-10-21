<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 */
class WorkSchedule extends Model
{
    protected $fillable = ['user_id', 'starts_on', 'ends_on', 'workweek', 'policy_version', 'actor_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date:Y-m-d', 'ends_on' => 'immutable_date:Y-m-d'];
    }
}
