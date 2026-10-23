<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentDocument extends Model
{
    protected $fillable = ['user_id', 'recruitment_application_id', 'private_file_id', 'document_type', 'side'];

    protected function casts(): array
    {
        return [];
    }
}
