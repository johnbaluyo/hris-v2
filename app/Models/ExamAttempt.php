<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int, array{id: int, question_type: int, question: string, choices: array<int, array{id: string, text: string, image_file_id: int|null}>, image_file_id: int|null}> $questions
 * @property array<int|string, string|null>|null $answers
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $submitted_at
 */
class ExamAttempt extends Model
{
    protected $fillable = ['recruitment_application_id', 'exam_category_id', 'questions', 'category_snapshot', 'time_limit', 'started_at', 'expires_at', 'submitted_at', 'answers'];

    protected function casts(): array
    {
        return ['questions' => 'array', 'category_snapshot' => 'array', 'answers' => 'array', 'time_limit' => 'integer', 'started_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'submitted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<RecruitmentApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }
}
