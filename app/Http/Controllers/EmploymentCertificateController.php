<?php

namespace App\Http\Controllers;

use App\Models\EmploymentCertificate;
use App\Models\User;
use App\Services\Certificates\CertificateAmounts;
use App\Services\Files\PrivateUpload;
use App\Services\Notifications\NotificationPublisher;
use App\Support\EmployeeSnapshot;
use App\Support\WorkflowHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EmploymentCertificateController extends Controller
{
    public function index(Request $request): View
    {
        abort_if($request->user()->role === 'applicant', 403);

        return view('hris.certificates', ['amountFields' => CertificateAmounts::FIELDS,
            'formerEmployees' => $request->user()->hasAbility('certificates.manage') ? User::where('role', '!=', 'applicant')
                ->whereDate('termination_date', '<=', today('Asia/Manila'))->whereRaw('LOWER(COALESCE(employee_type, ?)) != ?', ['', 'intern'])->orderBy('name')->get(['id', 'name']) : collect()]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_if($request->user()->role === 'applicant', 403);
        $filters = $request->validate(['search' => 'nullable|string|max:100']);
        $page = EmploymentCertificate::with('files', 'history.actor:id,name,role')->when(! $request->user()->hasAbility('certificates.manage'), fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('purpose', 'like', "%{$search}%"))->latest('id')->paginate(10);

        return response()->json($page);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => 'required|in:current,manual', 'purpose' => 'required|string|max:3000', 'employee_id' => 'nullable|integer|exists:users,id']);
        $actor = $request->user();
        abort_if($actor->role === 'applicant', 403);
        $owner = $data['type'] === 'manual' ? User::whereKey($data['employee_id'] ?? 0)->firstOrFail() : $actor;
        if ($data['type'] === 'manual') {
            abort_unless($actor->hasAbility('certificates.manage'), 403);
            abort_unless($owner->role !== 'applicant' && strcasecmp($owner->employee_type ?? '', 'Intern') !== 0
                && $owner->termination_date !== null && $owner->termination_date->lte(today('Asia/Manila')), 422, 'Select a former non-Intern employee.');
        } else {
            abort_if(! empty($data['employee_id']) && (int) $data['employee_id'] !== $actor->id, 403);
        }
        $certificate = DB::transaction(function () use ($owner, $data, $actor) {
            User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            abort_if(EmploymentCertificate::where('user_id', $owner->id)->where('type', $data['type'])
                ->whereNotIn('status', ['Completed', 'Rejected'])->exists(), 422, 'An unfinished certificate request already exists.');
            $certificate = EmploymentCertificate::create(['user_id' => $owner->id, 'type' => $data['type'], 'purpose' => $data['purpose'],
                'verification_token' => Str::random(64), 'snapshot' => EmployeeSnapshot::for($owner)]);
            WorkflowHistory::record('COE', $certificate, $actor, 'request', 'For Acknowledgment');

            return $certificate;
        });

        return response()->json(['message' => 'Certificate requested.', 'id' => $certificate->id], 201);
    }

    public function transition(Request $request, EmploymentCertificate $certificate, CertificateAmounts $calculator, NotificationPublisher $notices): JsonResponse
    {
        abort_unless($request->user()->hasAbility('certificates.manage'), 403);
        $rules = ['action' => 'required|in:receive,acknowledge,approve,reject,generate', 'reason' => 'nullable|string|max:3000'];
        foreach (CertificateAmounts::FIELDS as $field) {
            $rules[$field] = 'sometimes|numeric|min:0|max:999999999.99|decimal:0,2';
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($data, $request, $certificate, $calculator, $notices) {
            $certificate = EmploymentCertificate::whereKey($certificate->id)->lockForUpdate()->firstOrFail();
            $action = $data['action'];
            $expected = ['receive' => 'For Acknowledgment', 'acknowledge' => 'Received', 'approve' => 'Acknowledged', 'generate' => 'Approved'];
            $next = ['receive' => 'Received', 'acknowledge' => 'Acknowledged', 'approve' => 'Approved', 'reject' => 'Rejected', 'generate' => 'COE Generated'];
            if ($action === 'reject') {
                abort_unless(in_array($certificate->status, ['For Acknowledgment', 'Received', 'Acknowledged'], true) && filled($data['reason'] ?? null), 422, 'Reject only a pending request and provide a reason.');
            } else {
                abort_unless($certificate->status === $expected[$action], 422, 'This action is not available in the current state.');
            }
            $updates = ['status' => $next[$action]];
            if ($action === 'acknowledge') {
                $updates['acknowledged_by'] = $request->user()->id;
            }
            if ($action === 'generate') {
                abort_unless(in_array($request->user()->id, config('records.certificate_signer_ids'), true), 422, 'An approved certificate signer must be configured before issuance.');
                $updates += ['issued_by' => $request->user()->id, 'issued_at' => now(),
                    'snapshot' => array_merge($certificate->snapshot, ['signer' => EmployeeSnapshot::for($request->user()),
                        'verification_url' => url('/verification/'.$certificate->verification_token)])];
                if ($certificate->type === 'current') {
                    $updates += $calculator->calculate(array_intersect_key($data, array_flip(CertificateAmounts::FIELDS)));
                }
            }
            $certificate->update($updates);
            WorkflowHistory::record('COE', $certificate, $request->user(), $action, $next[$action], $data['reason'] ?? null);
            $notices->afterCommit($certificate->employee, 'coe:'.$certificate->id.':'.$next[$action], 'Certificate request updated', 'Your certificate request is '.$next[$action].'.', '/cert');
        });

        return response()->json(['message' => 'Certificate action recorded.']);
    }

    public function complete(Request $request, EmploymentCertificate $certificate, PrivateUpload $uploads): JsonResponse
    {
        abort_unless($request->user()->hasAbility('certificates.manage'), 403);
        $request->validate(['file' => 'required|file|mimes:pdf|max:10240']);
        abort_unless($certificate->status === 'COE Generated', 422);
        $file = $uploads->store($request->file('file'), $certificate->employee, $request->user(), 'employment-certificate');
        try {
            DB::transaction(function () use ($certificate, $file, $request) {
                $certificate = EmploymentCertificate::whereKey($certificate->id)->lockForUpdate()->firstOrFail();
                abort_unless($certificate->status === 'COE Generated', 422);
                $certificate->files()->create(['private_file_id' => $file->id]);
                $certificate->update(['status' => 'Completed', 'completed_at' => now()]);
                WorkflowHistory::record('COE', $certificate, $request->user(), 'complete', 'Completed');
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($file->path);
            $file->delete();
            throw $exception;
        }

        return response()->json(['message' => 'Completed certificate stored privately.']);
    }

    public function print(Request $request, EmploymentCertificate $certificate): View
    {
        abort_unless($certificate->user_id === $request->user()->id || $request->user()->hasAbility('certificates.manage'), 403);

        return view('hris.certificate-print', compact('certificate'));
    }

    public function verify(string $token): View
    {
        abort_unless(config('records.certificate_public_verification'), 404);
        $certificate = EmploymentCertificate::where('verification_token', $token)->where('status', 'Completed')->whereNotNull('issued_at')->firstOrFail();

        return view('hris.certificate-verification', ['reference' => $certificate->verification_token, 'type' => $certificate->type, 'issuedAt' => $certificate->issued_at]);
    }
}
