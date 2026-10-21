<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 */
class AuditEvent extends Model
{
    protected $fillable = ['actor_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'created_at'];

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }
}
