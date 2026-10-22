<?php

namespace App\Http\Controllers;

use App\Models\Concern;
use App\Models\PrivateFile;
use App\Models\RequestStatus;
use App\Models\User;
use App\Services\Files\PrivateUpload;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConcernController extends Controller
{
    public function index(Request $request): View
    {
        abort_if($request->user()->role === 'applicant', 403);

        return view('hris.concerns');
    }

    public function data(Request $request): JsonResponse
    {
        $filters = $request->validate(['status' => 'nullable|string|max:40', 'search' => 'nullable|string|max:100']);
        $query = Concern::with(['employee:id,name', 'attachments.file:id,original_name,mime,size', 'statuses.actor:id,name,role'])
            ->addSelect(['current_status' => RequestStatus::select('status')->whereColumn('request_id', 'concerns.id')->where('request_type', 'CONCERN')->latest('id')->limit(1)])
            ->when(! $request->user()->hasAbility('concerns.manage'), fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->whereRaw('(select status from request_statuses where request_type = ? and request_id = concerns.id order by id desc limit 1) = ?', ['CONCERN', $status]))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($scope) => $scope->where('subject', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")));

        return response()->json($query->latest('id')->paginate(10));
    }

    public function store(Request $request, PrivateUpload $files, ?Concern $concern = null): JsonResponse
    {
        $data = $request->validate(['subject' => 'required|string|max:255', 'description' => 'required|string|max:10000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240']);
        if ($concern) {
            abort_unless($concern->user_id === $request->user()->id && $this->status($concern) === 'New', 403);
        }
        $file = $request->hasFile('attachment') ? $files->store($request->file('attachment'), $request->user(), $request->user(), 'concern') : null;
        try {
            $concern = DB::transaction(function () use ($request, $data, $concern, $file) {
                if ($concern) {
                    $before = $concern->attributesToArray();
                    $concern->update(Arr::except($data, 'attachment'));
                    Audit::record('concern.updated', $concern, $request->user()->id, $before);
                    $record = $concern;
                } else {
                    $record = Concern::create(Arr::except($data, 'attachment') + ['user_id' => $request->user()->id]);
                    $this->append($record, 'New', 'created', $request);
                }
                if ($file instanceof PrivateFile) {
                    $record->attachments()->create(['private_file_id' => $file->id, 'side' => 'employee', 'uploaded_by' => $request->user()->id]);
                }

                return $record;
            });
        } catch (\Throwable $exception) {
            $this->discard($file);
            throw $exception;
        }

        return response()->json(['message' => 'Concern saved.', 'id' => $concern->id], $concern->wasRecentlyCreated ? 201 : 200);
    }

    public function transition(Request $request, Concern $concern, PrivateUpload $files): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['acknowledge', 'resolve', 'staff_acknowledge', 'cancel'])],
            'response' => 'nullable|string|max:10000', 'reason' => 'nullable|string|max:2000', 'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240']);
        $actor = $request->user();
        $current = $this->status($concern);
        if ($data['action'] === 'cancel') {
            abort_unless($actor->id === $concern->user_id && $current === 'New', 403);
            $this->append($concern, 'Cancelled', 'cancel', $request, $data['reason'] ?? null);
        } elseif ($data['action'] === 'staff_acknowledge') {
            abort_unless($actor->id === $concern->user_id && $current === 'Resolved' && $concern->staff_acknowledged_at === null, 403);
            $concern->update(['staff_acknowledged_at' => now()]);
            $this->append($concern, 'Staff Acknowledged', 'staff_acknowledge', $request);
        } else {
            abort_unless($actor->hasAbility('concerns.manage'), 403);
            $expected = $data['action'] === 'acknowledge' ? 'New' : 'Acknowledged';
            if ($current !== $expected) {
                throw ValidationException::withMessages(['status' => "This action requires status {$expected}."]);
            }
            if ($data['action'] === 'resolve' && blank($data['response'] ?? null)) {
                throw ValidationException::withMessages(['response' => 'Enter the HR resolution response.']);
            }
            $status = $data['action'] === 'acknowledge' ? 'Acknowledged' : 'Resolved';
            $owner = $concern->employee;
            abort_unless($owner instanceof User, 404);
            $file = $request->hasFile('attachment') ? $files->store($request->file('attachment'), $owner, $actor, 'concern') : null;
            try {
                DB::transaction(function () use ($concern, $actor, $data, $status, $request, $file) {
                    $concern->update(['response' => $data['response'] ?? $concern->response, 'responded_by' => $actor->id, 'resolved_at' => $status === 'Resolved' ? now() : null]);
                    $this->append($concern, $status, $data['action'], $request, $data['reason'] ?? null);
                    if ($file instanceof PrivateFile) {
                        $concern->attachments()->create(['private_file_id' => $file->id, 'side' => 'hr', 'uploaded_by' => $actor->id]);
                    }
                });
            } catch (\Throwable $exception) {
                $this->discard($file);
                throw $exception;
            }
        }

        return response()->json(['message' => 'Concern status updated.']);
    }

    private function status(Concern $concern): ?string
    {
        return RequestStatus::where('request_type', 'CONCERN')->where('request_id', $concern->id)->latest('id')->value('status');
    }

    private function append(Concern $concern, string $status, string $action, Request $request, ?string $reason = null): void
    {
        RequestStatus::create(['request_type' => 'CONCERN', 'request_id' => $concern->id, 'status' => $status,
            'actor_id' => $request->user()->id, 'action' => $action, 'reason' => $reason, 'created_at' => now()]);
    }

    private function discard(?PrivateFile $file): void
    {
        if (! $file instanceof PrivateFile) {
            return;
        }
        Storage::disk('local')->delete($file->path);
        $file->delete();
    }
}
