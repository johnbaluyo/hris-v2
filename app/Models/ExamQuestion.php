<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int, array{id: string, text: string, image_file_id: int|null}>|null $choices
 */
class ExamQuestion extends Model
{
    protected $fillable = ['exam_category_id', 'question_type', 'question', 'choices', 'image_file_id', 'archived_at'];

    protected function casts(): array
    {
        return ['question_type' => 'integer', 'choices' => 'array', 'archived_at' => 'immutable_datetime'];
    }
}
