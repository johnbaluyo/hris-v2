<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpcrOutput extends Model
{
    protected $fillable = ['ipcr_id', 'category', 'task'];

    /** @return BelongsTo<Ipcr, $this> */
    public function ipcr(): BelongsTo
    {
        return $this->belongsTo(Ipcr::class);
    }

    /** @return HasMany<IpcrIndicator, $this> */
    public function indicators(): HasMany
    {
        return $this->hasMany(IpcrIndicator::class);
    }
}
