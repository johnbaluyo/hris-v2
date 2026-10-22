<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $payroll_id
 * @property array<string, mixed> $employee_snapshot
 * @property string $basic_salary
 * @property string $pera
 * @property string $rata
 * @property string $other_compensation
 * @property string $total_deductions
 * @property string $net
 */
class PayrollLine extends Model
{
    protected $fillable = ['payroll_id', 'user_id', 'employee_snapshot', 'basic_salary', 'pera', 'rata', 'other_compensation', 'statutory_deductions', 'other_deductions', 'late_minutes', 'undertime_minutes', 'absence_days', 'gross', 'total_deductions', 'net', 'calculation_inputs'];

    protected function casts(): array
    {
        return ['employee_snapshot' => 'array', 'calculation_inputs' => 'array', 'basic_salary' => 'decimal:2', 'pera' => 'decimal:2', 'rata' => 'decimal:2', 'other_compensation' => 'decimal:2', 'statutory_deductions' => 'decimal:2', 'other_deductions' => 'decimal:2', 'absence_days' => 'decimal:3', 'gross' => 'decimal:2', 'total_deductions' => 'decimal:2', 'net' => 'decimal:2'];
    }
}
