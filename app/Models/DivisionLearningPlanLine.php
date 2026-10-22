<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DivisionLearningPlanLine extends Model
{
    protected $fillable = ['division_learning_plan_id', 'idp_item_id', 'user_id', 'budget', 'is_free', 'snapshot'];

    protected function casts(): array
    {
        return ['budget' => 'decimal:2', 'is_free' => 'boolean', 'snapshot' => 'array'];
    }
}
