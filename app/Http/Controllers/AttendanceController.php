<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRemark;
use App\Models\AttendanceRevision;
use App\Models\DailyAttendance;
use App\Models\RawPunch;
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
        [$from, $to] = $this->range($request);
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

        return view('hris.attendance-print', compact('employee', 'rows', 'from', 'to', 'remarks'));
    }
}
