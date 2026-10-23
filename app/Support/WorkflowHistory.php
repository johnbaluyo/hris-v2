<?php

namespace App\Support;

use App\Models\RequestStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WorkflowHistory
{
    public static function record(string $type, Model $record, User $actor, string $action, string $status, ?string $reason = null): void
    {
        RequestStatus::create(['request_type' => $type, 'request_id' => $record->getKey(), 'actor_id' => $actor->id,
            'action' => $action, 'status' => $status, 'reason' => $reason, 'created_at' => now()]);
        Audit::record(strtolower($type).'.'.$action, $record, $actor->id);
    }
}
