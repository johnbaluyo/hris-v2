<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationRun extends Model
{
    protected $fillable = ['partner', 'actor_id', 'status', 'message'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [];
    }
}
