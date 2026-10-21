<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivateFile extends Model
{
    protected $fillable = ['user_id', 'actor_id', 'purpose', 'original_name', 'path', 'mime', 'size', 'checksum'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [];
    }
}
