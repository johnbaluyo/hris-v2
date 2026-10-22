<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalnEntry extends Model
{
    protected $fillable = ['saln_filing_id', 'section', 'data', 'declared_amount'];

    protected function casts(): array
    {
        return ['data' => 'array', 'declared_amount' => 'decimal:2'];
    }

    /** @return BelongsTo<SalnFiling, $this> */
    public function filing(): BelongsTo
    {
        return $this->belongsTo(SalnFiling::class, 'saln_filing_id');
    }
}
