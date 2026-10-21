<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    /** @param array<string, mixed>|null $before */
    public static function record(string $action, Model $subject, ?int $actorId, ?array $before = null, ?string $reason = null): void
    {
        $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'token_hash'];
        AuditEvent::create([
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'actor_id' => $actorId,
            'before' => $before === null ? null : array_diff_key($before, array_flip($hidden)),
            'after' => array_diff_key($subject->attributesToArray(), array_flip($hidden)) + ($reason === null ? [] : ['audit_reason' => $reason]),
            'created_at' => now(),
        ]);
    }
}
