<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemNotification extends Model
{
    protected $fillable = ['source_key', 'event_id', 'payload_hash', 'system', 'source', 'title', 'message', 'url', 'notification_type', 'recipient_email', 'priority', 'badge_text', 'badge_class', 'expires_at', 'is_active'];

    protected function casts(): array
    {
        return ['priority' => 'integer', 'expires_at' => 'immutable_datetime', 'is_active' => 'boolean'];
    }

    /** @return HasMany<NotificationReceipt, $this> */
    public function receipts(): HasMany
    {
        return $this->hasMany(NotificationReceipt::class);
    }
}
