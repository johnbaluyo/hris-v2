<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRemark;
use App\Models\CtoRequest;
use App\Models\DailyAttendance;
use App\Models\Division;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveRequest;
use App\Models\OfficialBusiness;
use App\Models\User;
use App\Services\Attendance\AttendanceScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReviewController extends Controller
{
    private function authorizeReview(Request $request): void
    {
        abort_if(in_array($request->user()->role, ['staff', 'applicant'], true), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeReview($request);
        $actor = $request->user();
        $divisions = $actor->role === 'division_chief'
            ? Division::whereKey($actor->division_id)->get()
            : Division::orderBy('name')->get();
        $bulkEmployees = $actor->hasAbility('attendance.export')
            ? User::where('role', '!=', 'applicant')->where('is_archived', false)
                ->where(fn ($query) => $query->whereNull('termination_date')->orWhereDate('termination_date', '>', today('Asia/Manila')))
                ->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('hris.attendance-review', compact('divisions', 'bulkEmployees'));
    }

    /** @return Builder<User> */
    private function query(Request $request, AttendanceScope $scope): Builder
    {
        $this->authorizeReview($request);
        $data = $request->validate(['month' => 'required|date_format:Y-m', 'search' => 'nullable|string|max:100', 'division_id' => 'nullable|integer|exists:divisions,id']);
        if ($request->user()->role === 'division_chief') {
            abort_unless($request->user()->division_id, 403);
            $data['division_id'] = $request->user()->division_id;
        }
        $date = CarbonImmutable::createFromFormat('!Y-m', $data['month']);
        if ($date === null) {
            abort(422, 'Choose a valid month.');
        }
        $from = $date->startOfMonth()->toDateString();
        $to = $date->endOfMonth()->toDateString();
        $period = fn ($q) => $q->whereBetween('attendance_date', [$from, $to]);

        return $scope->employees($request->user())->select(['users.id', 'users.name', 'users.first_name', 'users.middle_name', 'users.last_name', 'users.division_id', 'users.workweek'])->with('division:id,name')
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($data['division_id'] ?? null, fn ($q, $id) => $q->where('division_id', $id))
            ->withCount(['attendances as recorded_days' => $period, 'attendances as review_days' => fn ($q) => $period($q)->where('needs_review', true)])
            ->withSum(['attendances as worked_minutes' => $period], 'worked_minutes')
            ->withSum(['attendances as late_minutes' => $period], 'late_minutes')
            ->withSum(['attendances as undertime_minutes' => $period], 'undertime_minutes');
    }

    public function data(Request $request, AttendanceScope $scope): JsonResponse
    {
        $query = $this->query($request, $scope);
        $page = min(max(1, $request->integer('page', 1)), max(1, (int) ceil((clone $query)->count() / 10)));

        return response()->json($query->orderBy('name')->orderBy('id')->paginate(10, ['id', 'name', 'division_id'], 'page', $page));
    }

    public function export(Request $request, AttendanceScope $scope): StreamedResponse
    {
        abort_unless($request->user()->hasAbility('attendance.export'), 403);
        $query = $this->query($request, $scope);
        $month = CarbonImmutable::createFromFormat('!Y-m', (string) $request->string('month'));
        abort_unless($month, 422);
        $from = $month->startOfMonth()->toDateString();
        $to = $month->endOfMonth()->toDateString();
        $employees = $query->with('division:id,name')->orderBy('name')->get();
        $ids = $employees->pluck('id');
        $attendance = DailyAttendance::whereIn('user_id', $ids)->whereBetween('attendance_date', [$from, $to])->get()->groupBy('user_id');
        $credits = LeaveLedgerEntry::whereIn('user_id', $ids)->whereDate('scope_date', '<=', $to)->get()->groupBy('user_id');
        $leaves = LeaveRequest::with(['type', 'dates', 'statuses'])->whereIn('user_id', $ids)
            ->whereHas('dates', fn ($dates) => $dates->whereBetween('leave_date', [$from, $to]))->get()
            ->filter(fn (LeaveRequest $leave) => $leave->statuses->sortByDesc('id')->first()?->status === 'Approved')->groupBy('user_id');
        $ctos = CtoRequest::with(['dates', 'statuses'])->whereIn('user_id', $ids)
            ->whereHas('dates', fn ($dates) => $dates->whereBetween('cto_date', [$from, $to]))->get()
            ->filter(fn (CtoRequest $cto) => $cto->statuses->sortByDesc('id')->first()?->status === 'Approved')->groupBy('user_id');
        $business = OfficialBusiness::with('statuses')->whereIn('user_id', $ids)->whereBetween('business_date', [$from, $to])->where('type', 2)->get()
            ->filter(fn (OfficialBusiness $ob) => $ob->statuses->sortByDesc('id')->first()?->status === 'Approved')->groupBy('user_id');
        $remarks = AttendanceRemark::whereIn('user_id', $ids)->whereDate('coverage', $month->startOfMonth())->get()->keyBy('user_id');

        $rows = $employees->map(function (User $employee) use ($attendance, $credits, $leaves, $ctos, $business, $remarks, $from, $to) {
            $days = $attendance->get($employee->id, collect());
            $ledger = $credits->get($employee->id, collect());
            $employeeLeaves = $leaves->get($employee->id, collect());
            $leaveDays = $employeeLeaves->flatMap(fn (LeaveRequest $leave) => $leave->dates->whereBetween('leave_date', [$from, $to])->map(fn ($date) => ['code' => $leave->type?->code, 'fraction' => (float) $date->day_fraction]));
            $employeeCtos = $ctos->get($employee->id, collect())->flatMap->dates->whereBetween('cto_date', [$from, $to]);
            $balance = fn (int $type) => $ledger->where('leave_type_id', $type)->sum(fn ($entry) => ($entry->direction === 'IN' ? 1 : -1) * (float) $entry->days);
            $workday = ($employee->workweek ?? 5) === 4 ? 600 : 480;

            return [
                'name' => $employee->name, 'vl' => $balance(1), 'sl' => $balance(2),
                'late_deduction' => $days->sum('late_minutes') / $workday, 'ut_deduction' => $days->sum('undertime_minutes') / $workday,
                'late_count' => $days->where('late_minutes', '>', 0)->count(), 'ut_count' => $days->where('undertime_minutes', '>', 0)->count(),
                'absent' => $employeeLeaves->sum(fn ($leave) => (float) ($leave->unpaid_days ?? 0)), 'lwop_late' => '', 'lwop_ut' => '',
                'sl_days' => $leaveDays->where('code', 'SL')->sum('fraction'), 'vl_days' => $leaveDays->where('code', 'VL')->sum('fraction'),
                'fl_days' => $leaveDays->where('code', 'FL')->sum('fraction'), 'spl_days' => $leaveDays->where('code', 'SPL')->sum('fraction'),
                'cto_days' => $employeeCtos->where('portion', 'WHOLE')->count(), 'cto_half' => $employeeCtos->whereIn('portion', ['AM', 'PM'])->count(),
                'medical_ob' => $business->get($employee->id, collect())->count(), 'fc_absence' => $remarks->get($employee->id)?->fc_absence,
                'remarks' => $remarks->get($employee->id)?->remarks,
            ];
        });

        return response()->streamDownload(function () use ($rows, $month) {
            echo view('hris.attendance-monthly-export', compact('rows', 'month'))->render();
        }, 'month_of_'.$month->format('F').'-DTR.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function bulkPrint(Request $request, AttendanceScope $scope, AttendanceController $attendance): View
    {
        abort_unless($request->user()->hasAbility('attendance.export'), 403);
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'employee_ids' => 'required|array|min:1|max:200',
            'employee_ids.*' => 'required|integer|distinct|exists:users,id',
        ]);
        $requested = collect($data['employee_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $employees = $scope->employees($request->user())->whereIn('id', $requested)->where('is_archived', false)
            ->where(fn ($query) => $query->whereNull('termination_date')->orWhereDate('termination_date', '>', today('Asia/Manila')))
            ->orderBy('name')->get();
        abort_unless($employees->count() === $requested->count(), 403);

        $reports = $employees->map(function (User $employee) use ($attendance, $data, $request) {
            $reportRequest = Request::create('/attendance/'.$employee->id.'/report', 'GET', ['month' => $data['month']]);
            $reportRequest->setUserResolver(fn () => $request->user());
            $view = $attendance->report($reportRequest, $employee);
            abort_unless($view instanceof View, 500, 'Unable to prepare the DTR print view.');

            return $view->getData();
        });

        return view('hris.attendance-print', compact('reports'));
    }
}
