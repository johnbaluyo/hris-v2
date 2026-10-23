<?php

namespace App\Services\Wfh;

use App\Models\LeaveDate;
use App\Models\RawPunch;
use App\Models\User;
use App\Models\WfhAccomplishment;
use App\Models\WfhReport;
use App\Support\EmployeeSnapshot;

class Consolidation
{
    public function capture(WfhReport $report): void
    {
        $date = $report->wfh_date->toDateString();
        $approved = WfhAccomplishment::where('division_id', $report->division_id)->whereDate('wfh_date', $date)
            ->where('status', 'approved')->whereNull('archived_at')->get()->keyBy('user_id');
        abort_if($approved->isEmpty(), 422, 'At least one approved accomplishment is required.');
        $users = User::with('position', 'division')->where('division_id', $report->division_id)->whereIn('role', ['staff', 'hr', 'system_admin'])
            ->where('is_archived', false)->whereRaw('LOWER(COALESCE(employee_type, ?)) != ?', ['', 'intern'])
            ->where(fn ($q) => $q->whereNull('date_hired')->orWhereDate('date_hired', '<=', $date))
            ->where(fn ($q) => $q->whereNull('termination_date')->orWhereDate('termination_date', '>', $date))->get();
        $report->lines()->delete();
        $count = 0;
        foreach ($users->sortBy(fn (User $user) => [-($user->position->salary_grade ?? 0), $user->id]) as $user) {
            $accomplishment = $approved->get($user->id);
            $text = $accomplishment?->accomplishment;
            $evidence = $accomplishment ? ['accomplishment_id' => $accomplishment->id, 'updated_at' => $accomplishment->updated_at?->toIso8601String()] : [];
            if ($accomplishment) {
                $count++;
            } else {
                $leave = LeaveDate::whereDate('leave_date', $date)->where('day_fraction', 1)->whereHas('request', fn ($q) => $q->where('user_id', $user->id)
                    ->whereRaw("(select status from request_statuses where request_type = 'LEAVE' and request_id = leave_requests.id order by id desc limit 1) = 'Approved'"))->first();
                $onsite = RawPunch::where('user_id', $user->id)->whereDate('punched_at', $date)->whereNotNull('device')->where('device', '!=', 'WEB WFH')->first();
                $text = $leave ? 'on approved leave' : ($onsite ? 'worked onsite' : 'no record');
                $evidence = ['leave_request_id' => $leave?->leave_request_id, 'onsite_punch_id' => $leave ? null : $onsite?->id];
            }
            $report->lines()->create(['user_id' => $user->id, 'wfh_accomplishment_id' => $accomplishment?->id,
                'snapshot' => ['employee' => EmployeeSnapshot::for($user), 'accomplishment' => $text, 'evidence' => $evidence]]);
        }
        abort_if($count === 0, 422, 'No approved accomplishments belong to currently eligible report employees.');
        $report->update(['submitted_count' => $count]);
    }
}
