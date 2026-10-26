<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesHrisRequests;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\Requests\RequestWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OvertimeController extends Controller
{
    use HandlesHrisRequests;

    public function index(Request $request): View
    {
        abort_if($request->user()->role === 'applicant', 403);

        return view('hris.overtime');
    }

    public function data(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|string|max:50']);
        $query = $this->visibleRequests(OvertimeRequest::query()->with(['employee:id,name,division_id', 'dates']), $request, 'OT')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($x) => $x->where('purpose', 'like', "%{$s}%")->orWhereHas('employee', fn ($u) => $u->where('name', 'like', "%{$s}%"))))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->whereRaw('(select status from request_statuses where request_type = ? and request_id = overtime_requests.id order by id desc limit 1) = ?', ['OT', $s]));
        $page = max(1, min($request->integer('page', 1), max(1, (int) ceil((clone $query)->count() / 10))));
        $rows = $query->latest('filed_on')->latest('id')->paginate(10, ['*'], 'page', $page);
        $rows->getCollection()->transform(fn (OvertimeRequest $row) => $row->toArray() + ['dates_label' => $row->dates->pluck('overtime_date')->map->format('Y-m-d')->join(', '),
            'hours' => number_format($row->requested_minutes / 60, 2), 'type_label' => [1 => 'With overtime pay', 2 => 'Without overtime pay', 3 => 'With CTO'][$row->overtime_type]]);

        return response()->json($rows);
    }

    public function store(Request $request, RequestWorkflow $workflow): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $data = $request->validate(['user_ids' => 'nullable|array|min:1|max:100', 'user_ids.*' => 'integer|distinct|exists:users,id', 'dates' => 'required|array|min:1|max:31', 'dates.*' => 'required|date|distinct',
            'day_type' => 'required|integer|in:1,2,3', 'start_time' => 'required|date_format:H:i', 'requested_minutes' => 'required|integer|min:1|max:1440',
            'overtime_type' => 'required|integer|in:1,2,3', 'purpose' => 'required|string|max:2000', 'override_reason' => 'nullable|string|min:5|max:1000']);
        $employeeIds = $data['user_ids'] ?? [$actor->id];
        if ($employeeIds !== [$actor->id]) {
            abort_unless($actor->hasAbility('requests.override') && filled($data['override_reason'] ?? null), 403);
        }
        $records = DB::transaction(function () use ($request, $data, $employeeIds, $workflow, $actor) {
            $records = [];
            foreach ($employeeIds as $employeeId) {
                $employee = User::whereKey($employeeId)->firstOrFail();
                $conflict = OvertimeRequest::where('user_id', $employeeId)->whereHas('dates', fn ($q) => $q->whereIn('overtime_date', $data['dates']))
                    ->whereHas('statuses', fn ($q) => $q->whereNotIn('status', ['Cancelled', 'Disapproved by DC', 'Disapproved by ED']))->exists();
                if ($conflict) {
                    throw ValidationException::withMessages(['dates' => 'An active overtime request already includes one of these dates for '.$employee->name.'.']);
                }
                $record = OvertimeRequest::create($data + ['user_id' => $employeeId, 'filed_on' => today('Asia/Manila'), 'created_by' => $actor->id,
                    'attachment_file_id' => count($employeeIds) === 1 ? $this->storeAttachment($request, $employee) : null]);
                $record->dates()->createMany(array_map(fn ($date) => ['overtime_date' => $date], $data['dates']));
                $workflow->start('OT', $record, $actor, $employeeId === $actor->id ? null : 'Approved');
                $records[] = $record->id;
            }

            return $records;
        });

        return response()->json(['message' => count($records).' overtime authorization(s) filed.', 'ids' => $records], 201);
    }

    public function transition(Request $request, OvertimeRequest $overtime, RequestWorkflow $workflow): JsonResponse
    {
        $data = $request->validate(['action' => 'required|in:approve_dc,reject_dc,approve_ed,reject_ed,cancel', 'reason' => 'nullable|string|max:1000']);
        $status = $workflow->transition('OT', $overtime, $request->user(), $data['action'], $data['reason'] ?? null);

        return response()->json(['message' => 'Request is now '.$status->status.'.']);
    }

    public function print(Request $request, OvertimeRequest $overtime): View
    {
        $record = $this->visibleRequests(OvertimeRequest::with(['employee.division', 'employee.position', 'dates'])->whereKey($overtime->id), $request, 'OT')->firstOrFail();

        return view('hris.request-print', ['kind' => 'overtime', 'record' => $record, 'status' => $record->current_status]);
    }
}
