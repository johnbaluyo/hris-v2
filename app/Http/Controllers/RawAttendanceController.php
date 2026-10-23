<?php

namespace App\Http\Controllers;

use App\Models\RawPunch;
use App\Services\Attendance\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RawAttendanceController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', RawPunch::class);

        return view('hris.raw-attendance');
    }

    public function data(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', RawPunch::class);
        $data = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d',
            'search' => 'nullable|string|max:200', 'matched' => 'nullable|in:yes,no']);
        $from = $data['from'] ?? today('Asia/Manila')->startOfMonth()->toDateString();
        $to = $data['to'] ?? today('Asia/Manila')->endOfMonth()->toDateString();
        abort_if($from > $to || CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 366, 422, 'Choose a date range of at most one year.');
        $query = RawPunch::with('employee:id,name')->withCount('revisions')
            ->where('punched_at', '>=', $from.' 00:00:00')
            ->where('punched_at', '<', CarbonImmutable::parse($to)->addDay()->toDateString().' 00:00:00');
        if (! empty($data['search'])) {
            $search = '%'.$data['search'].'%';
            $query->where(fn ($q) => $q->where('atms_id', 'like', $search)->orWhere('device', 'like', $search)
                ->orWhereHas('employee', fn ($employee) => $employee->where('name', 'like', $search)));
        }
        if (! empty($data['matched'])) {
            $data['matched'] === 'yes' ? $query->whereNotNull('user_id') : $query->whereNull('user_id');
        }
        $page = min(max(1, $request->integer('page', 1)), max(1, (int) ceil((clone $query)->count() / 10)));
        $rows = $query->orderByDesc('punched_at')->orderByDesc('id')->paginate(10, ['*'], 'page', $page);
        $rows->through(fn (RawPunch $punch) => [
            'id' => $punch->id, 'employee_name' => $punch->employee->name ?? 'Unmatched employee', 'atms_id' => $punch->atms_id,
            'punched_at' => $punch->punched_at->format('Y-m-d H:i:s'), 'device' => $punch->device, 'record_type' => $punch->record_type,
            'revisions_count' => $punch->revisions_count, 'version' => $punch->editVersion(),
        ]);

        return response()->json($rows);
    }

    public function update(Request $request, RawPunch $punch, AttendanceService $service): JsonResponse
    {
        Gate::authorize('update', $punch);
        $rules = ['punched_at' => 'required|date_format:Y-m-d\TH:i:s,Y-m-d\TH:i|before_or_equal:now', 'device' => 'required|string|max:100',
            'record_type' => 'nullable|string|max:50', 'reason' => 'required|string|max:900', 'version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']];
        foreach (['user_id', 'atms_id', 'service_client_id', 'source_key', 'anviz_log_id', 'source_epoch', 'wfh_location', 'lock_version', 'source_evidence'] as $field) {
            $rules[$field] = 'prohibited';
        }
        $data = $request->validate($rules);
        $data['punched_at'] = CarbonImmutable::parse($data['punched_at'], 'Asia/Manila')->format('Y-m-d H:i:s');
        $service->correctRawPunch($punch, $data, $data['version'], $request->user(), $data['reason']);

        return response()->json(['message' => $punch->user_id === null
            ? 'Punch saved with correction history. Employee identity reconciliation is required before DTR recalculation.'
            : 'Punch saved. Affected attendance dates were recalculated; manual DTR overrides were retained.']);
    }

    public function history(RawPunch $punch): JsonResponse
    {
        Gate::authorize('update', $punch);

        return response()->json($punch->revisions()->reorder()->with('actor:id,name')->latest('id')->paginate(10));
    }
}
