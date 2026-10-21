<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Division;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EmploymentHistory
{
    public const EVENTS = [
        'hired' => 'Hired',
        'position_changed' => 'Position change',
        'terminated' => 'Terminated',
        'rehired' => 'Rehired',
        'hire_date_corrected' => 'Hire date corrected',
        'termination_date_corrected' => 'Termination date corrected',
        'termination_cancelled' => 'Termination cancelled',
    ];

    /** @return Builder<AuditEvent> */
    public function query(User $employee): Builder
    {
        return AuditEvent::where('subject_type', User::class)->where('subject_id', $employee->id)
            ->whereIn('action', array_map(fn ($event) => 'employment.'.$event, array_keys(self::EVENTS)));
    }

    /** Populate only known dates/assignments; never guess a missing historical hire date. */
    public function backfill(User $employee, ?int $actorId = null): void
    {
        DB::transaction(function () use ($employee, $actorId) {
            $employee = User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            if ($this->query($employee)->exists()) {
                return;
            }
            $assignments = $employee->positionAssignments()->orderBy('assume_date')->orderBy('id')->get();
            $first = $assignments->first();
            if ($employee->date_hired !== null) {
                $this->record($employee, 'hired', $employee->date_hired->toDateString(), $actorId,
                    $first ? $first->position_id : $employee->position_id, null,
                    'Opening record from the existing hire date and available position history.');
            }
            foreach ($assignments as $assignment) {
                if ($assignment->old_position_id !== $assignment->position_id
                    && ($assignment->old_position_id !== null || $employee->date_hired === null)) {
                    $this->record($employee, 'position_changed', $assignment->assume_date->toDateString(), $assignment->actor_id,
                        $assignment->position_id, $assignment->old_position_id, 'Existing position assignment history.');
                }
            }
            if ($employee->termination_date !== null) {
                $this->record($employee, 'terminated', $employee->termination_date->toDateString(), $actorId, $employee->position_id);
            }
        });
    }

    /** @param array<string, mixed> $before */
    public function changes(User $employee, array $before, string $assumeDate, bool $new, bool $rehired, int $actorId): void
    {
        if ($employee->employee_type === 'Applicant') {
            return;
        }
        $oldPosition = $before['position_id'] ?? null;
        $oldHire = $before['date_hired'] ?? null;
        $oldEnd = $before['termination_date'] ?? null;
        $hire = $employee->date_hired?->toDateString();
        $end = $employee->termination_date?->toDateString();
        if ($new || ($before['employee_type'] ?? null) === 'Applicant') {
            $this->record($employee, 'hired', $hire ?? $assumeDate, $actorId, $employee->position_id);
        } elseif ($rehired && $hire !== null) {
            $this->record($employee, 'rehired', $hire, $actorId, $employee->position_id, $oldPosition,
                'Previous employment ended on '.$oldEnd.'.');
        } else {
            if ($oldHire !== $hire && $hire !== null) {
                $this->record($employee, 'hire_date_corrected', $hire, $actorId, $employee->position_id, null,
                    'Previous hire date: '.($oldHire ?? 'not recorded').'.');
            }
            if ($oldPosition !== $employee->position_id) {
                $this->record($employee, 'position_changed', $assumeDate, $actorId, $employee->position_id, $oldPosition);
            }
        }
        if ($end !== null && ($new || $rehired || $oldEnd !== $end)) {
            $type = $oldEnd !== null && ! $rehired && ! $new ? 'termination_date_corrected' : 'terminated';
            $this->record($employee, $type, $end, $actorId, $employee->position_id, null,
                $type === 'termination_date_corrected' ? 'Previous termination date: '.$oldEnd.'.' : null);
        } elseif ($oldEnd !== null && $end === null && ! $rehired) {
            $this->record($employee, 'termination_cancelled', today('Asia/Manila')->toDateString(), $actorId,
                $employee->position_id, null, 'Cancelled termination dated '.$oldEnd.'.');
        }
    }

    private function record(User $employee, string $type, string $date, ?int $actorId, ?int $positionId, ?int $previousPositionId = null, ?string $notes = null): void
    {
        $position = $positionId === null ? null : Position::findOrFail($positionId);
        $previous = $previousPositionId === null ? null : Position::findOrFail($previousPositionId);
        // Employment events share the append-only audit store, with a deliberately limited snapshot.
        AuditEvent::create([
            'actor_id' => $actorId, 'action' => 'employment.'.$type, 'subject_type' => User::class,
            'subject_id' => $employee->id, 'before' => null,
            'after' => [
                'event' => self::EVENTS[$type], 'effective_date' => $date,
                'position_id' => $positionId, 'position_title' => $position?->title,
                'previous_position_id' => $previousPositionId, 'previous_position_title' => $previous?->title,
                'division' => $position ? Division::whereKey($position->division_id)->value('name') : null,
                'classification' => $position?->classification, 'employee_type' => $employee->employee_type,
                'date_hired' => $employee->date_hired?->toDateString(),
                'termination_date' => $employee->termination_date?->toDateString(), 'notes' => $notes,
            ],
            'created_at' => now(),
        ]);
    }
}
