<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRevision extends Model
{
    protected $fillable = ['daily_attendance_id', 'actor_id', 'reason', 'before', 'after', 'created_at'];

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }
}
