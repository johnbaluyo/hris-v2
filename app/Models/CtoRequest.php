<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $total_minutes
 * @property Collection<int, CtoDate> $dates
 */
class CtoRequest extends Model
{
    protected $fillable = ['user_id', 'filed_on', 'total_minutes', 'balance_snapshot_minutes', 'created_by'];

    protected function casts(): array
    {
        return ['filed_on' => 'date:Y-m-d', 'total_minutes' => 'integer', 'balance_snapshot_minutes' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<CtoDate, $this> */
    public function dates(): HasMany
    {
        return $this->hasMany(CtoDate::class);
    }

    /** @return HasMany<RequestStatus, $this> */
    public function statuses(): HasMany
    {
        return $this->hasMany(RequestStatus::class, 'request_id')->where('request_type', 'CTO');
    }
}
