<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $requested_minutes
 * @property int $overtime_type
 * @property Collection<int, OvertimeDate> $dates
 */
class OvertimeRequest extends Model
{
    protected $fillable = ['user_id', 'filed_on', 'day_type', 'start_time', 'requested_minutes', 'overtime_type', 'purpose', 'attachment_file_id', 'created_by'];

    protected function casts(): array
    {
        return ['filed_on' => 'date:Y-m-d', 'day_type' => 'integer', 'overtime_type' => 'integer', 'requested_minutes' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<OvertimeDate, $this> */
    public function dates(): HasMany
    {
        return $this->hasMany(OvertimeDate::class);
    }

    /** @return HasMany<RequestStatus, $this> */
    public function statuses(): HasMany
    {
        return $this->hasMany(RequestStatus::class, 'request_id')->where('request_type', 'OT');
    }
}
