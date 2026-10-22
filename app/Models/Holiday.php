<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Holiday extends Model
{
    protected $fillable = ['name', 'holiday_date', 'type', 'agency_wide', 'suspension_time', 'source', 'archived_at'];

    protected function casts(): array
    {
        return ['holiday_date' => 'immutable_date', 'agency_wide' => 'boolean', 'archived_at' => 'immutable_datetime'];
    }

    /** @return BelongsToMany<User, $this> */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @param Builder<Holiday> $query
     * @return Builder<Holiday>
     */
    public function scopeEffectiveFor(Builder $query, User $employee): Builder
    {
        return $query->whereNull('archived_at')->where(fn (Builder $scope) => $scope->where('agency_wide', true)
            ->orWhereHas('employees', fn (Builder $employees) => $employees->whereKey($employee->id)));
    }
}
