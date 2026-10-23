<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed> $snapshot
 */
class RecognitionNominee extends Model
{
    public $timestamps = false;

    protected $fillable = ['recognition_event_id', 'user_id', 'snapshot', 'winner'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'winner' => 'boolean'];
    }

    /** @return HasMany<RecognitionVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(RecognitionVote::class);
    }
}
