<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpcrIndicator extends Model
{
    protected $fillable = ['ipcr_output_id', 'description'];

    /** @return BelongsTo<IpcrOutput, $this> */
    public function output(): BelongsTo
    {
        return $this->belongsTo(IpcrOutput::class, 'ipcr_output_id');
    }

    /** @return HasMany<IpcrAccomplishment, $this> */
    public function accomplishments(): HasMany
    {
        return $this->hasMany(IpcrAccomplishment::class);
    }
}
