<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanCategory extends Model
{
    protected $fillable = ['provider', 'name', 'retired_at'];

    protected function casts(): array
    {
        return ['retired_at' => 'immutable_datetime'];
    }
}
