<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelDocumentVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['personnel_document_id', 'private_file_id', 'uploaded_by', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PersonnelDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PersonnelDocument::class, 'personnel_document_id');
    }

    /** @return BelongsTo<PrivateFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(PrivateFile::class, 'private_file_id');
    }
}
