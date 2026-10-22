<?php

namespace App\Http\Controllers;

use App\Models\PersonnelDocument;
use App\Models\RequestStatus;
use App\Services\Files\PrivateUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PersonnelDocumentController extends Controller
{
    private const TYPES = ['IPCR (1st Sem)', 'IPCR (2nd Sem)', 'SALN', 'Medical OB Form', 'Training Needs Assessment Form', 'Training Effectiveness Evaluation', 'OTHERS'];

    public function index(Request $request): View
    {
        abort_if($request->user()->role === 'applicant', 403);

        return view('hris.personnel-documents', ['types' => self::TYPES]);
    }

    public function data(Request $request): JsonResponse
    {
        $filters = $request->validate(['status' => 'nullable|string|max:40', 'document_year' => 'nullable|integer|between:2000,2100']);
        $query = PersonnelDocument::with(['employee:id,name', 'versions.file:id,original_name,mime,size', 'statuses.actor:id,name,role'])
            ->addSelect(['current_status' => RequestStatus::select('status')->whereColumn('request_id', 'personnel_documents.id')->where('request_type', 'UPLOADS')->latest('id')->limit(1)])
            ->when(! $request->user()->hasAbility('documents.manage'), fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($filters['document_year'] ?? null, fn ($query, $year) => $query->where('document_year', $year))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->whereRaw('(select status from request_statuses where request_type = ? and request_id = personnel_documents.id order by id desc limit 1) = ?', ['UPLOADS', $status]));

        return response()->json($query->latest('id')->paginate(10));
    }

    public function store(Request $request, PrivateUpload $files): JsonResponse
    {
        $data = $request->validate(['document_type' => ['required', Rule::in(self::TYPES)], 'document_year' => 'required|integer|between:2000,2100',
            'period' => 'required|in:annual,first_semester,second_semester,one_time', 'remarks' => 'nullable|string|max:2000',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240']);
        $existing = PersonnelDocument::where('user_id', $request->user()->id)->where('document_type', $data['document_type'])
            ->where('document_year', $data['document_year'])->where('period', $data['period'])->first();
        if ($existing && $this->status($existing) !== 'Returned') {
            throw ValidationException::withMessages(['document_type' => 'A submission already exists for this type, year and period.']);
        }
        $file = $files->store($request->file('file'), $request->user(), $request->user(), 'personnel-document');
        try {
            $document = DB::transaction(function () use ($existing, $data, $file, $request) {
                $document = $existing ?? PersonnelDocument::create(Arr::except($data, 'file') + ['user_id' => $request->user()->id]);
                if ($existing) {
                    $document->update(['remarks' => $data['remarks'] ?? null]);
                }
                $document->versions()->create(['private_file_id' => $file->id, 'uploaded_by' => $request->user()->id, 'created_at' => now()]);
                RequestStatus::create(['request_type' => 'UPLOADS', 'request_id' => $document->id, 'status' => 'Submitted',
                    'actor_id' => $request->user()->id, 'action' => $existing ? 'resubmit' : 'submit', 'created_at' => now()]);

                return $document;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($file->path);
            $file->delete();
            throw $exception;
        }

        return response()->json(['message' => $existing ? 'Corrected document resubmitted.' : 'Personnel document submitted.', 'id' => $document->id], $existing ? 200 : 201);
    }

    public function transition(Request $request, PersonnelDocument $document): JsonResponse
    {
        $data = $request->validate(['action' => 'required|in:acknowledge,return,cancel', 'reason' => 'nullable|string|max:2000']);
        $current = $this->status($document);
        if ($data['action'] === 'cancel') {
            abort_unless($request->user()->id === $document->user_id && $current === 'Submitted', 403);
            $next = 'Cancelled';
        } else {
            abort_unless($request->user()->hasAbility('documents.manage') && $current === 'Submitted', 403);
            if ($data['action'] === 'return' && blank($data['reason'] ?? null)) {
                throw ValidationException::withMessages(['reason' => 'A return reason is required.']);
            }
            $next = $data['action'] === 'return' ? 'Returned' : 'Acknowledged';
        }
        RequestStatus::create(['request_type' => 'UPLOADS', 'request_id' => $document->id, 'status' => $next,
            'actor_id' => $request->user()->id, 'action' => $data['action'], 'reason' => $data['reason'] ?? null, 'created_at' => now()]);

        return response()->json(['message' => "Document is now {$next}."]);
    }

    private function status(PersonnelDocument $document): ?string
    {
        return RequestStatus::where('request_type', 'UPLOADS')->where('request_id', $document->id)->latest('id')->value('status');
    }
}
