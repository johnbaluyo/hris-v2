<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdpPlan extends Model
{
    protected $fillable = ['user_id', 'plan_year', 'coverage_years', 'purpose', 'other_purpose', 'employee_snapshot', 'submitted_at', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['plan_year' => 'integer', 'coverage_years' => 'array', 'purpose' => 'integer', 'employee_snapshot' => 'array', 'submitted_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<IdpItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(IdpItem::class);
    }
}
