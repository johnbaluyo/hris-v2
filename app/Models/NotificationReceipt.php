<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationReceipt extends Model
{
    public $timestamps = false;

    protected $fillable = ['system_notification_id', 'user_id', 'read_at', 'dismissed_at'];

    protected function casts(): array
    {
        return ['read_at' => 'immutable_datetime', 'dismissed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<SystemNotification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(SystemNotification::class, 'system_notification_id');
    }
}
