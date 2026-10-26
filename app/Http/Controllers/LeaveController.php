<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesHrisRequests;
use App\Models\CtoRequest;
use App\Models\Holiday;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Requests\RequestWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeaveController extends Controller
{
    use HandlesHrisRequests;

    public function index(Request $request): View
    {
        abort_if($request->user()->role === 'applicant', 403);

        return view('hris.leave', ['types' => LeaveType::where('active', true)->orderBy('id')->get()]);
    }

    public function data(Request $request, RequestWorkflow $workflow): JsonResponse
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|string|max:50']);
        $query = $this->visibleRequests(LeaveRequest::query()->with(['employee:id,name,division_id,role', 'type:id,code,name', 'dates', 'statuses.actor:id,name,role']), $request, 'LEAVE')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($x) => $x->where('details', 'like', "%{$s}%")->orWhereHas('employee', fn ($u) => $u->where('name', 'like', "%{$s}%"))))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->whereRaw('(select status from request_statuses where request_type = ? and request_id = leave_requests.id order by id desc limit 1) = ?', ['LEAVE', $s]));
        $page = max(1, min($request->integer('page', 1), max(1, (int) ceil((clone $query)->count() / 10))));
        $rows = $query->latest('filed_on')->latest('id')->paginate(10, ['*'], 'page', $page);
        $rows->getCollection()->transform(function (LeaveRequest $row) use ($request, $workflow) {
            $data = $row->toArray();
            unset($data['statuses']);

            return $data + [
                'dates_label' => $row->dates->pluck('leave_date')->map->format('M-d-Y')->join(', '),
                'available_actions' => $workflow->availableActions('LEAVE', $row, $request->user()),
                'action_history' => $row->statuses->sortByDesc(fn ($status) => [$status->created_at->getTimestamp(), $status->id])->values()->map(fn ($status) => [
                    'action' => $status->action,
                    'status' => $status->status,
                    'actor' => $status->actor->name,
                    'actor_role' => $status->actor->role,
                    'reason' => $status->reason,
                    'acted_at' => $status->created_at->timezone('Asia/Manila')->format('M d, Y h:i A'),
                ]),
            ];
        });

        return response()->json($rows);
    }

    public function store(Request $request, RequestWorkflow $workflow): JsonResponse
    {
        $data = $request->validate(['leave_type_id' => 'required|integer|exists:leave_types,id', 'detail_code' => 'nullable|string|max:20', 'details' => 'nullable|string|max:2000',
            'commutation' => 'required|in:not_requested,requested', 'for_disapproval' => 'sometimes|boolean', 'requested_days' => 'nullable|numeric|min:0.001|max:999',
            'dates' => 'nullable|array|max:60', 'dates.*.date' => 'required_with:dates|date|distinct', 'acknowledge_non_working_days' => 'sometimes|boolean']);
        $type = LeaveType::whereKey($data['leave_type_id'])->firstOrFail();
        $allowedDetails = match ($type->code) {
            'VL' => ['1', '2', '7', '8'],
            'SL' => ['3', '4', '7', '8'],
            'SPL' => ['1', '2'],
            'STUDY' => ['5', '6'],
            default => [],
        };
        $detailCode = $data['detail_code'] ?? null;
        if ($allowedDetails === [] && $detailCode !== null) {
            throw ValidationException::withMessages(['detail_code' => 'This leave type has no additional detail option.']);
        }
        if ($allowedDetails !== [] && ! in_array($detailCode, $allowedDetails, true)) {
            throw ValidationException::withMessages(['detail_code' => 'Choose a valid detail for the selected leave type.']);
        }
        if (in_array($detailCode, ['2', '3', '4'], true) && blank($data['details'] ?? null)) {
            throw ValidationException::withMessages(['details' => 'Please specify the location or medical details for this option.']);
        }
        $data['for_disapproval'] = $type->code === 'FL' && (bool) ($data['for_disapproval'] ?? false);
        $monetization = ($data['detail_code'] ?? null) === '7';
        if (! $monetization && empty($data['dates'])) {
            throw ValidationException::withMessages(['dates' => 'Select at least one leave date.']);
        }
        if ($monetization && ! empty($data['dates'])) {
            throw ValidationException::withMessages(['dates' => 'Monetization does not use absence dates.']);
        }
        if ($monetization && ! isset($data['requested_days'])) {
            throw ValidationException::withMessages(['requested_days' => 'Enter the number of days to monetize.']);
        }
        $days = $monetization ? (float) $data['requested_days'] : count($data['dates']);
        $employee = $request->user();
        $dateValues = array_column($data['dates'] ?? [], 'date');
        $warnings = $this->nonWorkingWarnings($employee, $dateValues);
        if ($warnings !== [] && ! $request->boolean('acknowledge_non_working_days')) {
            throw ValidationException::withMessages(['dates' => 'Review and acknowledge the selected holiday or non-working date(s).']);
        }
        if ($dateValues) {
            $leaveConflict = LeaveRequest::where('user_id', $employee->id)->whereHas('dates', fn ($q) => $q->whereIn('leave_date', $dateValues))
                ->whereHas('statuses', fn ($q) => $q->whereNotIn('status', ['Cancelled', 'Disapproved']))->exists();
            $ctoConflict = CtoRequest::where('user_id', $employee->id)->whereHas('dates', fn ($q) => $q->whereIn('cto_date', $dateValues))
                ->whereHas('statuses', fn ($q) => $q->whereNotIn('status', ['Cancelled', 'Disapproved by DC', 'Disapproved by ED']))->exists();
            if ($leaveConflict || $ctoConflict) {
                throw ValidationException::withMessages(['dates' => 'A selected date overlaps another active leave or CTO request.']);
            }
        }
        $record = DB::transaction(function () use ($employee, $data, $days, $workflow) {
            $record = LeaveRequest::create(array_merge($data, ['user_id' => $employee->id, 'filed_on' => today('Asia/Manila'), 'requested_days' => $days, 'created_by' => $employee->id]));
            $record->dates()->createMany(array_map(fn ($item) => ['leave_date' => $item['date'], 'day_fraction' => 1], $data['dates'] ?? []));
            $workflow->start('LEAVE', $record, $employee);

            return $record;
        });

        return response()->json(['message' => 'Leave application filed.', 'id' => $record->id], 201);
    }

    public function dateCheck(Request $request): JsonResponse
    {
        $data = $request->validate(['dates' => 'required|array|min:1|max:60', 'dates.*' => 'required|date|distinct']);

        return response()->json(['warnings' => $this->nonWorkingWarnings($request->user(), $data['dates'])]);
    }

    /** @param array<int, string> $dates
     * @return array<int, array{date: string, reason: string}>
     */
    private function nonWorkingWarnings(User $employee, array $dates): array
    {
        if ($dates === []) {
            return [];
        }
        $holidays = Holiday::effectiveFor($employee)->whereIn('type', ['Regular', 'Special Non-Working'])
            ->where(function ($query) use ($dates) {
                foreach ($dates as $date) {
                    $query->orWhereDate('holiday_date', $date);
                }
            })->get();
        $warnings = [];
        foreach ($dates as $date) {
            $day = CarbonImmutable::parse($date, 'Asia/Manila');
            $reasons = [];
            if (($employee->workweek ?? 5) === 4 ? $day->dayOfWeekIso >= 5 : $day->dayOfWeekIso >= 6) {
                $reasons[] = 'non-working weekday for the employee schedule';
            }
            foreach ($holidays as $holiday) {
                if (CarbonImmutable::parse($holiday->holiday_date)->toDateString() === $date) {
                    $reasons[] = $holiday->name;
                }
            }
            if ($reasons !== []) {
                $warnings[] = ['date' => $date, 'reason' => implode('; ', $reasons)];
            }
        }

        return $warnings;
    }

    public function transition(Request $request, LeaveRequest $leave, RequestWorkflow $workflow): JsonResponse
    {
        $data = $request->validate(['action' => 'required|in:certify,return,approve_dc,reject_dc,approve_ed,reject_ed,witness,reject_witness,cancel',
            'reason' => 'nullable|string|max:1000', 'paid_days' => 'nullable|numeric|min:0', 'unpaid_days' => 'nullable|numeric|min:0']);
        if ($data['action'] === 'return' && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'A reason is required when returning an application.']);
        }
        $finalApproval = in_array($data['action'], ['approve_ed', 'witness'], true);
        if ($finalApproval) {
            $paid = round((float) ($data['paid_days'] ?? $leave->requested_days), 3);
            $unpaid = round((float) ($data['unpaid_days'] ?? 0), 3);
            if (abs(($paid + $unpaid) - (float) $leave->requested_days) > .0005) {
                throw ValidationException::withMessages(['paid_days' => 'Paid and unpaid days must equal the requested days.']);
            }
            $data['paid_days'] = $paid;
            $data['unpaid_days'] = $unpaid;
        }
        $status = DB::transaction(function () use ($request, $leave, $workflow, $data, $finalApproval) {
            $before = $workflow->current('LEAVE', $leave->id, true)?->status;
            $status = $workflow->transition('LEAVE', $leave, $request->user(), $data['action'], $data['reason'] ?? null);
            if ($status->status === 'Approved' && $finalApproval) {
                $leave->update(['paid_days' => $data['paid_days'], 'unpaid_days' => $data['unpaid_days'], 'recommendation_reason' => $data['reason'] ?? null]);
                if ((float) $data['paid_days'] > 0) {
                    LeaveLedgerEntry::firstOrCreate(['idempotency_key' => 'leave:'.$leave->id.':debit'], ['user_id' => $leave->user_id, 'leave_type_id' => $leave->leave_type_id,
                        'direction' => 'OUT', 'days' => $data['paid_days'], 'scope_date' => $leave->filed_on, 'source_type' => 'leave', 'source_id' => $leave->id,
                        'activity' => 'Approved leave #'.$leave->id, 'actor_id' => $request->user()->id, 'created_at' => now()]);
                }
            }
            if ($status->status === 'Returned by HR') {
                $leave->update(['return_message' => $data['reason']]);
            }
            if ($status->status === 'Cancelled' && $before === 'Approved' && LeaveLedgerEntry::where('idempotency_key', 'leave:'.$leave->id.':debit')->exists()) {
                LeaveLedgerEntry::firstOrCreate(['idempotency_key' => 'leave:'.$leave->id.':reversal'], ['user_id' => $leave->user_id, 'leave_type_id' => $leave->leave_type_id,
                    'direction' => 'IN', 'days' => $leave->paid_days, 'scope_date' => today('Asia/Manila'), 'source_type' => 'leave-reversal', 'source_id' => $leave->id,
                    'activity' => 'Cancelled leave #'.$leave->id, 'actor_id' => $request->user()->id, 'created_at' => now()]);
            }

            return $status;
        });

        return response()->json(['message' => 'Application is now '.$status->status.'.']);
    }

    public function ledger(Request $request, User $employee): JsonResponse
    {
        abort_unless($request->user()->id === $employee->id || $request->user()->hasAbility('leave.ledger'), 403);
        $rows = LeaveLedgerEntry::where('user_id', $employee->id)->latest('id')->paginate(10);

        return response()->json($rows);
    }

    public function adjust(Request $request, User $employee): JsonResponse
    {
        abort_unless($request->user()->hasAbility('leave.ledger'), 403);
        $data = $request->validate(['leave_type_id' => 'required|exists:leave_types,id', 'direction' => 'required|in:IN,OUT', 'days' => 'required|numeric|min:0.001|max:999',
            'scope_date' => 'required|date', 'reason' => 'required|string|min:5|max:1000', 'idempotency_key' => 'required|string|max:100|unique:leave_ledger_entries,idempotency_key']);
        $entry = LeaveLedgerEntry::create($data + ['user_id' => $employee->id, 'source_type' => 'adjustment', 'activity' => $data['reason'], 'actor_id' => $request->user()->id, 'created_at' => now()]);

        return response()->json(['message' => 'Leave-credit adjustment recorded.', 'id' => $entry->id], 201);
    }

    public function print(Request $request, LeaveRequest $leave): View
    {
        $record = $this->visibleRequests(LeaveRequest::with(['employee.division', 'employee.position', 'type', 'dates', 'statuses.actor'])->whereKey($leave->id), $request, 'LEAVE')->firstOrFail();
        $balances = LeaveLedgerEntry::where('user_id', $record->user_id)
            ->selectRaw('leave_type_id, SUM(CASE WHEN direction = ? THEN days ELSE -days END) AS balance', ['IN'])
            ->groupBy('leave_type_id')->pluck('balance', 'leave_type_id');

        return view('hris.request-print', ['kind' => 'leave', 'record' => $record, 'status' => $record->current_status, 'balances' => $balances]);
    }
}
