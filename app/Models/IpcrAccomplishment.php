<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpcrAccomplishment extends Model
{
    protected $fillable = ['ipcr_indicator_id', 'accomplishment', 'quality', 'efficiency', 'timeliness', 'rated_by'];

    protected function casts(): array
    {
        return ['quality' => 'integer', 'efficiency' => 'integer', 'timeliness' => 'integer'];
    }

    /** @return BelongsTo<IpcrIndicator, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(IpcrIndicator::class, 'ipcr_indicator_id');
    }
}
