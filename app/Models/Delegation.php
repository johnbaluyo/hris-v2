<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delegation extends Model
{
    protected $fillable = ['user_id', 'division_id', 'ability', 'period_start', 'period_end', 'actor_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['period_start' => 'date:Y-m-d', 'period_end' => 'date:Y-m-d'];
    }
}
