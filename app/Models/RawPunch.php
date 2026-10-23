<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $punched_at
 * @property array<string, mixed>|null $source_evidence
 * @property int $lock_version
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
        return ['punched_at' => 'immutable_datetime', 'wfh_location' => 'array', 'source_evidence' => 'array', 'lock_version' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<RawPunchRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(RawPunchRevision::class)->orderBy('id');
    }

    /** @return array<string, mixed> */
    public function correctionSnapshot(): array
    {
        return ['user_id' => $this->user_id, 'atms_id' => $this->atms_id, 'punched_at' => $this->punched_at->setTimezone('Asia/Manila')->format('Y-m-d H:i:s'),
            'device' => $this->device, 'record_type' => $this->record_type, 'lock_version' => $this->lock_version ?? 0];
    }

    public function editVersion(): string
    {
        return hash('sha256', json_encode($this->correctionSnapshot(), JSON_THROW_ON_ERROR));
    }
}
