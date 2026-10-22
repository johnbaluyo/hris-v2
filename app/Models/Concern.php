<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Concern extends Model
{
    protected $fillable = ['user_id', 'subject', 'description', 'response', 'responded_by', 'resolved_at', 'staff_acknowledged_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'immutable_datetime', 'staff_acknowledged_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<ConcernAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ConcernAttachment::class);
    }

    /** @return HasMany<RequestStatus, $this> */
    public function statuses(): HasMany
    {
        return $this->hasMany(RequestStatus::class, 'request_id')->where('request_type', 'CONCERN');
    }
}
