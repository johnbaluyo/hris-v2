<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryGrade extends Model
{
    public $timestamps = false;

    protected $fillable = ['grade', 'step', 'monthly_salary', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return ['grade' => 'integer', 'step' => 'integer', 'monthly_salary' => 'decimal:2', 'effective_from' => 'date:Y-m-d', 'effective_to' => 'date:Y-m-d'];
    }
}
