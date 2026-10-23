<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\RequestStatus;
use App\Models\User;
use App\Models\WfhAccomplishment;
use App\Models\WfhReport;
use App\Services\Attendance\WfhSchedule;
use App\Services\Notifications\NotificationPublisher;
use App\Services\Wfh\Consolidation;
use App\Support\EmployeeSnapshot;
use App\Support\WorkflowHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WfhAccomplishmentController extends Controller
{
    public function index(Request $request): View
    {
        abort_if($request->user()->role === 'applicant', 403);

        return view('hris.wfh-accomplishments', ['divisions' => Division::orderBy('name')->get()->filter(fn (Division $division) => $request->user()->actsAsDivisionChiefOf($division->id))]);
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function scoped(Builder $query, User $actor, bool $reports = false): Builder
    {
        abort_if($actor->role === 'applicant', 403);
        if ($actor->actsAsDirector()) {
            return $query;
        }
        $divisions = Division::all()->filter(fn (Division $division) => $actor->actsAsDivisionChiefOf($division->id))->pluck('id');

        return $query->where(function ($q) use ($actor, $divisions, $reports) {
            $q->whereIn('division_id', $divisions);
            if ($reports && $actor->hasAbility('staff.manage')) {
                $q->orWhere('status', 'approved');
            }
            if (! $reports) {
                $q->orWhere('user_id', $actor->id);
            }
        });
    }

    public function data(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'wfh_date' => 'nullable|date_format:Y-m-d']);
        $query = $this->scoped(WfhAccomplishment::with('employee:id,name', 'history.actor:id,name,role'), $request->user())->whereNull('archived_at');

        $page = $query->when($filters['search'] ?? null, fn ($q, $text) => $q->where('accomplishment', 'like', "%{$text}%"))
            ->when($filters['wfh_date'] ?? null, fn ($q, $date) => $q->whereDate('wfh_date', $date))->latest('wfh_date')->paginate(10);
        $page->getCollection()->transform(function (WfhAccomplishment $record) use ($request) {
            $actor = $request->user();
            $locked = WfhReport::where('division_id', $record->division_id)->whereDate('wfh_date', $record->wfh_date)->whereNotNull('signed_at')->exists();
            $mutable = ! $locked && $record->status !== 'approved';
            $record->setAttribute('can_edit', $mutable && ($actor->id === $record->user_id || $actor->actsAsDivisionChiefOf($record->division_id)));
            $record->setAttribute('can_delete', $mutable && $actor->id === $record->user_id);
            $record->setAttribute('can_review', ! $locked && $record->status === 'for approval' && $actor->id !== $record->user_id
                && ($actor->actsAsDivisionChiefOf($record->division_id) || $actor->actsAsDirector()));

            return $record;
        });

        return response()->json($page);
    }

    public function reports(Request $request): JsonResponse
    {
        $page = $this->scoped(WfhReport::query(), $request->user(), true)->whereNull('archived_at')->latest('wfh_date')->paginate(10);
        $page->getCollection()->transform(function (WfhReport $report) use ($request) {
            $report->setAttribute('can_sign', $report->status === 'for review' && $request->user()->actsAsDivisionChiefOf($report->division_id));
            $report->setAttribute('can_approve', $report->status === 'for approval' && $request->user()->actsAsDirector());

            return $report;
        });

        return response()->json($page);
    }

    private function employeeEligible(User $employee, string $date): bool
    {
        return $employee->isEligible() && in_array($employee->role, ['staff', 'hr', 'system_admin'], true)
            && strcasecmp($employee->employee_type ?? '', 'Intern') !== 0 && $employee->division_id !== null
            && ($employee->date_hired === null || $employee->date_hired->toDateString() <= $date);
    }

    private function lockDivision(int $divisionId, string $date): void
    {
        Division::whereKey($divisionId)->lockForUpdate()->firstOrFail();
        abort_if(WfhReport::where('division_id', $divisionId)->whereDate('wfh_date', $date)->whereNotNull('signed_at')->exists(), 422, 'This division date has been signed and is locked.');
    }

    public function save(Request $request, WfhSchedule $schedule, ?WfhAccomplishment $accomplishment = null): JsonResponse
    {
        $data = $request->validate(['wfh_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'accomplishment' => 'required|string|max:20000', 'employee_id' => 'nullable|integer|exists:users,id']);
        abort_unless($schedule->isAvailableOn($data['wfh_date']), 422, 'Choose a Wednesday or a date covered by a WFH calendar event.');
        $actor = $request->user();
        $employee = User::whereKey($accomplishment->user_id ?? $data['employee_id'] ?? $actor->id)->firstOrFail();
        abort_unless($this->employeeEligible($employee, $data['wfh_date']), 422, 'This employee is not eligible for this WFH date.');
        $divisionId = $accomplishment->division_id ?? $employee->division_id;
        abort_unless($employee->id === $actor->id || $actor->actsAsDivisionChiefOf($divisionId), 403);
        $record = DB::transaction(function () use ($request, $data, $employee, $divisionId, $accomplishment) {
            $this->lockDivision($divisionId, $data['wfh_date']);
            if ($accomplishment) {
                $record = WfhAccomplishment::whereKey($accomplishment->id)->lockForUpdate()->firstOrFail();
                abort_unless($record->wfh_date->toDateString() === $data['wfh_date'] && $record->status !== 'approved' && $record->archived_at === null, 422, 'Only a pending or returned accomplishment can be edited; its date is fixed.');
                $record->update(['accomplishment' => $data['accomplishment'], 'status' => 'for approval', 'return_remarks' => null]);
            } else {
                $existing = WfhAccomplishment::where('user_id', $employee->id)->whereDate('wfh_date', $data['wfh_date'])->first();
                abort_if($existing && $existing->archived_at === null, 422, 'This employee already has an accomplishment for this date.');
                abort_if($existing && $existing->division_id !== $divisionId, 422, 'A historical accomplishment cannot be moved to another division.');
                $record = WfhAccomplishment::updateOrCreate(['user_id' => $employee->id, 'wfh_date' => $data['wfh_date']],
                    ['division_id' => $divisionId, 'accomplishment' => $data['accomplishment'], 'status' => 'for approval', 'return_remarks' => null, 'archived_at' => null]);
            }
            WorkflowHistory::record('WFH', $record, $request->user(), 'submit', 'for approval');

            return $record;
        });

        return response()->json(['message' => 'WFH accomplishment submitted.', 'id' => $record->id], 201);
    }

    public function transition(Request $request, WfhAccomplishment $accomplishment, NotificationPublisher $notices): JsonResponse
    {
        $data = $request->validate(['action' => 'required|in:approve,return,delete', 'reason' => 'nullable|string|max:2000']);
        DB::transaction(function () use ($request, $data, $accomplishment, $notices) {
            $this->lockDivision($accomplishment->division_id, $accomplishment->wfh_date->toDateString());
            $record = WfhAccomplishment::whereKey($accomplishment->id)->lockForUpdate()->firstOrFail();
            abort_if($record->archived_at || $record->status === 'approved', 422, 'This record is final or deleted.');
            $actor = $request->user();
            if ($data['action'] === 'delete') {
                abort_unless($record->user_id === $actor->id, 403);
                $record->update(['archived_at' => now(), 'status' => 'deleted']);
            } else {
                abort_unless($record->user_id !== $actor->id && ($actor->actsAsDivisionChiefOf($record->division_id) || $actor->actsAsDirector()), 403);
                abort_unless($record->status === 'for approval', 422);
                abort_if($data['action'] === 'return' && blank($data['reason'] ?? null), 422, 'Return remarks are required.');
                $record->update(['status' => $data['action'] === 'approve' ? 'approved' : 'returned', 'return_remarks' => $data['action'] === 'return' ? $data['reason'] : null]);
            }
            WorkflowHistory::record('WFH', $record, $actor, $data['action'], $record->status, $data['reason'] ?? null);
            $historyId = RequestStatus::where('request_type', 'WFH')->where('request_id', $record->id)->max('id');
            $notices->afterCommit($record->employee, 'wfh:'.$historyId, 'WFH accomplishment updated', 'Your accomplishment is '.$record->status.'.', '/wfh-accomplishments');
        });

        return response()->json(['message' => 'WFH action recorded.']);
    }

    public function consolidate(Request $request, Consolidation $consolidation): JsonResponse
    {
        $data = $request->validate(['division_id' => 'required|integer|exists:divisions,id', 'wfh_date' => 'required|date_format:Y-m-d|before_or_equal:today']);
        abort_unless($request->user()->actsAsDivisionChiefOf((int) $data['division_id']), 403);
        $report = DB::transaction(function () use ($request, $data, $consolidation) {
            $this->lockDivision((int) $data['division_id'], $data['wfh_date']);
            $report = WfhReport::firstOrCreate(['division_id' => $data['division_id'], 'wfh_date' => $data['wfh_date']],
                ['status' => 'for review', 'created_by' => $request->user()->id, 'division_snapshot' => Division::whereKey($data['division_id'])->firstOrFail()->only('id', 'name', 'acronym')]);
            abort_unless($report->status === 'for review', 422);
            $report->update(['archived_at' => null]);
            $consolidation->capture($report);
            WorkflowHistory::record('WFH_REPORT', $report, $request->user(), 'consolidate', 'for review');

            return $report;
        });

        return response()->json(['message' => 'Division report consolidated.', 'id' => $report->id]);
    }

    public function reportAction(Request $request, WfhReport $report, Consolidation $consolidation): JsonResponse
    {
        $data = $request->validate(['action' => 'required|in:sign,approve,delete']);
        DB::transaction(function () use ($request, $data, $report, $consolidation) {
            Division::whereKey($report->division_id)->lockForUpdate()->firstOrFail();
            $report = WfhReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_if($report->archived_at !== null, 422);
            $actor = $request->user();
            if ($data['action'] === 'approve') {
                abort_unless($actor->actsAsDirector() && ($report->signer_snapshot['id'] ?? null) !== $actor->id, 403);
                if ($report->status === 'approved') {
                    return;
                }
                abort_unless($report->status === 'for approval', 422);
                $report->update(['status' => 'approved', 'approved_at' => now(), 'approver_snapshot' => EmployeeSnapshot::for($actor)]);
            } else {
                abort_unless($actor->actsAsDivisionChiefOf($report->division_id), 403);
                if ($data['action'] === 'sign' && $report->signed_at !== null) {
                    return;
                }
                abort_unless($report->status === 'for review', 422);
                if ($data['action'] === 'delete') {
                    $report->update(['archived_at' => now()]);
                } else {
                    $consolidation->capture($report);
                    $report->update(['status' => 'for approval', 'signed_at' => now(), 'signer_snapshot' => EmployeeSnapshot::for($actor)]);
                }
            }
            WorkflowHistory::record('WFH_REPORT', $report, $actor, $data['action'], $report->status);
        });

        return response()->json(['message' => 'Consolidated report action recorded.']);
    }

    public function print(Request $request, WfhReport $report): View
    {
        $actor = $request->user();
        abort_unless($actor->actsAsDivisionChiefOf($report->division_id) || $actor->actsAsDirector()
            || ($actor->hasAbility('staff.manage') && $report->status === 'approved'), 403);
        abort_if($report->archived_at !== null, 404);

        return view('hris.wfh-print', ['reports' => collect([$report->load('lines')]), 'date' => $report->wfh_date->toDateString()]);
    }

    public function agency(Request $request, string $date): View
    {
        abort_unless($request->user()->actsAsDirector() || $request->user()->hasAbility('staff.manage'), 403);
        validator(['date' => $date], ['date' => 'required|date_format:Y-m-d'])->validate();

        return view('hris.wfh-print', ['reports' => WfhReport::with('lines')->whereDate('wfh_date', $date)->where('status', 'approved')->whereNull('archived_at')->get(), 'date' => $date]);
    }
}
