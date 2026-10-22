<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $coc_entry_id @property int $minutes */
class CocAllocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['cto_request_id', 'coc_entry_id', 'minutes', 'reversed_at'];

    protected function casts(): array
    {
        return ['minutes' => 'integer', 'reversed_at' => 'immutable_datetime'];
    }
}
