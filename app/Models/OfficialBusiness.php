<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $type
 * @property string|null $current_status
 */
class OfficialBusiness extends Model
{
    protected $fillable = ['user_id', 'filed_on', 'business_date', 'type', 'departure_time', 'return_time', 'origin', 'destination', 'go_directly', 'return_to_office', 'purpose', 'amendment_reason', 'attachment_file_id', 'created_by'];

    protected function casts(): array
    {
        return ['filed_on' => 'date:Y-m-d', 'business_date' => 'date:Y-m-d', 'type' => 'integer', 'go_directly' => 'boolean', 'return_to_office' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<RequestStatus, $this> */
    public function statuses(): HasMany
    {
        return $this->hasMany(RequestStatus::class, 'request_id')->where('request_type', 'OB');
    }
}
