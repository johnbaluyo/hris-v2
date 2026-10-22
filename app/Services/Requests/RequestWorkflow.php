<?php

namespace App\Services\Requests;

use App\Models\RequestStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestWorkflow
{
    /** @var array<string, array<string, array<string, string>>> */
    private const TRANSITIONS = [
        'OB' => [
            'For DC Approval' => ['approve_dc' => 'Approved', 'reject_dc' => 'Disapproved by DC'],
            'For ED Approval' => ['approve_ed' => 'Approved', 'reject_ed' => 'Disapproved by ED'],
        ],
        'OT' => [
            'For DC Approval' => ['approve_dc' => 'For ED Approval', 'reject_dc' => 'Disapproved by DC'],
            'For ED Approval' => ['approve_ed' => 'Approved', 'reject_ed' => 'Disapproved by ED'],
        ],
        'CTO' => [
            'For HR Cert' => ['certify' => 'route'],
            'For DC Approval' => ['approve_dc' => 'For ED Approval', 'reject_dc' => 'Disapproved by DC'],
            'For ED Approval' => ['approve_ed' => 'Approved', 'reject_ed' => 'Disapproved by ED'],
        ],
        'LEAVE' => [
            'For HR Cert' => ['certify' => 'route', 'return' => 'Returned by HR'],
            'For DC Approval' => ['return' => 'Returned by Chief', 'approve_dc' => 'For ED Approval', 'reject_dc' => 'Disapproved'],
            'For ED Approval' => ['approve_ed' => 'Approved', 'reject_ed' => 'Disapproved'],
            'For CAO Witnesseth' => ['witness' => 'Approved', 'reject_witness' => 'Disapproved'],
        ],
    ];

    /** @return array<string, string> */
    public function availableActions(string $type, Model $request, User $actor): array
    {
        $current = $this->current($type, (int) $request->getKey());
        if (! $current) {
            return [];
        }
        $labels = [
            'certify' => 'HR Certify',
            'return' => 'Return to employee',
            'approve_dc' => 'Recommend as chief',
            'reject_dc' => 'Disapprove as chief',
            'approve_ed' => $actor->role === 'executive_director' ? 'Approve' : 'Approve as ED/OIC',
            'reject_ed' => $actor->role === 'executive_director' ? 'Disapprove' : 'Disapprove as ED/OIC',
            'witness' => 'CAO witness and approve',
            'reject_witness' => 'CAO disapprove',
        ];
        $actions = [];
        foreach (array_keys(self::TRANSITIONS[$type][$current->status] ?? []) as $action) {
            if ($this->canReview($type, $request, $actor, $action, $current->status)) {
                $actions[$action] = $labels[$action];
            }
        }
        if ($actor->id === (int) $request->getAttribute('user_id') && ! $this->isClosed($current->status)) {
            $actions['cancel'] = 'Cancel my application';
        }

        return $actions;
    }

    public function current(string $type, int $id, bool $lock = false): ?RequestStatus
    {
        $query = RequestStatus::where('request_type', $type)->where('request_id', $id)->latest('id');

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    public function start(string $type, Model $request, User $actor, ?string $status = null): RequestStatus
    {
        $initial = $status ?? match ($type) {
            'CTO', 'LEAVE' => 'For HR Cert',
            default => $actor->role === 'division_chief' ? 'For ED Approval' : 'For DC Approval',
        };

        return $this->append($type, (int) $request->getKey(), $initial, $actor, null, 'filed');
    }

    public function transition(string $type, Model $request, User $actor, string $action, ?string $reason = null): RequestStatus
    {
        return DB::transaction(function () use ($type, $request, $actor, $action, $reason) {
            $current = $this->current($type, (int) $request->getKey(), true);
            if (! $current) {
                throw ValidationException::withMessages(['status' => 'This request has no review status.']);
            }
            if ($action === 'cancel') {
                if ($actor->id !== (int) $request->getAttribute('user_id')) {
                    abort(403);
                }
                if ($this->isClosed($current->status)) {
                    throw ValidationException::withMessages(['status' => 'This request is already closed.']);
                }

                return $this->append($type, (int) $request->getKey(), 'Cancelled', $actor, $reason, $action);
            }
            $next = self::TRANSITIONS[$type][$current->status][$action] ?? null;
            if ($next === null) {
                throw ValidationException::withMessages(['status' => "{$action} is not allowed while the request is {$current->status}."]);
            }
            $this->authorizeReview($type, $request, $actor, $action, $current->status);
            if ($next === 'route') {
                $employee = User::whereKey($request->getAttribute('user_id'))->first();
                abort_unless($employee instanceof User, 404);
                $next = $employee->role === 'executive_director' && $type === 'LEAVE'
                    ? 'For CAO Witnesseth'
                    : ($employee->role === 'division_chief' ? 'For ED Approval' : 'For DC Approval');
            }
            if (str_starts_with($action, 'reject') && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'A reason is required for a disapproval.']);
            }

            return $this->append($type, (int) $request->getKey(), $next, $actor, $reason, $action);
        });
    }

    private function authorizeReview(string $type, Model $request, User $actor, string $action, string $currentStatus): void
    {
        abort_unless($this->canReview($type, $request, $actor, $action, $currentStatus), 403);
    }

    private function canReview(string $type, Model $request, User $actor, string $action, string $currentStatus): bool
    {
        $employee = User::whereKey($request->getAttribute('user_id'))->first();
        if (! $employee instanceof User || $actor->id === $employee->id) {
            return false;
        }

        return match (true) {
            $action === 'certify' => $actor->role === 'hr' && $actor->hasAbility('requests.certify'),
            $action === 'return' && $currentStatus === 'For HR Cert' => $actor->role === 'hr' && $actor->hasAbility('requests.certify'),
            $action === 'return' && $currentStatus === 'For DC Approval' => $actor->actsAsDivisionChiefOf($employee->division_id),
            str_ends_with($action, '_dc') => $actor->actsAsDivisionChiefOf($employee->division_id),
            str_ends_with($action, '_ed') => $actor->actsAsDirector(),
            in_array($action, ['witness', 'reject_witness'], true) => $type === 'LEAVE' && $employee->role === 'executive_director' && $actor->hasAbility('leave.witness'),
            default => false,
        };
    }

    private function isClosed(string $status): bool
    {
        return in_array($status, ['Cancelled', 'Disapproved', 'Disapproved by DC', 'Disapproved by ED'], true);
    }

    private function append(string $type, int $id, string $status, User $actor, ?string $reason = null, ?string $action = null): RequestStatus
    {
        return RequestStatus::create(['request_type' => $type, 'request_id' => $id, 'status' => $status,
            'actor_id' => $actor->id, 'action' => $action, 'reason' => $reason, 'created_at' => now()]);
    }
}
