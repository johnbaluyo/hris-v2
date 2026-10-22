<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $id @property int $overtime_request_id @property \Illuminate\Support\Carbon $overtime_date */
class OvertimeDate extends Model
{
    public $timestamps = false;

    protected $fillable = ['overtime_request_id', 'overtime_date', 'rendered_minutes'];

    protected function casts(): array
    {
        return ['overtime_date' => 'date:Y-m-d', 'rendered_minutes' => 'integer'];
    }

    /** @return BelongsTo<OvertimeRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequest::class, 'overtime_request_id');
    }
}
