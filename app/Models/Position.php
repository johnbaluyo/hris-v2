<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Position extends Model
{
    protected $fillable = ['division_id', 'title', 'code', 'plantilla', 'classification', 'status'];

    /** @return BelongsTo<Division, $this> */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /** @return HasOne<User, $this> */
    public function employee(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['plantilla' => 'boolean', 'division_id' => 'integer'];
    }
}
