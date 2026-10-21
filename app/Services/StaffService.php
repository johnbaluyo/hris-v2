<?php

namespace App\Services;

use App\Jobs\SendAccountInvitation;
use App\Models\PdsEntry;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffService
{
    /** @param array<string, mixed> $data */
    public function save(User $actor, ?User $employee, array $data): User
    {
        return DB::transaction(function () use ($actor, $employee, $data) {
            $new = $employee === null;
            $employee = $new ? new User : User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $before = $employee->attributesToArray();
            $history = app(EmploymentHistory::class);
            if (! $new) {
                $history->backfill($employee, $actor->id);
            }
            $oldPosition = $employee->position_id;
            $role = $data['role'] ?? ($new ? 'staff' : $employee->role);
            if ($role !== ($new ? 'staff' : $employee->role) && ! $actor->hasAbility('accounts.manage')) {
                throw ValidationException::withMessages(['role' => 'Account administration permission is required to change roles.']);
            }
            $positionId = $data['position_id'] ?? null;
            // Every position is exclusive, independently of its plantilla classification.
            $positions = Position::whereIn('id', array_filter([$oldPosition, $positionId]))->orderBy('id')->lockForUpdate()->get();
            $position = $positions->firstWhere('id', $positionId);
            if ($position && ($position->status === 'archived' || $position->division_id !== (int) ($data['division_id'] ?? 0))) {
                throw ValidationException::withMessages(['position_id' => 'Choose a current position in the selected division.']);
            }
            if ($position && User::where('position_id', $position->id)
                ->when(! $new, fn ($q) => $q->where('id', '!=', $employee->id))->exists()) {
                throw ValidationException::withMessages(['position_id' => 'This position is already assigned. Add a separate position for the new employee.']);
            }
            $oldEnd = $employee->termination_date?->toDateString();
            $rehired = ! $new && $oldEnd !== null && ($data['date_hired'] ?? '') > $oldEnd;
            if ($oldEnd !== null && $oldEnd <= today('Asia/Manila')->toDateString()
                && (($data['termination_date'] ?? null) === null || $data['termination_date'] > today('Asia/Manila')->toDateString())
                && ! $rehired) {
                throw ValidationException::withMessages(['date_hired' => 'For rehiring, enter a new hire date after the previous termination date and clear or update the termination date.']);
            }
            $assumeDate = $data['assume_date'];
            if ($rehired) {
                $assumeDate = $data['date_hired'];
            }
            unset($data['assume_date']);
            $emailChanged = ! $new && $employee->email !== $data['email'];
            $employee->forceFill($data + ['role' => $role]);
            $employee->name = trim(implode(' ', array_filter([$employee->first_name, $employee->middle_name, $employee->last_name, $employee->extension])));
            if ($new) {
                $employee->password = Str::random(64);
            }
            if ($emailChanged) {
                $employee->email_verified_at = null;
            }
            if ($rehired) {
                $employee->is_archived = false;
            }
            $employee->save();
            if ($position) {
                $position->update(['status' => 'Filled Up']);
            }
            if ($oldPosition !== null && $oldPosition !== $employee->position_id) {
                $positions->firstWhere('id', $oldPosition)?->update(['status' => 'Open for Application']);
            }
            $personal = $employee->pdsEntries()->where('section', 'personal')->latest('id')->first();
            $personalFields = array_flip(['first_name', 'middle_name', 'last_name', 'extension', 'birthday', 'sex', 'mobile_no', 'contact_person', 'contact_person_no']);
            $emptyPersonal = array_fill_keys(array_keys($personalFields), null);
            $previousPersonal = $personal ? array_replace($emptyPersonal, $personal->data) : $emptyPersonal;
            $personalData = array_replace($previousPersonal, $emptyPersonal, array_intersect_key($employee->attributesToArray(), $personalFields));
            if (! $personal || $previousPersonal != $personalData) {
                $snapshot = PdsEntry::create(['user_id' => $employee->id, 'section' => 'personal', 'data' => $personalData, 'actor_id' => $actor->id]);
                Audit::record('pds.personal.staff-synchronized', $snapshot, $actor->id, $personal?->attributesToArray());
            }
            if ($new || $rehired || $oldPosition !== $employee->position_id) {
                PositionAssignment::create(['user_id' => $employee->id, 'old_position_id' => $oldPosition,
                    'position_id' => $employee->position_id, 'actor_id' => $actor->id, 'assume_date' => $assumeDate]);
            }
            if ($new || $rehired || ($before['workweek'] ?? null) !== $employee->workweek) {
                $current = WorkSchedule::where('user_id', $employee->id)->whereNull('ends_on')->lockForUpdate()->first();
                if ($current && $assumeDate <= $current->starts_on->toDateString()) {
                    throw ValidationException::withMessages(['assume_date' => 'A changed schedule must start after the current schedule start date.']);
                }
                if ($current) {
                    $current->update(['ends_on' => CarbonImmutable::parse($assumeDate)->subDay()->toDateString()]);
                }
                if ($employee->workweek !== null) {
                    WorkSchedule::create(['user_id' => $employee->id, 'starts_on' => $assumeDate,
                        'workweek' => $employee->workweek, 'policy_version' => 'office-v1', 'actor_id' => $actor->id]);
                }
            }
            $history->changes($employee, $before, $assumeDate, $new, $rehired, $actor->id);
            Audit::record($new ? 'staff.created' : 'staff.updated', $employee, $actor->id, $before);
            if ($new || $emailChanged) {
                SendAccountInvitation::dispatch($employee->id)->afterCommit();
            }

            return $employee;
        }, 3);
    }
}
