<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $id @property int $remaining_minutes */
class CocEntry extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'direction', 'minutes', 'remaining_minutes', 'scope_date', 'expires_on', 'source_type', 'source_id', 'idempotency_key', 'remarks', 'actor_id', 'created_at'];

    protected function casts(): array
    {
        return ['minutes' => 'integer', 'remaining_minutes' => 'integer', 'scope_date' => 'date:Y-m-d', 'expires_on' => 'date:Y-m-d', 'created_at' => 'immutable_datetime'];
    }
}
