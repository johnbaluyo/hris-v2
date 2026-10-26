<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRemark;
use App\Models\AttendanceRevision;
use App\Models\CtoRequest;
use App\Models\DailyAttendance;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OfficialBusiness;
use App\Models\RawPunch;
use App\Models\RequestStatus;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\OfficePunchAccess;
use App\Services\Attendance\WfhSchedule;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function index(Request $request, WfhSchedule $wfhSchedule, OfficePunchAccess $officePunchAccess, ?User $employee = null): View
    {
        $employee ??= $request->user();
        Gate::authorize('attendance', $employee);

        $wfh = $wfhSchedule->isAvailableToday();
        $officeAccess = $officePunchAccess->availability($request->user());

        return view('hris.attendance', compact('employee', 'wfh', 'officeAccess'));
    }

    /** @return array{0: string, 1: string} */
    private function range(Request $request): array
    {
        $data = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $from = $data['from'] ?? today('Asia/Manila')->startOfMonth()->toDateString();
        $to = $data['to'] ?? today('Asia/Manila')->endOfMonth()->toDateString();
        abort_if(CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to), false) > 366 || $from > $to, 422, 'Choose a date range of at most one year.');

        return [$from, $to];
    }

    public function data(Request $request, User $employee): JsonResponse
    {
        Gate::authorize('attendance', $employee);
        [$from, $to] = $this->range($request);
        $query = DailyAttendance::where('user_id', $employee->id)->whereBetween('attendance_date', [$from, $to]);
        if ($request->boolean('review')) {
            $query->where('needs_review', true);
        }
        $summary = (clone $query)->selectRaw('COUNT(*) as days, SUM(worked_minutes) as worked_minutes, SUM(late_minutes) as late_minutes, SUM(undertime_minutes) as undertime_minutes')->first();
        $page = min(max(1, $request->integer('page', 1)), max(1, (int) ceil((clone $query)->count() / 10)));

        return response()->json($query->orderByDesc('attendance_date')->paginate(10, ['*'], 'page', $page)->toArray() + ['summary' => $summary]);
    }

    public function raw(Request $request, User $employee): JsonResponse
    {
        Gate::authorize('attendance', $employee);
        [$from, $to] = $this->range($request);
        $query = RawPunch::where('user_id', $employee->id)
            ->where('punched_at', '>=', $from.' 00:00:00')->where('punched_at', '<', CarbonImmutable::parse($to)->addDay()->toDateString().' 00:00:00');

        return response()->json($query->orderByDesc('punched_at')->paginate(10, ['id', 'punched_at', 'device', 'record_type', 'anviz_log_id']));
    }

    public function clock(Request $request, WfhSchedule $wfhSchedule, OfficePunchAccess $officePunchAccess): JsonResponse
    {
        $user = $request->user();
        abort_if($user->role === 'applicant' || ! $user->atms_id, 422, 'An employee attendance identity is required.');
        $data = $request->validate(['wfh' => 'required|boolean', 'user_id' => 'prohibited', 'user_atms_id' => 'prohibited', 'atms_id' => 'prohibited', 'time_log' => 'prohibited']);
        if ($data['wfh']) {
            abort_unless($wfhSchedule->isAvailableToday(), 422, 'WFH capture is available only on Wednesdays or dates covered by a Work From Home calendar event.');
        } else {
            abort_unless($officePunchAccess->isAvailable($user), 422, 'Office punches require an active access window set by an administrator.');
        }
        RawPunch::create(['user_id' => $user->id, 'atms_id' => $user->atms_id, 'punched_at' => now('Asia/Manila'), 'device' => $data['wfh'] ? 'WEB WFH' : 'WEB APP']);

        return response()->json(['message' => 'Attendance punch recorded using the server time.'], 201);
    }

    public function clockAvailability(Request $request, OfficePunchAccess $officePunchAccess): JsonResponse
    {
        Gate::authorize('attendance', $request->user());

        return response()->json($officePunchAccess->availability($request->user()));
    }

    public function rebuild(Request $request, User $employee, AttendanceService $service): JsonResponse
    {
        Gate::authorize('correctAttendance', $employee);
        [$from, $to] = $this->range($request);
        $data = $request->validate(['reason' => 'required|string|max:1000', 'replace_override' => 'sometimes|boolean']);
        abort_if(CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 31, 422, 'Recalculate at most 32 days at a time.');
        for ($date = CarbonImmutable::parse($from); $date->toDateString() <= $to; $date = $date->addDay()) {
            $service->rebuild($employee, $date->toDateString(), $request->user()->id, $data['reason'], $request->boolean('replace_override'));
        }

        return response()->json(['message' => 'Attendance recalculated. Manual overrides were retained unless explicitly replaced.']);
    }

    public function override(Request $request, User $employee, AttendanceService $service): JsonResponse
    {
        Gate::authorize('correctAttendance', $employee);
        $rules = ['attendance_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'workweek' => 'nullable|in:4,5', 'notes' => 'nullable|string|max:1000', 'reason' => 'required|string|max:1000'];
        foreach (['time_in', 'lunch_out', 'lunch_in', 'time_out', 'ot_in', 'ot_out'] as $field) {
            $rules[$field] = 'nullable|date_format:H:i';
        }
        $data = $request->validate($rules);
        $last = null;
        foreach (['time_in', 'lunch_out', 'lunch_in', 'time_out', 'ot_in', 'ot_out'] as $field) {
            if (! empty($data[$field])) {
                abort_if($last !== null && $data[$field] < $last, 422, 'Punches must be chronological within the same day. Overnight shifts require a reviewed policy.');
                $last = $data[$field];
            }
        }
        $date = $data['attendance_date'];
        $reason = $data['reason'];
        unset($data['attendance_date'], $data['reason']);
        $service->override($employee, $date, $data, $request->user()->id, $reason);

        return response()->json(['message' => 'Correction saved with an audit revision.']);
    }

    public function revisions(Request $request, User $employee, DailyAttendance $attendance): JsonResponse
    {
        Gate::authorize('attendance', $employee);
        abort_unless($attendance->user_id === $employee->id, 404);

        return response()->json(AttendanceRevision::where('daily_attendance_id', $attendance->id)->latest('id')->paginate(10));
    }

    public function remarks(Request $request, User $employee): JsonResponse
    {
        Gate::authorize('attendance', $employee);
        $data = $request->validate(['coverage' => 'required|date_format:Y-m-d']);
        $coverage = CarbonImmutable::parse($data['coverage'])->startOfMonth()->toDateString();
        if ($request->isMethod('get')) {
            return response()->json(AttendanceRemark::where('user_id', $employee->id)->whereDate('coverage', $coverage)->first());
        }
        Gate::authorize('correctAttendance', $employee);
        $data = $request->validate(['fc_absence' => 'required|integer|min:0|max:31', 'remarks' => 'nullable|string|max:1000']);
        DB::transaction(function () use ($employee, $request, $coverage, $data) {
            User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $record = AttendanceRemark::firstOrNew(['user_id' => $employee->id, 'coverage' => $coverage]);
            $before = $record->attributesToArray();
            $record->fill($data + ['actor_id' => $request->user()->id])->save();
            Audit::record('attendance.remarks-saved', $record, $request->user()->id, $before);
        });

        return response()->json(['message' => 'Monthly remarks saved.']);
    }

    public function report(Request $request, User $employee): View|StreamedResponse
    {
        Gate::authorize('attendance', $employee);
        abort_unless($request->user()->id === $employee->id || $request->user()->hasAbility('attendance.export'), 403);
        $data = $request->validate([
            'month' => 'nullable|date_format:Y-m',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'format' => 'nullable|in:csv,xls',
        ]);
        $month = CarbonImmutable::createFromFormat('!Y-m', $data['month'] ?? (isset($data['from']) ? CarbonImmutable::parse($data['from'])->format('Y-m') : today('Asia/Manila')->format('Y-m')));
        abort_unless($month, 422, 'Choose a valid month and year.');
        $from = $month->startOfMonth()->toDateString();
        $to = $month->endOfMonth()->toDateString();
        $rows = DailyAttendance::where('user_id', $employee->id)->whereBetween('attendance_date', [$from, $to])->orderBy('attendance_date')->get();
        $remarks = AttendanceRemark::where('user_id', $employee->id)->whereBetween('coverage', [CarbonImmutable::parse($from)->startOfMonth()->toDateString(), $to])->get();
        if ($request->input('format') === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $stream = fopen('php://output', 'w');
                if ($stream === false) {
                    throw new \RuntimeException('The report output stream could not be opened.');
                }
                fputcsv($stream, ['Date', 'Time in', 'Lunch out', 'Lunch in', 'Time out', 'Worked minutes', 'Late minutes', 'Undertime minutes', 'Policy', 'Needs review'], ',', '"', '');
                foreach ($rows as $row) {
                    fputcsv($stream, [$row->attendance_date->toDateString(), $row->time_in, $row->lunch_out, $row->lunch_in, $row->time_out,
                        $row->worked_minutes, $row->late_minutes, $row->undertime_minutes, $row->policy_version, $row->needs_review ? 'Yes' : 'No'], ',', '"', '');
                }
                fclose($stream);
            }, 'attendance-'.$employee->id.'-'.$from.'.csv', ['Cache-Control' => 'private, no-store']);
        }

        $statuses = RequestStatus::whereIn('request_type', ['OB', 'LEAVE', 'CTO'])
            ->orderBy('id')->get()->groupBy(fn (RequestStatus $status) => $status->request_type.':'.$status->request_id)
            ->map(fn ($history) => $history->last()->status);
        $approved = static fn (string $type, int $id, array $accepted): bool => in_array($statuses->get($type.':'.$id), $accepted, true);

        $officialBusinesses = OfficialBusiness::where('user_id', $employee->id)->whereBetween('business_date', [$from, $to])->get()
            ->filter(fn (OfficialBusiness $business) => $approved('OB', $business->id, ['Approved']))
            ->keyBy(fn (OfficialBusiness $business) => $business->business_date->toDateString());
        $leaveRequests = LeaveRequest::with(['type', 'dates'])->where('user_id', $employee->id)
            ->whereHas('dates', fn ($query) => $query->whereBetween('leave_date', [$from, $to]))->get()
            ->filter(fn (LeaveRequest $leave) => $approved('LEAVE', $leave->id, ['Approved', 'For Cancellation']));
        $leaves = $leaveRequests->flatMap(fn (LeaveRequest $leave) => $leave->dates->mapWithKeys(
            fn ($date) => [$date->leave_date->toDateString() => $leave],
        ));
        $ctoRequests = CtoRequest::with('dates')->where('user_id', $employee->id)
            ->whereHas('dates', fn ($query) => $query->whereBetween('cto_date', [$from, $to]))->get()
            ->filter(fn (CtoRequest $cto) => $approved('CTO', $cto->id, ['Approved', 'For Cancellation']));
        $ctoDates = $ctoRequests->flatMap(fn (CtoRequest $cto) => $cto->dates->mapWithKeys(
            fn ($date) => [$date->cto_date->toDateString() => ['request' => $cto, 'date' => $date]],
        ));
        $holidays = Holiday::effectiveFor($employee)->whereBetween('holiday_date', [$from, $to])->orderByDesc('id')->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->holiday_date->toDateString());

        $rowsByDate = $rows->keyBy(fn (DailyAttendance $attendance) => $attendance->attendance_date->toDateString());
        $weekOfMonth = static function (CarbonImmutable $date): int {
            $firstWeek = (int) $date->startOfMonth()->format('W');
            $week = (int) $date->format('W');
            if ($date->month === 1 && $week > 51) {
                $week = 0;
            } elseif ($date->month === 12 && $week === 1) {
                $week = 53;
            }

            return max(1, min(6, $week - $firstWeek + 1));
        };
        $statistics = array_fill(1, 6, ['worked' => 0, 'late' => 0, 'late_count' => 0, 'undertime' => 0, 'undertime_count' => 0, 'absent' => 0, 'overtime' => 0]);
        $reportDays = collect();
        for ($date = CarbonImmutable::parse($from); $date->lte(CarbonImmutable::parse($to)); $date = $date->addDay()) {
            $key = $date->toDateString();
            $attendance = $rowsByDate->get($key);
            $holiday = $holidays->get($key);
            $business = $officialBusinesses->get($key);
            $leave = $leaves->get($key);
            $cto = $ctoDates->get($key);
            $week = $weekOfMonth($date);
            if ($attendance) {
                $statistics[$week]['worked'] += (int) ($attendance->worked_minutes ?? 0);
                $statistics[$week]['late'] += (int) ($attendance->late_minutes ?? 0);
                $statistics[$week]['late_count'] += (int) (($attendance->late_minutes ?? 0) > 0);
                $statistics[$week]['undertime'] += (int) ($attendance->undertime_minutes ?? 0);
                $statistics[$week]['undertime_count'] += (int) (($attendance->undertime_minutes ?? 0) > 0);
                $statistics[$week]['overtime'] += (int) ($attendance->overtime_minutes ?? 0);
            }
            if ($leave && in_array($leave->type?->name, ['Vacation Leave', 'Sick Leave'], true)) {
                $statistics[$week]['absent']++;
            }
            $reportDays->push(compact('date', 'attendance', 'holiday', 'business', 'leave', 'cto', 'week'));
        }

        $reportData = compact('employee', 'rows', 'from', 'to', 'remarks', 'reportDays', 'statistics');
        if ($request->input('format') === 'xls') {
            return response()->streamDownload(function () use ($reportData) {
                echo view('hris.attendance-print', $reportData)->render();
            }, 'DTR-'.$employee->id.'-'.$from.'.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }

        return view('hris.attendance-print', $reportData);
    }
}
