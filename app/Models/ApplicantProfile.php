<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $data
 */
class ApplicantProfile extends Model
{
    protected $fillable = ['user_id', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
