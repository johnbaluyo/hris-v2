<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $leave_type_id
 * @property string $requested_days
 * @property string|null $paid_days
 * @property Carbon $filed_on
 * @property Collection<int, LeaveDate> $dates
 */
class LeaveRequest extends Model
{
    protected $fillable = ['user_id', 'leave_type_id', 'filed_on', 'detail_code', 'details', 'commutation', 'for_disapproval', 'requested_days', 'paid_days', 'unpaid_days', 'recommendation_reason', 'return_message', 'created_by'];

    protected function casts(): array
    {
        return ['filed_on' => 'date:Y-m-d', 'for_disapproval' => 'boolean', 'requested_days' => 'decimal:3', 'paid_days' => 'decimal:3', 'unpaid_days' => 'decimal:3'];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    /** @return HasMany<LeaveDate, $this> */
    public function dates(): HasMany
    {
        return $this->hasMany(LeaveDate::class);
    }

    /** @return HasMany<RequestStatus, $this> */
    public function statuses(): HasMany
    {
        return $this->hasMany(RequestStatus::class, 'request_id')->where('request_type', 'LEAVE');
    }
}
