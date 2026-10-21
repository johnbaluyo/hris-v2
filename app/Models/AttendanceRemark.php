<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRemark extends Model
{
    protected $fillable = ['user_id', 'coverage', 'fc_absence', 'remarks', 'actor_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['coverage' => 'date:Y-m-d'];
    }
}
