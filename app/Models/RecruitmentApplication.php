<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed> $profile_snapshot
 */
class RecruitmentApplication extends Model
{
    /** @return HasMany<RequestStatus, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(RequestStatus::class, 'request_id')->where('request_type', 'RECRUITMENT')->orderBy('id');
    }

    protected $fillable = ['recruitment_vacancy_id', 'user_id', 'status', 'remark', 'allow_test', 'profile_snapshot'];

    protected function casts(): array
    {
        return ['allow_test' => 'boolean', 'profile_snapshot' => 'array'];
    }

    /** @return BelongsTo<RecruitmentVacancy, $this> */
    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(RecruitmentVacancy::class, 'recruitment_vacancy_id');
    }

    /** @return BelongsTo<User, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<ExamAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /** @return HasMany<RecruitmentDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(RecruitmentDocument::class);
    }

    /** @return HasMany<RecruitmentDocument, $this> */
    public function generalDocuments(): HasMany
    {
        return $this->hasMany(RecruitmentDocument::class, 'user_id', 'user_id')->whereNull('recruitment_application_id')->where('side', 'requirement');
    }
}
