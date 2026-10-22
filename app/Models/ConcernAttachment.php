<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcernAttachment extends Model
{
    protected $fillable = ['concern_id', 'private_file_id', 'side', 'uploaded_by'];

    /** @return BelongsTo<Concern, $this> */
    public function concern(): BelongsTo
    {
        return $this->belongsTo(Concern::class);
    }

    /** @return BelongsTo<PrivateFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(PrivateFile::class, 'private_file_id');
    }
}
