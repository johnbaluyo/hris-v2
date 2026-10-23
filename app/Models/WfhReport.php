<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $wfh_date
 * @property array<string, mixed> $division_snapshot
 * @property array<string, mixed>|null $signer_snapshot
 * @property array<string, mixed>|null $approver_snapshot
 */
class WfhReport extends Model
{
    /** @return HasMany<RequestStatus, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(RequestStatus::class, 'request_id')->where('request_type', 'WFH_REPORT')->orderBy('id');
    }

    protected $fillable = ['division_id', 'wfh_date', 'status', 'submitted_count', 'created_by', 'division_snapshot', 'signer_snapshot', 'approver_snapshot', 'signed_at', 'approved_at', 'archived_at'];

    protected function casts(): array
    {
        return ['wfh_date' => 'immutable_date:Y-m-d', 'submitted_count' => 'integer', 'division_snapshot' => 'array', 'signer_snapshot' => 'array', 'approver_snapshot' => 'array', 'signed_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'archived_at' => 'immutable_datetime'];
    }

    /** @return HasMany<WfhReportLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(WfhReportLine::class);
    }
}
