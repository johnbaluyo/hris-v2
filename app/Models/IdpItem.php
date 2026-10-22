<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdpItem extends Model
{
    protected $fillable = ['idp_plan_id', 'training_requirement_id', 'proposal', 'title'];

    /** @return BelongsTo<IdpPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(IdpPlan::class, 'idp_plan_id');
    }

    /** @return BelongsTo<TrainingRequirement, $this> */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(TrainingRequirement::class, 'training_requirement_id');
    }
}
