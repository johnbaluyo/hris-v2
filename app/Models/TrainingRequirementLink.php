<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingRequirementLink extends Model
{
    protected $fillable = ['pds_entry_id', 'training_requirement_id', 'actor_id'];

    /** @return BelongsTo<PdsEntry, $this> */
    public function training(): BelongsTo
    {
        return $this->belongsTo(PdsEntry::class, 'pds_entry_id');
    }

    /** @return BelongsTo<TrainingRequirement, $this> */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(TrainingRequirement::class, 'training_requirement_id');
    }
}
