<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\SalaryGrade;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReferenceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasAbility('references.manage'), 403);

        return view('hris.reference-settings');
    }

    public function salaries(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAbility('references.manage'), 403);
        $filters = $request->validate(['grade' => 'nullable|integer|between:1,99']);

        return response()->json(SalaryGrade::query()->when($filters['grade'] ?? null, fn ($query, $grade) => $query->where('grade', $grade))
            ->orderByDesc('effective_from')->orderBy('grade')->orderBy('step')->paginate(10));
    }

    public function saveSalary(Request $request, ?SalaryGrade $salary = null): JsonResponse
    {
        abort_unless($request->user()->hasAbility('references.manage'), 403);
        $data = $request->validate([
            'grade' => 'required|integer|between:1,99', 'step' => 'required|integer|between:1,8',
            'monthly_salary' => 'required|numeric|min:0|max:999999999999.99', 'effective_from' => 'required|date_format:Y-m-d',
            'effective_to' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from',
        ]);
        $overlap = SalaryGrade::where('grade', $data['grade'])->where('step', $data['step'])->whereKeyNot($salary?->id)
            ->whereDate('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $data['effective_from']))->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['effective_from' => 'This effective period overlaps another value for the same grade and step.']);
        }
        $record = $salary ?? new SalaryGrade;
        $before = $record->exists ? $record->attributesToArray() : null;
        $record->fill($data)->save();
        Audit::record('salary-reference.saved', $record, $request->user()->id, $before);

        return response()->json(['message' => 'Salary reference saved.', 'id' => $record->id], $salary ? 200 : 201);
    }

    public function archiveSalary(Request $request, SalaryGrade $salary): JsonResponse
    {
        abort_unless($request->user()->hasAbility('references.manage'), 403);
        $end = max(CarbonImmutable::parse($salary->effective_from)->format('Y-m-d'), today('Asia/Manila')->format('Y-m-d'));
        $before = $salary->attributesToArray();
        $salary->update(['effective_to' => $end]);
        Audit::record('salary-reference.archived', $salary, $request->user()->id, $before);

        return response()->json(['message' => 'Salary reference ended; historical use was preserved.']);
    }

    public function holidays(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAbility('references.manage'), 403);
        $filters = $request->validate(['year' => 'nullable|integer|between:2000,2100', 'type' => 'nullable|string|max:40']);
        $query = Holiday::with('employees:id,name')->whereNull('archived_at')
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->whereYear('holiday_date', $year))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type));

        return response()->json($query->orderByDesc('holiday_date')->paginate(10));
    }

    public function saveHoliday(Request $request, ?Holiday $holiday = null): JsonResponse
    {
        abort_unless($request->user()->hasAbility('references.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('holidays')->where(fn ($query) => $query->whereDate('holiday_date', $request->input('holiday_date')))->ignore($holiday?->id)],
            'holiday_date' => 'required|date_format:Y-m-d', 'type' => ['required', Rule::in(['Regular', 'Special Non-Working', 'Work Suspension', 'Special Order'])],
            'agency_wide' => 'required|boolean', 'suspension_time' => 'nullable|date_format:H:i|required_if:type,Work Suspension',
            'source' => 'required|string|max:255', 'employee_ids' => 'nullable|array|max:1000', 'employee_ids.*' => 'integer|distinct|exists:users,id',
        ]);
        if (! $data['agency_wide'] && empty($data['employee_ids'])) {
            throw ValidationException::withMessages(['employee_ids' => 'Select at least one employee when the entry is not agency-wide.']);
        }
        if ($data['type'] !== 'Work Suspension') {
            $data['suspension_time'] = null;
        }
        $record = $holiday ?? new Holiday;
        $before = $record->exists ? $record->attributesToArray() : null;
        $record->fill(Arr::except($data, 'employee_ids') + ['archived_at' => null])->save();
        $record->employees()->sync($data['agency_wide'] ? [] : $data['employee_ids']);
        Audit::record('holiday-reference.saved', $record, $request->user()->id, $before);

        return response()->json(['message' => 'Holiday or attendance reference saved.', 'id' => $record->id], $holiday ? 200 : 201);
    }

    public function archiveHoliday(Request $request, Holiday $holiday): JsonResponse
    {
        abort_unless($request->user()->hasAbility('references.manage'), 403);
        $before = $holiday->attributesToArray();
        $holiday->update(['archived_at' => now()]);
        Audit::record('holiday-reference.archived', $holiday, $request->user()->id, $before);

        return response()->json(['message' => 'Reference archived; historical records were preserved.']);
    }
}
