<?php

namespace App\Services\Credits;

use App\Models\CocAllocation;
use App\Models\CocEntry;
use App\Models\CtoRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CocLedger
{
    public function balance(int $userId, ?string $on = null): int
    {
        $date = $on ?? today('Asia/Manila')->toDateString();

        return (int) CocEntry::where('user_id', $userId)->where('direction', 'IN')
            ->where(fn ($q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', $date))->sum('remaining_minutes');
    }

    public function adjust(User $employee, int $minutes, string $direction, string $reason, User $actor, string $key): CocEntry
    {
        abort_unless($actor->hasAbility('coc.manage'), 403);
        if ($minutes < 1 || ! in_array($direction, ['IN', 'OUT'], true)) {
            throw ValidationException::withMessages(['minutes' => 'Enter a positive number of minutes and a valid direction.']);
        }

        return DB::transaction(function () use ($employee, $minutes, $direction, $reason, $actor, $key) {
            $existing = CocEntry::where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            if ($direction === 'OUT') {
                $remaining = $minutes;
                $lots = CocEntry::where('user_id', $employee->id)->where('direction', 'IN')->where('remaining_minutes', '>', 0)
                    ->where(fn ($q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', today('Asia/Manila')))
                    ->orderByRaw('expires_on is null')->orderBy('expires_on')->orderBy('scope_date')->orderBy('id')->lockForUpdate()->get();
                if ($lots->sum('remaining_minutes') < $remaining) {
                    throw ValidationException::withMessages(['minutes' => 'The adjustment exceeds available unexpired COC credits.']);
                }
                foreach ($lots as $lot) {
                    if ($remaining === 0) {
                        break;
                    }
                    $used = min($remaining, $lot->remaining_minutes);
                    $lot->decrement('remaining_minutes', $used);
                    $remaining -= $used;
                }
            }

            return CocEntry::create(['idempotency_key' => $key, 'user_id' => $employee->id, 'direction' => $direction,
                'minutes' => $minutes, 'remaining_minutes' => $direction === 'IN' ? $minutes : 0,
                'scope_date' => today('Asia/Manila'), 'source_type' => 'adjustment', 'remarks' => $reason,
                'actor_id' => $actor->id, 'created_at' => now()]);
        }, 3);
    }

    public function consume(CtoRequest $request, User $actor): void
    {
        DB::transaction(function () use ($request, $actor) {
            if (CocEntry::where('idempotency_key', 'cto:'.$request->id.':debit')->exists()) {
                return;
            }
            $remaining = $request->total_minutes;
            $lots = CocEntry::where('user_id', $request->user_id)->where('direction', 'IN')->where('remaining_minutes', '>', 0)
                ->where(fn ($q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', today('Asia/Manila')))
                ->orderByRaw('expires_on is null')->orderBy('expires_on')->orderBy('scope_date')->orderBy('id')->lockForUpdate()->get();
            if ($lots->sum('remaining_minutes') < $remaining) {
                throw ValidationException::withMessages(['balance' => 'The employee does not have enough unexpired COC credits.']);
            }
            foreach ($lots as $lot) {
                if ($remaining === 0) {
                    break;
                }
                $used = min($remaining, $lot->remaining_minutes);
                $lot->decrement('remaining_minutes', $used);
                CocAllocation::create(['cto_request_id' => $request->id, 'coc_entry_id' => $lot->id, 'minutes' => $used]);
                $remaining -= $used;
            }
            CocEntry::create(['user_id' => $request->user_id, 'direction' => 'OUT', 'minutes' => $request->total_minutes,
                'remaining_minutes' => 0, 'scope_date' => today('Asia/Manila'), 'source_type' => 'cto', 'source_id' => $request->id,
                'idempotency_key' => 'cto:'.$request->id.':debit', 'remarks' => 'Approved CTO #'.$request->id,
                'actor_id' => $actor->id, 'created_at' => now()]);
        }, 3);
    }

    public function reverse(CtoRequest $request, User $actor): void
    {
        DB::transaction(function () use ($request, $actor) {
            $allocations = CocAllocation::where('cto_request_id', $request->id)->whereNull('reversed_at')->lockForUpdate()->get();
            foreach ($allocations as $allocation) {
                CocEntry::whereKey($allocation->coc_entry_id)->increment('remaining_minutes', $allocation->minutes);
                $allocation->update(['reversed_at' => now()]);
            }
            if ($allocations->isNotEmpty()) {
                CocEntry::firstOrCreate(['idempotency_key' => 'cto:'.$request->id.':reversal'], ['user_id' => $request->user_id,
                    'direction' => 'IN', 'minutes' => $request->total_minutes, 'remaining_minutes' => 0,
                    'scope_date' => today('Asia/Manila'), 'source_type' => 'cto-reversal', 'source_id' => $request->id,
                    'remarks' => 'Cancelled CTO #'.$request->id, 'actor_id' => $actor->id, 'created_at' => now()]);
            }
        }, 3);
    }
}
