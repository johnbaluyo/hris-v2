<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string|null $action
 * @property string $status
 * @property CarbonImmutable $created_at
 * @property User $actor
 */
class RequestStatus extends Model
{
    public $timestamps = false;

    protected $fillable = ['request_type', 'request_id', 'status', 'actor_id', 'action', 'reason', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
