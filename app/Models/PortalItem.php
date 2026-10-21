<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalItem extends Model
{
    protected $fillable = ['kind', 'title', 'description', 'url', 'status', 'starts_on', 'ends_on', 'author_id', 'file_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d'];
    }
}
