<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delegation extends Model
{
    protected $fillable = ['user_id', 'division_id', 'ability', 'period_start', 'period_end', 'actor_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['period_start' => 'date:Y-m-d', 'period_end' => 'date:Y-m-d'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Division, $this> */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}
