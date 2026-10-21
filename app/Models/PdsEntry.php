<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<string, mixed> $data
 */
class PdsEntry extends Model
{
    protected $fillable = ['user_id', 'section', 'data', 'file_id', 'actor_id'];

    use SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
