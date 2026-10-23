<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $snapshot
 */
class WfhReportLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['wfh_report_id', 'user_id', 'wfh_accomplishment_id', 'snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
