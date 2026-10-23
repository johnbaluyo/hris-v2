<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateFile extends Model
{
    protected $fillable = ['employment_certificate_id', 'private_file_id'];

    protected function casts(): array
    {
        return [];
    }
}
