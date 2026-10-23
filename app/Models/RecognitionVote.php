<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecognitionVote extends Model
{
    public $timestamps = false;

    protected $fillable = ['recognition_event_id', 'recognition_nominee_id', 'user_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
