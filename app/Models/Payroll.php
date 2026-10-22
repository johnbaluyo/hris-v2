<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property string $payroll_number @property string $status @property string $calculation_version @property \Illuminate\Support\Carbon $period_start @property \Illuminate\Support\Carbon $period_end */
class Payroll extends Model
{
    protected $fillable = ['type', 'payroll_number', 'fund_cluster', 'period_start', 'period_end', 'status', 'calculation_version', 'created_by', 'finalized_by', 'finalized_at'];

    protected function casts(): array
    {
        return ['period_start' => 'date:Y-m-d', 'period_end' => 'date:Y-m-d', 'finalized_at' => 'immutable_datetime'];
    }

    /** @return HasMany<PayrollLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }
}
