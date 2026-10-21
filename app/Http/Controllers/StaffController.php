<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveStaffRequest;
use App\Models\Delegation;
use App\Models\Division;
use App\Models\Position;
use App\Models\User;
use App\Services\EmploymentHistory;
use App\Services\StaffService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        Gate::authorize('staff.manage');

        return view('hris.staff', ['divisions' => Division::orderBy('name')->get(), 'positions' => Position::with('employee:id,position_id')->orderBy('title')->get()]);
    }

    public function data(Request $request): JsonResponse
    {
        Gate::authorize('staff.manage');
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'division_id' => 'nullable|integer|exists:divisions,id',
            'archived' => 'nullable|in:0,1', 'sort' => 'nullable|in:name,email,created_at', 'direction' => 'nullable|in:asc,desc']);
        $query = User::with(['division', 'position'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('atms_id', 'like', "%{$search}%")))
            ->when($filters['division_id'] ?? null, fn ($q, $id) => $q->where('division_id', $id))
            ->when(isset($filters['archived']), fn ($q) => $q->where('is_archived', $filters['archived']));
        $page = max(1, $request->integer('page', 1));
        $last = max(1, (int) ceil((clone $query)->count() / 10));

        return response()->json($query->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->orderBy('id')->paginate(10, ['id', 'name', 'email', 'role', 'employee_type', 'division_id', 'position_id', 'atms_id', 'is_archived', 'termination_date'], 'page', min($page, $last))
            ->through(fn (User $employee) => $employee->toArray() + ['avatar_url' => route('employees.avatar', $employee)]));
    }

    public function show(User $employee): JsonResponse
    {
        Gate::authorize('staff.manage');

        return response()->json($employee->only(['id', 'first_name', 'middle_name', 'last_name', 'extension', 'nickname', 'email', 'role', 'employee_type', 'division_id', 'position_id', 'atms_id', 'date_hired', 'termination_date', 'workweek', 'step_increment', 'birthday', 'sex', 'mobile_no', 'contact_person', 'contact_person_no']));
    }

    public function store(SaveStaffRequest $request, StaffService $service): JsonResponse
    {
        $employee = $service->save($request->user(), null, $request->validated());

        return response()->json(['message' => 'Employee created. A password setup email has been queued.', 'id' => $employee->id], 201);
    }

    public function update(SaveStaffRequest $request, User $employee, StaffService $service): JsonResponse
    {
        $service->save($request->user(), $employee, $request->validated());

        return response()->json(['message' => 'Employee saved.']);
    }

    public function archive(Request $request, User $employee): JsonResponse
    {
        Gate::authorize('staff.manage');
        $data = $request->validate(['is_archived' => 'required|boolean']);
        abort_if($employee->id === $request->user()->id, 422, 'You cannot archive your own account.');
        DB::transaction(function () use ($employee, $request, $data) {
            $employee = User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            if (! $data['is_archived'] && $employee->position_id) {
                $position = Position::whereKey($employee->position_id)->lockForUpdate()->firstOrFail();
                if (User::where('position_id', $position->id)->whereKeyNot($employee->id)->exists()) {
                    throw ValidationException::withMessages(['is_archived' => 'This position is assigned to another employee. Choose a separate position before reactivating.']);
                }
            }
            $before = $employee->attributesToArray();
            $employee->forceFill($data)->save();
            Audit::record('staff.archive-changed', $employee, $request->user()->id, $before);
        });

        return response()->json(['message' => $data['is_archived'] ? 'Employee archived. History is preserved.' : 'Employee reactivated.']);
    }

    public function serviceRecord(Request $request, User $employee, EmploymentHistory $history): JsonResponse
    {
        abort_unless($request->user()->id === $employee->id || $request->user()->hasAbility('staff.manage'), 403);

        return response()->json($history->query($employee)->orderByDesc('after->effective_date')->orderByDesc('id')->paginate(10)
            ->through(fn ($event) => ['id' => $event->id] + ($event->after ?? [])));
    }

    public function printServiceRecord(Request $request, User $employee, EmploymentHistory $history): View
    {
        abort_unless($request->user()->id === $employee->id || $request->user()->hasAbility('staff.manage'), 403);
        $records = $history->query($employee)->orderByDesc('after->effective_date')->orderByDesc('id')->get(['after']);

        return view('hris.service-record-print', compact('employee', 'records'));
    }

    public function organization(Request $request, string $kind): View|JsonResponse
    {
        Gate::authorize('organization.manage');
        abort_unless(in_array($kind, ['divisions', 'positions'], true), 404);
        $class = $kind === 'divisions' ? Division::class : Position::class;
        if ($request->isMethod('get')) {
            if (! $request->expectsJson()) {
                return view('hris.organization', ['kind' => $kind, 'divisions' => Division::orderBy('name')->get()]);
            }

            return response()->json($class::orderBy('id')->paginate(10));
        }
        $id = $request->validate(['id' => 'nullable|integer'])['id'] ?? null;
        $record = $id ? $class::query()->whereKey($id)->firstOrFail() : new $class;
        $data = $request->validate($kind === 'divisions' ? [
            'name' => ['required', 'string', 'max:255', Rule::unique('divisions')->ignore($id)], 'acronym' => 'nullable|string|max:30',
        ] : ['title' => 'required|string|max:255', 'division_id' => 'required|integer|exists:divisions,id',
            'code' => ['nullable', 'string', 'max:255', Rule::unique('positions')->ignore($id)], 'plantilla' => 'required|boolean',
            'classification' => ['required', Rule::in(['Staff', 'Supervisory'])], 'status' => ['required', Rule::in(['Open for Application', 'Filled Up', 'archived'])]]);
        DB::transaction(function () use ($record, $data, $request) {
            $before = $record->attributesToArray();
            if ($record instanceof Position && $record->exists && (int) $record->division_id !== (int) $data['division_id'] && User::where('position_id', $record->id)->exists()) {
                throw ValidationException::withMessages(['division_id' => 'An assigned position cannot move division. Create a new position and transfer employees.']);
            }
            if ($record instanceof Position && $record->exists) {
                Position::whereKey($record->id)->lockForUpdate()->firstOrFail();
                $incumbents = User::where('position_id', $record->id)->where('is_archived', false)
                    ->where(fn ($q) => $q->whereNull('termination_date')->orWhereDate('termination_date', '>', today('Asia/Manila')))->count();
                if ($data['status'] === 'archived' && $incumbents > 0) {
                    throw ValidationException::withMessages(['status' => 'Transfer the active employee before archiving this position.']);
                }
            }
            $record->fill($data)->save();
            Audit::record('organization.saved', $record, $request->user()->id, $before);
        });

        return response()->json(['message' => 'Organization record saved.']);
    }

    public function delegations(Request $request): View|JsonResponse
    {
        Gate::authorize('delegations.manage');
        if ($request->isMethod('get')) {
            return $request->expectsJson() ? response()->json(Delegation::latest('id')->paginate(10))
                : view('hris.delegations', ['divisions' => Division::orderBy('name')->get()]);
        }
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id', 'division_id' => 'required|integer|exists:divisions,id',
            'ability' => ['required', Rule::in(['attendance.division'])], 'period_start' => 'required|date_format:Y-m-d', 'period_end' => 'required|date_format:Y-m-d|after_or_equal:period_start']);
        DB::transaction(function () use ($data, $request) {
            User::whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            if (Delegation::where('user_id', $data['user_id'])->where('division_id', $data['division_id'])->where('ability', $data['ability'])
                ->whereDate('period_start', '<=', $data['period_end'])->whereDate('period_end', '>=', $data['period_start'])->exists()) {
                throw ValidationException::withMessages(['period_start' => 'This delegation overlaps an existing assignment.']);
            }
            $delegation = Delegation::create($data + ['actor_id' => $request->user()->id]);
            Audit::record('delegation.created', $delegation, $request->user()->id);
        });

        return response()->json(['message' => 'Delegation created.'], 201);
    }
}
