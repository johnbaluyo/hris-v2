<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ipcr extends Model
{
    protected $fillable = ['user_id', 'period_start', 'period_end', 'overall_rating', 'employee_snapshot'];

    protected function casts(): array
    {
        return ['period_start' => 'immutable_date', 'period_end' => 'immutable_date', 'overall_rating' => 'decimal:2', 'employee_snapshot' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<IpcrOutput, $this> */
    public function outputs(): HasMany
    {
        return $this->hasMany(IpcrOutput::class);
    }

    /** @return HasMany<RequestStatus, $this> */
    public function statuses(): HasMany
    {
        return $this->hasMany(RequestStatus::class, 'request_id')->where('request_type', 'IPCR');
    }
}
