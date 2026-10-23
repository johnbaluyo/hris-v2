<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRevision;
use App\Models\DailyAttendance;
use App\Models\Holiday;
use App\Models\RawPunch;
use App\Models\RawPunchRevision;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AttendanceService
{
    public function __construct(private AttendanceCalculator $calculator, private RequestExemptions $exemptions) {}

    public function rebuild(User $employee, string $date, ?int $actorId = null, string $reason = 'Raw punch recalculation', bool $replaceOverride = false): DailyAttendance
    {
        return Cache::lock('attendance:'.$employee->id.':'.$date, 60)->block(5, function () use ($employee, $date, $actorId, $reason, $replaceOverride) {
            return $this->rebuildRecord($employee, $date, $actorId, $reason, $replaceOverride);
        });
    }

    private function rebuildRecord(User $employee, string $date, ?int $actorId, string $reason, bool $replaceOverride = false): DailyAttendance
    {
        return DB::transaction(function () use ($employee, $date, $actorId, $reason, $replaceOverride) {
            User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $record = DailyAttendance::firstOrNew(['user_id' => $employee->id, 'attendance_date' => $date]);
            if ($record->exists && $record->is_override && ! $replaceOverride) {
                return $record;
            }
            $day = CarbonImmutable::parse($date, 'Asia/Manila');
            $logs = RawPunch::where('user_id', $employee->id)
                ->where('punched_at', '>=', $day->startOfDay())->where('punched_at', '<', $day->addDay()->startOfDay())->orderBy('punched_at')->orderBy('id')->get();
            $times = array_fill_keys(['time_in', 'lunch_out', 'lunch_in', 'time_out', 'ot_in', 'ot_out'], null);
            $office = $logs->filter(fn ($log) => $log->device !== 'WEB WFH');
            $first = $office->first() ?? $logs->first();
            $last = $logs->last();
            $wfh = $logs->isNotEmpty() && $office->isEmpty();
            if ($first) {
                $start = $first->punched_at->format('H:i:s');
                $times[$start >= '12:01:00' ? 'lunch_in' : 'time_in'] = $start;
            }
            if ($logs->count() > 1 && $last) {
                $end = $last->punched_at->format('H:i:s');
                if ($end < '12:00:00') {
                    $times['lunch_out'] = $end;
                } else {
                    $times['time_out'] = $end;
                    $lunch = $office->filter(fn ($log) => $log->punched_at->format('H:i:s') >= '12:00:00' && $log->punched_at->format('H:i:s') <= '13:00:59');
                    if ($times['time_in']) {
                        $times['lunch_out'] = $lunch->first()?->punched_at->format('H:i:s') ?? '12:00:00';
                        $times['lunch_in'] = $lunch->last()?->punched_at->format('H:i:s') ?? '13:00:00';
                    }
                }
            }
            $schedule = WorkSchedule::where('user_id', $employee->id)->whereDate('starts_on', '<=', $date)
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date))->latest('starts_on')->first();
            $workweek = $logs->count() > 1 ? $schedule?->workweek : null;
            $calendarEntries = Holiday::effectiveFor($employee)->whereDate('holiday_date', $date)->get();
            $suspension = $calendarEntries->where('type', 'Work Suspension')->pluck('suspension_time')->filter()->sort()->first();
            $result = $this->calculator->calculate($times, $workweek, $wfh, is_string($suspension) ? $suspension : null);
            $fullDayExcused = $calendarEntries->whereIn('type', ['Regular', 'Special Non-Working', 'Special Order'])->isNotEmpty();
            if ($fullDayExcused) {
                $result['late_minutes'] = 0;
                $result['undertime_minutes'] = 0;
                if ($logs->isEmpty()) {
                    $result['needs_review'] = false;
                }
            }
            $exemptions = $this->exemptions->forDate($employee->id, $date);
            if ($exemptions['late']) {
                $result['late_minutes'] = 0;
            }
            if ($exemptions['undertime']) {
                $result['undertime_minutes'] = 0;
            }
            if ($exemptions['late'] && $exemptions['undertime'] && $logs->isEmpty()) {
                $result['needs_review'] = false;
            }
            $outsideEmployment = ($employee->date_hired && $day->isBefore($employee->date_hired)) || ($employee->termination_date && $day->isAfter($employee->termination_date));
            if ($outsideEmployment) {
                $result['needs_review'] = true;
                $result['late_minutes'] = $result['undertime_minutes'] = null;
            }
            $values = $times + $result + ['workweek' => $workweek, 'policy_version' => AttendanceCalculator::VERSION,
                'is_override' => false, 'notes' => $wfh ? 'WEB WFH' : null,
                'provenance' => ['raw_ids' => $logs->pluck('id')->all(), 'schedule_id' => $schedule?->id, 'outside_employment' => (bool) $outsideEmployment,
                    'request_exemptions' => $exemptions['sources'], 'calendar_reference_ids' => $calendarEntries->pluck('id')->all()]];
            $this->persist($record, $values, $actorId, $reason);

            return $record;
        }, 3);
    }

    /** @param array<string, mixed> $values */
    public function correctRawPunch(RawPunch $punch, array $values, string $version, User $actor, string $reason): RawPunch
    {
        Gate::forUser($actor)->authorize('update', $punch);
        $employee = $punch->user_id === null ? null : User::whereKey($punch->user_id)->firstOrFail();
        $dates = array_values(array_unique([$punch->punched_at->toDateString(), CarbonImmutable::parse($values['punched_at'], 'Asia/Manila')->toDateString()]));
        sort($dates);

        return $this->withCorrectionLocks($employee, $dates, function () use ($punch, $values, $version, $actor, $reason, $employee, $dates) {
            return DB::transaction(function () use ($punch, $values, $version, $actor, $reason, $employee, $dates) {
                $current = RawPunch::whereKey($punch->id)->lockForUpdate()->firstOrFail();
                abort_unless(hash_equals($current->editVersion(), $version), 409, 'This punch changed since you opened it. Reload the list and edit it again.');
                $before = $current->correctionSnapshot();
                $current->fill(array_intersect_key($values, array_flip(['punched_at', 'device', 'record_type'])));
                if (! $current->isDirty(['punched_at', 'device', 'record_type'])) {
                    return $current;
                }
                $current->source_evidence ??= $before;
                $current->lock_version++;
                $current->save();
                RawPunchRevision::create(['raw_punch_id' => $current->id, 'actor_id' => $actor->id, 'reason' => $reason,
                    'before' => $before, 'after' => $current->correctionSnapshot(), 'created_at' => now()]);
                Audit::record('attendance.raw-punch.corrected', $current, $actor->id, $before, $reason);
                if ($employee !== null) {
                    foreach ($dates as $date) {
                        $this->rebuildRecord($employee, $date, $actor->id, 'Raw punch correction #'.$current->id.': '.$reason);
                    }
                }

                return $current;
            }, 3);
        });
    }

    /**
     * @param  array<int, string>  $dates
     * @param  \Closure(): RawPunch  $callback
     */
    private function withCorrectionLocks(?User $employee, array $dates, \Closure $callback): RawPunch
    {
        if ($employee === null || $dates === []) {
            return $callback();
        }
        $date = array_shift($dates);

        return Cache::lock('attendance:'.$employee->id.':'.$date, 60)->block(5,
            fn () => $this->withCorrectionLocks($employee, $dates, $callback));
    }

    /** @param array<string, mixed> $times */
    public function override(User $employee, string $date, array $times, int $actorId, string $reason): DailyAttendance
    {
        return DB::transaction(function () use ($employee, $date, $times, $actorId, $reason) {
            User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $record = DailyAttendance::firstOrNew(['user_id' => $employee->id, 'attendance_date' => $date]);
            $workweek = isset($times['workweek']) ? (int) $times['workweek'] : null;
            $wfh = strcasecmp(trim($times['notes'] ?? ''), 'WEB WFH') === 0;
            $values = $times + $this->calculator->calculate($times, $workweek, $wfh) + [
                'is_override' => true, 'policy_version' => AttendanceCalculator::VERSION,
                'provenance' => ['manual' => true, 'actor_id' => $actorId],
            ];
            $this->persist($record, $values, $actorId, $reason);

            return $record;
        }, 3);
    }

    /** @param array<string, mixed> $values */
    private function persist(DailyAttendance $record, array $values, ?int $actorId, string $reason): void
    {
        $before = $record->exists ? $record->attributesToArray() : null;
        $record->fill($values);
        if ($record->exists && ! $record->isDirty()) {
            return;
        }
        $record->save();
        AttendanceRevision::create(['daily_attendance_id' => $record->id, 'actor_id' => $actorId, 'reason' => $reason,
            'before' => $before, 'after' => $record->attributesToArray(), 'created_at' => now()]);
    }
}
