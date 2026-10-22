<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DivisionLearningPlan extends Model
{
    protected $fillable = ['division_id', 'plan_year', 'total_budget', 'staff_count', 'created_by'];

    protected function casts(): array
    {
        return ['plan_year' => 'integer', 'total_budget' => 'decimal:2', 'staff_count' => 'integer'];
    }

    /** @return HasMany<DivisionLearningPlanLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(DivisionLearningPlanLine::class);
    }

    /** @return BelongsTo<Division, $this> */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}
