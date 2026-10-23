<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<int, string> $voter_types
 * @property CarbonImmutable|null $opens_at
 * @property CarbonImmutable|null $closes_at
 * @property CarbonImmutable|null $published_at
 */
class RecognitionEvent extends Model
{
    protected $fillable = ['name', 'type', 'coverage_start', 'coverage_end', 'recognition_date', 'opens_at', 'closes_at', 'allow_self_vote', 'voter_types', 'archived_at', 'published_at', 'published_by'];

    protected function casts(): array
    {
        return ['coverage_start' => 'immutable_date:Y-m-d', 'coverage_end' => 'immutable_date:Y-m-d', 'recognition_date' => 'immutable_date:Y-m-d', 'opens_at' => 'immutable_datetime', 'closes_at' => 'immutable_datetime', 'allow_self_vote' => 'boolean', 'voter_types' => 'array', 'archived_at' => 'immutable_datetime', 'published_at' => 'immutable_datetime'];
    }

    /** @return HasMany<RecognitionNominee, $this> */
    public function nominees(): HasMany
    {
        return $this->hasMany(RecognitionNominee::class);
    }
}
