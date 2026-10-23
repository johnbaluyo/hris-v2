<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $wfh_date
 */
class WfhAccomplishment extends Model
{
    /** @return HasMany<RequestStatus, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(RequestStatus::class, 'request_id')->where('request_type', 'WFH')->orderBy('id');
    }

    protected $fillable = ['user_id', 'division_id', 'wfh_date', 'accomplishment', 'status', 'return_remarks', 'archived_at'];

    protected function casts(): array
    {
        return ['wfh_date' => 'immutable_date:Y-m-d', 'archived_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
