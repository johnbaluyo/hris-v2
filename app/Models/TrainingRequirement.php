<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingRequirement extends Model
{
    protected $fillable = ['position_id', 'category', 'title', 'created_by', 'archived_at'];

    protected function casts(): array
    {
        return ['category' => 'integer', 'archived_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
