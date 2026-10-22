<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveLedgerEntry extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'leave_type_id', 'direction', 'days', 'scope_date', 'source_type', 'source_id', 'idempotency_key', 'activity', 'actor_id', 'created_at'];

    protected function casts(): array
    {
        return ['days' => 'decimal:3', 'scope_date' => 'date:Y-m-d', 'created_at' => 'immutable_datetime'];
    }
}
