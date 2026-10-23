<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamCategory extends Model
{
    protected $fillable = ['name', 'exam_type', 'time_limit', 'archived_at'];

    protected function casts(): array
    {
        return ['time_limit' => 'integer', 'archived_at' => 'immutable_datetime'];
    }

    /** @return HasMany<ExamQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class);
    }
}
