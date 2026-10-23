<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property CarbonImmutable|null $closes_on
 */
class RecruitmentVacancy extends Model
{
    protected $fillable = ['position_id', 'status', 'description', 'closes_on'];

    protected function casts(): array
    {
        return ['closes_on' => 'immutable_date:Y-m-d'];
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /** @return BelongsToMany<ExamCategory, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ExamCategory::class, 'vacancy_categories');
    }

    /** @return BelongsToMany<User, $this> */
    public function evaluators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'recruitment_evaluators')->withPivot('role');
    }
}
