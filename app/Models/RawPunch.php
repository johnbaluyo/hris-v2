<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CarbonImmutable $punched_at
 */
class RawPunch extends Model
{
    protected $fillable = ['user_id', 'service_client_id', 'source_key', 'anviz_log_id', 'source_epoch', 'atms_id', 'punched_at', 'device', 'record_type', 'wfh_location'];

    protected static function booted(): void
    {
        static::creating(function (RawPunch $punch) {
            // Pin the employee at capture time; later attendance-ID changes must not move evidence between people.
            if ($punch->user_id === null) {
                $punch->user_id = User::where('atms_id', $punch->atms_id)->value('id');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['punched_at' => 'immutable_datetime', 'wfh_location' => 'array'];
    }
}
