<?php

namespace App\Http\Controllers;

use App\Models\Division;
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
    public function index(Request $request): View
    {
        abort_if($request->user()->role === 'applicant', 403);

        return view('hris.attendance-review', ['divisions' => Division::orderBy('name')->get()]);
    }

    /** @return Builder<User> */
    private function query(Request $request, AttendanceScope $scope): Builder
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m', 'search' => 'nullable|string|max:100', 'division_id' => 'nullable|integer|exists:divisions,id']);
        $date = CarbonImmutable::createFromFormat('!Y-m', $data['month']);
        if ($date === null) {
            abort(422, 'Choose a valid month.');
        }
        $from = $date->startOfMonth()->toDateString();
        $to = $date->endOfMonth()->toDateString();
        $period = fn ($q) => $q->whereBetween('attendance_date', [$from, $to]);

        return $scope->employees($request->user())->select(['users.id', 'users.name', 'users.division_id'])->with('division:id,name')
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

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('Unable to open report stream.');
            }
            fputcsv($output, ['Employee ID', 'Employee', 'Division', 'Recorded days', 'Worked minutes', 'Late minutes', 'Undertime minutes', 'Review days'], ',', '"', '');
            foreach ($query->orderBy('id')->lazy(100) as $employee) {
                // Prevent formula interpretation when opening employee-supplied names in spreadsheets.
                $safe = fn (?string $value) => $value !== null && preg_match('/^[=+@\-\t\r\n]/', $value) ? "'".$value : $value;
                fputcsv($output, [$employee->id, $safe($employee->name), $safe($employee->division?->name), $employee->getAttribute('recorded_days'),
                    $employee->getAttribute('worked_minutes'), $employee->getAttribute('late_minutes'), $employee->getAttribute('undertime_minutes'), $employee->getAttribute('review_days')], ',', '"', '');
            }
            fclose($output);
        }, 'monthly-attendance-'.$request->string('month').'.csv', ['Cache-Control' => 'private, no-store']);
    }
}
