<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CarbonImmutable $attendance_date
 * @property string|null $time_in
 * @property string|null $lunch_out
 * @property string|null $lunch_in
 * @property string|null $time_out
 * @property string|null $ot_in
 * @property string|null $ot_out
 * @property array<string, mixed> $provenance
 */
class DailyAttendance extends Model
{
    protected $fillable = ['user_id', 'attendance_date', 'time_in', 'lunch_out', 'lunch_in', 'time_out', 'ot_in', 'ot_out', 'workweek', 'worked_minutes', 'late_minutes', 'undertime_minutes', 'overtime_minutes', 'policy_version', 'needs_review', 'is_override', 'notes', 'provenance'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['attendance_date' => 'immutable_date:Y-m-d', 'needs_review' => 'boolean', 'is_override' => 'boolean', 'provenance' => 'array'];
    }
}
