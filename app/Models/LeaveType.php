<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = ['code', 'name', 'accumulation', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
