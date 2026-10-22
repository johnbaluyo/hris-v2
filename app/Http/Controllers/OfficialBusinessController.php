<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesHrisRequests;
use App\Models\OfficialBusiness;
use App\Models\RequestStatus;
use App\Models\User;
use App\Services\Requests\RequestWorkflow;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OfficialBusinessController extends Controller
{
    use HandlesHrisRequests;

    public function index(Request $request): View
    {
        abort_if($request->user()->role === 'applicant', 403);

        return view('hris.official-business');
    }

    public function data(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|string|max:50']);
        $query = $this->visibleRequests(OfficialBusiness::query()->with('employee:id,name,division_id'), $request, 'OB')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($x) => $x->where('destination', 'like', "%{$s}%")->orWhere('purpose', 'like', "%{$s}%")->orWhereHas('employee', fn ($u) => $u->where('name', 'like', "%{$s}%"))))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->whereRaw('(select status from request_statuses where request_type = ? and request_id = official_businesses.id order by id desc limit 1) = ?', ['OB', $s]));
        $page = max(1, min($request->integer('page', 1), max(1, (int) ceil((clone $query)->count() / 10))));
        $rows = $query->latest('business_date')->latest('id')->paginate(10, ['*'], 'page', $page);
        $rows->getCollection()->transform(fn (OfficialBusiness $row) => $row->toArray() + [
            'type_label' => [1 => 'Work-related', 2 => 'Medical', 3 => 'Bereavement'][$row->type],
            'can_edit' => $row->current_status === 'Approved' && ($row->user_id === $request->user()->id || $request->user()->hasAbility('requests.override')),
            'attachment_url' => $row->attachment_file_id ? route('files.download', $row->attachment_file_id) : null,
        ]);

        return response()->json($rows);
    }

    public function store(Request $request, RequestWorkflow $workflow): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $data = $request->validate(['user_id' => 'nullable|integer|exists:users,id', 'business_date' => 'required|date', 'type' => 'required|integer|in:1,2,3',
            'departure_time' => 'nullable|date_format:H:i', 'return_time' => 'nullable|date_format:H:i|after:departure_time', 'origin' => 'nullable|string|max:255',
            'destination' => 'required|string|max:255', 'go_directly' => 'required|boolean', 'return_to_office' => 'required|boolean', 'purpose' => 'required|string|max:2000',
            'attachment' => 'prohibited', 'override_reason' => 'nullable|string|min:5|max:1000']);
        $employee = isset($data['user_id']) ? User::whereKey($data['user_id'])->firstOrFail() : $actor;
        if ($employee->id !== $actor->id) {
            abort_unless($actor->hasAbility('requests.override') && filled($data['override_reason'] ?? null), 403);
        }
        $record = DB::transaction(function () use ($data, $employee, $workflow, $actor) {
            $record = OfficialBusiness::create($data + ['user_id' => $employee->id, 'filed_on' => today('Asia/Manila'), 'created_by' => $actor->id]);
            $workflow->start('OB', $record, $actor, $employee->id === $actor->id ? null : 'Approved');

            return $record;
        });

        return response()->json(['message' => 'Official business request filed.', 'id' => $record->id], 201);
    }

    public function transition(Request $request, OfficialBusiness $officialBusiness, RequestWorkflow $workflow): JsonResponse
    {
        $data = $request->validate(['action' => 'required|in:approve_dc,reject_dc,approve_ed,reject_ed,cancel', 'reason' => 'nullable|string|max:1000']);
        $status = $workflow->transition('OB', $officialBusiness, $request->user(), $data['action'], $data['reason'] ?? null);

        return response()->json(['message' => 'Request is now '.$status->status.'.']);
    }

    public function uploadEvidence(Request $request, OfficialBusiness $officialBusiness, RequestWorkflow $workflow): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        abort_unless($officialBusiness->user_id === $actor->id || $actor->hasAbility('requests.override'), 403);
        abort_unless($workflow->current('OB', $officialBusiness->id)?->status === 'Approved', 409, 'Evidence can be uploaded only after the official business request is approved.');
        $request->validate(['attachment' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240']);
        $employee = User::whereKey($officialBusiness->user_id)->firstOrFail();
        $before = $officialBusiness->attributesToArray();
        $fileId = $this->storeAttachment($request, $employee);
        $officialBusiness->update(['attachment_file_id' => $fileId]);
        Audit::record('official-business.evidence-uploaded', $officialBusiness, $actor->id, $before);

        return response()->json(['message' => 'Official business evidence uploaded.', 'attachment_url' => route('files.download', $fileId)]);
    }

    public function history(Request $request, OfficialBusiness $officialBusiness): JsonResponse
    {
        $this->visibleRequests(OfficialBusiness::whereKey($officialBusiness), $request, 'OB')->firstOrFail();

        return response()->json(RequestStatus::where('request_type', 'OB')->where('request_id', $officialBusiness->id)->latest('id')->paginate(10));
    }
}
