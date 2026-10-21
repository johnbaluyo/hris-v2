<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon $assume_date */
class PositionAssignment extends Model
{
    protected $fillable = ['user_id', 'old_position_id', 'position_id', 'actor_id', 'assume_date'];

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['assume_date' => 'date:Y-m-d'];
    }
}
