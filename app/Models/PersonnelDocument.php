<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonnelDocument extends Model
{
    protected $fillable = ['user_id', 'document_type', 'document_year', 'period', 'remarks'];

    protected function casts(): array
    {
        return ['document_year' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<PersonnelDocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(PersonnelDocumentVersion::class);
    }

    /** @return HasMany<RequestStatus, $this> */
    public function statuses(): HasMany
    {
        return $this->hasMany(RequestStatus::class, 'request_id')->where('request_type', 'UPLOADS');
    }
}
