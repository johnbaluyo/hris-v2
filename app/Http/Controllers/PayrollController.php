<?php

namespace App\Http\Controllers;

use App\Models\LoanCategory;
use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\SalaryGrade;
use App\Models\User;
use App\Services\Payroll\PayrollCalculator;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    private function authorizePayroll(Request $request): void
    {
        abort_unless($request->user()->hasAbility('payroll.manage'), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizePayroll($request);

        return view('hris.payroll');
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorizePayroll($request);
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|in:Draft,Finalized,Voided']);
        $query = Payroll::query()->withCount('lines')->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($x) => $x->where('payroll_number', 'like', "%{$s}%")->orWhere('fund_cluster', 'like', "%{$s}%")))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s));
        $page = max(1, min($request->integer('page', 1), max(1, (int) ceil((clone $query)->count() / 10))));

        return response()->json($query->latest('period_end')->latest('id')->paginate(10, ['*'], 'page', $page));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePayroll($request);
        $data = $request->validate(['type' => ['required', Rule::in(['Regular', 'CSW', 'Mid-year Bonus', 'Year-end Bonus'])], 'payroll_number' => 'required|string|max:100|unique:payrolls,payroll_number',
            'fund_cluster' => 'required|string|max:100', 'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start']);
        $payroll = Payroll::create($data + ['created_by' => $request->user()->id, 'status' => 'Draft']);
        Audit::record('payroll.created', $payroll, $request->user()->id);

        return response()->json(['message' => 'Payroll draft created.', 'id' => $payroll->id], 201);
    }

    public function lines(Request $request, Payroll $payroll): JsonResponse
    {
        $this->authorizePayroll($request);
        $search = $request->validate(['search' => 'nullable|string|max:100'])['search'] ?? null;
        $query = PayrollLine::where('payroll_id', $payroll->id)->when($search, fn ($q, $s) => $q->where('employee_snapshot->name', 'like', "%{$s}%"));
        $page = max(1, min($request->integer('page', 1), max(1, (int) ceil((clone $query)->count() / 10))));

        return response()->json($query->orderBy('id')->paginate(10, ['*'], 'page', $page));
    }

    public function saveLine(Request $request, Payroll $payroll, PayrollCalculator $calculator): JsonResponse
    {
        $this->authorizePayroll($request);
        abort_if($payroll->status !== 'Draft', 409, 'Finalized payroll is immutable.');
        $data = $request->validate(['user_id' => 'required|exists:users,id', 'pera' => 'nullable|numeric|min:0|max:99999999', 'rata' => 'nullable|numeric|min:0|max:99999999',
            'other_compensation' => 'nullable|numeric|min:0|max:99999999', 'statutory_deductions' => 'nullable|numeric|min:0|max:99999999', 'other_deductions' => 'nullable|numeric|min:0|max:99999999',
            'late_minutes' => 'nullable|integer|min:0|max:100000', 'undertime_minutes' => 'nullable|integer|min:0|max:100000', 'absence_days' => 'nullable|numeric|min:0|max:366']);
        $employee = User::with(['position', 'division'])->whereKey($data['user_id'])->firstOrFail();
        abort_if(! $employee->isEligible(), 422, 'Only active employees may be added to a draft.');
        $salary = SalaryGrade::where('grade', $employee->position?->salary_grade)->where('step', $employee->step_increment ?? 1)
            ->whereDate('effective_from', '<=', $payroll->period_end)->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $payroll->period_start))
            ->latest('effective_from')->value('monthly_salary');
        if ($salary === null) {
            throw ValidationException::withMessages(['user_id' => 'No effective salary grade and step exists for this employee and period.']);
        }
        $input = array_merge(['basic_salary' => (float) $salary, 'pera' => 0, 'rata' => 0, 'other_compensation' => 0, 'statutory_deductions' => 0, 'other_deductions' => 0], $data);
        $totals = $calculator->regular($input);
        if ((float) $totals['net'] < 0) {
            throw ValidationException::withMessages(['deductions' => 'Deductions cannot produce a negative net amount.']);
        }
        $line = PayrollLine::updateOrCreate(['payroll_id' => $payroll->id, 'user_id' => $employee->id], [
            'employee_snapshot' => ['name' => $employee->name, 'employee_type' => $employee->employee_type, 'position' => $employee->position?->title, 'division' => $employee->division?->name, 'salary_grade' => $employee->position?->salary_grade, 'step' => $employee->step_increment ?? 1],
            'basic_salary' => $salary, 'pera' => $input['pera'], 'rata' => $input['rata'], 'other_compensation' => $input['other_compensation'],
            'statutory_deductions' => $input['statutory_deductions'], 'other_deductions' => $input['other_deductions'], 'late_minutes' => $data['late_minutes'] ?? 0,
            'undertime_minutes' => $data['undertime_minutes'] ?? 0, 'absence_days' => $data['absence_days'] ?? 0, 'gross' => $totals['gross'],
            'total_deductions' => $totals['total_deductions'], 'net' => $totals['net'], 'calculation_inputs' => ['weekly' => $totals['weekly'], 'version' => $payroll->calculation_version] + $input,
        ]);
        Audit::record('payroll.line-saved', $line, $request->user()->id);

        return response()->json(['message' => 'Payroll line calculated and saved.', 'id' => $line->id, 'totals' => $totals]);
    }

    public function deleteLine(Request $request, Payroll $payroll, PayrollLine $line): JsonResponse
    {
        $this->authorizePayroll($request);
        abort_unless($line->payroll_id === $payroll->id, 404);
        abort_if($payroll->status !== 'Draft', 409, 'Finalized payroll is immutable.');
        DB::transaction(function () use ($line, $request) {
            Audit::record('payroll.line-removed', $line, $request->user()->id);
            $line->delete();
        });

        return response()->json(['message' => 'Draft line removed.']);
    }

    public function finalize(Request $request, Payroll $payroll): JsonResponse
    {
        $this->authorizePayroll($request);
        DB::transaction(function () use ($payroll, $request) {
            $locked = Payroll::lockForUpdate()->findOrFail($payroll->id);
            abort_if($locked->status !== 'Draft', 409, 'Only a draft can be finalized.');
            if (! $locked->lines()->exists()) {
                throw ValidationException::withMessages(['payroll' => 'Add at least one employee before finalizing.']);
            }
            $locked->update(['status' => 'Finalized', 'finalized_by' => $request->user()->id, 'finalized_at' => now()]);
            Audit::record('payroll.finalized', $locked, $request->user()->id);
        });

        return response()->json(['message' => 'Payroll finalized. Historical amounts are now locked.']);
    }

    public function export(Request $request, Payroll $payroll): StreamedResponse
    {
        $this->authorizePayroll($request);
        abort_unless($payroll->status === 'Finalized', 409, 'Finalize payroll before exporting.');

        return response()->streamDownload(function () use ($payroll) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Employee', 'Basic salary', 'PERA', 'RATA', 'Other compensation', 'Deductions', 'Net']);
            $payroll->lines()->orderBy('id')->get()->each(function (PayrollLine $line) use ($out) {
                $snapshot = $line->employee_snapshot;
                fputcsv($out, [$snapshot['name'] ?? '', $line->basic_salary, $line->pera, $line->rata, $line->other_compensation, $line->total_deductions, $line->net]);
            });
            fclose($out);
        }, 'payroll-'.$payroll->payroll_number.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function saveLoanCategory(Request $request): JsonResponse
    {
        $this->authorizePayroll($request);
        $data = $request->validate(['provider' => 'required|in:GSIS,HDMF,LBP', 'name' => 'required|string|max:100']);
        $category = LoanCategory::firstOrCreate($data);

        return response()->json(['message' => 'Loan category available.', 'id' => $category->id], $category->wasRecentlyCreated ? 201 : 200);
    }
}
