<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed> $snapshot
 * @property array<string, int>|null $compensation
 * @property CarbonImmutable|null $issued_at
 * @property CarbonImmutable|null $completed_at
 */
class EmploymentCertificate extends Model
{
    /** @return HasMany<RequestStatus, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(RequestStatus::class, 'request_id')->where('request_type', 'COE')->orderBy('id');
    }

    protected $fillable = ['user_id', 'type', 'status', 'verification_token', 'purpose', 'snapshot', 'compensation', 'annual_total_cents', 'benefit_total_cents', 'grand_total_cents', 'acknowledged_by', 'issued_by', 'issued_at', 'completed_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'compensation' => 'array', 'annual_total_cents' => 'integer', 'benefit_total_cents' => 'integer', 'grand_total_cents' => 'integer', 'issued_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<CertificateFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(CertificateFile::class);
    }
}
