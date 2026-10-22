<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalnFiling extends Model
{
    protected $fillable = ['user_id', 'filing_type', 'compliance_type', 'compliance_date'];

    protected function casts(): array
    {
        return ['filing_type' => 'integer', 'compliance_type' => 'integer', 'compliance_date' => 'immutable_date'];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<SalnEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(SalnEntry::class);
    }
}
