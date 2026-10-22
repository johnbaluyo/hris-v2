<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $leave_request_id @property \Illuminate\Support\Carbon $leave_date */
class LeaveDate extends Model
{
    public $timestamps = false;

    protected $fillable = ['leave_request_id', 'leave_date', 'day_fraction'];

    protected function casts(): array
    {
        return ['leave_date' => 'date:Y-m-d', 'day_fraction' => 'decimal:3'];
    }

    /** @return BelongsTo<LeaveRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }
}
