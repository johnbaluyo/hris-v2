<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesHrisRequests;
use App\Models\CocEntry;
use App\Models\CtoDate;
use App\Models\CtoRequest;
use App\Models\User;
use App\Services\Credits\CocLedger;
use App\Services\Requests\RequestWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CtoController extends Controller
{
    use HandlesHrisRequests;

    public function index(Request $request, CocLedger $ledger): View
    {
        abort_if($request->user()->role === 'applicant', 403);

        return view('hris.cto', ['balance' => $ledger->balance($request->user()->id)]);
    }

    public function data(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|string|max:50']);
        $query = $this->visibleRequests(CtoRequest::query()->with(['employee:id,name,division_id', 'dates']), $request, 'CTO')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->whereHas('employee', fn ($u) => $u->where('name', 'like', "%{$s}%")))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->whereRaw('(select status from request_statuses where request_type = ? and request_id = cto_requests.id order by id desc limit 1) = ?', ['CTO', $s]));
        $page = max(1, min($request->integer('page', 1), max(1, (int) ceil((clone $query)->count() / 10))));
        $rows = $query->latest('filed_on')->latest('id')->paginate(10, ['*'], 'page', $page);
        $rows->getCollection()->transform(fn (CtoRequest $row) => $row->toArray() + ['hours' => sprintf('%d:%02d', intdiv($row->total_minutes, 60), $row->total_minutes % 60),
            'dates_label' => $row->dates->map(fn (CtoDate $date) => CarbonImmutable::parse($date->cto_date)->toDateString().' '.$date->portion)->join(', ')]);

        return response()->json($rows);
    }

    public function store(Request $request, RequestWorkflow $workflow, CocLedger $ledger): JsonResponse
    {
        $data = $request->validate(['dates' => 'required|array|min:1|max:31', 'dates.*.date' => 'required|date|distinct', 'dates.*.portion' => 'required|in:WHOLE,AM,PM']);
        $employee = $request->user();
        $workweek = $employee->workweek ?? 5;
        $rows = [];
        foreach ($data['dates'] as $item) {
            $weekday = (int) date('N', strtotime($item['date']));
            if (($workweek === 4 && $weekday >= 5) || ($workweek !== 4 && $weekday >= 6)) {
                throw ValidationException::withMessages(['dates' => $item['date'].' is not a scheduled workday.']);
            }
            $minutes = ($workweek === 4 ? 600 : 480) * ($item['portion'] === 'WHOLE' ? 1 : .5);
            $rows[] = ['cto_date' => $item['date'], 'portion' => $item['portion'], 'minutes' => (int) $minutes];
        }
        $total = array_sum(array_column($rows, 'minutes'));
        if ($ledger->balance($employee->id) < $total) {
            throw ValidationException::withMessages(['balance' => 'Available COC credits do not cover this request.']);
        }
        $conflict = CtoRequest::where('user_id', $employee->id)->whereHas('dates', fn ($q) => $q->whereIn('cto_date', array_column($rows, 'cto_date')))
            ->whereHas('statuses', fn ($q) => $q->whereNotIn('status', ['Cancelled', 'Disapproved by DC', 'Disapproved by ED']))->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['dates' => 'An active CTO already includes one of these dates.']);
        }
        $record = DB::transaction(function () use ($employee, $total, $rows, $workflow, $ledger) {
            $record = CtoRequest::create(['user_id' => $employee->id, 'filed_on' => today('Asia/Manila'), 'total_minutes' => $total,
                'balance_snapshot_minutes' => $ledger->balance($employee->id), 'created_by' => $employee->id]);
            $record->dates()->createMany($rows);
            $workflow->start('CTO', $record, $employee);

            return $record;
        });

        return response()->json(['message' => 'CTO request filed.', 'id' => $record->id], 201);
    }

    public function transition(Request $request, CtoRequest $cto, RequestWorkflow $workflow, CocLedger $ledger): JsonResponse
    {
        $data = $request->validate(['action' => 'required|in:certify,approve_dc,reject_dc,approve_ed,reject_ed,cancel', 'reason' => 'nullable|string|max:1000']);
        $status = DB::transaction(function () use ($request, $cto, $workflow, $ledger, $data) {
            $before = $workflow->current('CTO', $cto->id, true)?->status;
            $status = $workflow->transition('CTO', $cto, $request->user(), $data['action'], $data['reason'] ?? null);
            if ($status->status === 'Approved') {
                $ledger->consume($cto, $request->user());
            }
            if ($status->status === 'Cancelled' && $before === 'Approved') {
                $ledger->reverse($cto, $request->user());
            }

            return $status;
        });

        return response()->json(['message' => 'Request is now '.$status->status.'.']);
    }

    public function ledger(Request $request, User $employee, CocLedger $ledger): JsonResponse
    {
        abort_unless($request->user()->id === $employee->id || $request->user()->hasAbility('coc.manage'), 403);
        $rows = CocEntry::where('user_id', $employee->id)->latest('id')->paginate(10);

        return response()->json($rows->toArray() + ['balance_minutes' => $ledger->balance($employee->id)]);
    }

    public function adjust(Request $request, User $employee, CocLedger $ledger): JsonResponse
    {
        $data = $request->validate(['direction' => 'required|in:IN,OUT', 'minutes' => 'required|integer|min:1|max:100000', 'reason' => 'required|string|min:5|max:1000', 'idempotency_key' => 'required|string|max:100|unique:coc_entries,idempotency_key']);
        $entry = $ledger->adjust($employee, $data['minutes'], $data['direction'], $data['reason'], $request->user(), $data['idempotency_key']);

        return response()->json(['message' => 'COC adjustment recorded.', 'id' => $entry->id], 201);
    }

    public function print(Request $request, CtoRequest $cto, CocLedger $ledger): View
    {
        $record = $this->visibleRequests(CtoRequest::with(['employee.division', 'employee.position', 'dates'])->whereKey($cto->id), $request, 'CTO')->firstOrFail();

        return view('hris.request-print', [
            'kind' => 'cto',
            'record' => $record,
            'status' => $record->current_status,
            'balance' => $ledger->balance($record->user_id),
        ]);
    }
}
