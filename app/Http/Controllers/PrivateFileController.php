<?php

namespace App\Http\Controllers;

use App\Models\PortalItem;
use App\Models\PrivateFile;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateFileController extends Controller
{
    public function avatar(Request $request, User $employee): Response|StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor->id === $employee->id || $actor->hasAbility('staff.manage') || $actor->hasAbility('pds.read'), 403);
        $file = PrivateFile::where('user_id', $employee->id)->where('purpose', 'profile')->latest('id')->first();
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'];
        if (! $request->boolean('fallback') && $file && in_array($file->mime, ['image/jpeg', 'image/png'], true)
            && Storage::disk('local')->exists($file->path)) {
            return Storage::disk('local')->response($file->path, $file->original_name, $headers);
        }
        $initials = htmlspecialchars($employee->initials(), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96"><rect width="96" height="96" rx="48" fill="#dbeafe"/><text x="48" y="51" text-anchor="middle" dominant-baseline="middle" font-family="-apple-system,Helvetica,Arial,sans-serif" font-size="32" font-weight="600" fill="#1e40af">'.$initials.'</text></svg>';

        return response($svg, 200, $headers + ['Content-Type' => 'image/svg+xml', 'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'"]);
    }

    public function storeOwnPhoto(Request $request): JsonResponse
    {
        $request->merge(['purpose' => 'profile']);

        return $this->store($request, $request->user());
    }

    public function index(Request $request, User $employee): JsonResponse
    {
        abort_unless($request->user()->id === $employee->id || $request->user()->hasAbility('staff.manage'), 403);

        return response()->json(PrivateFile::where('user_id', $employee->id)->whereIn('purpose', ['personnel', 'profile', 'signature'])
            ->latest('id')->paginate(10, ['id', 'original_name', 'mime', 'size', 'purpose', 'created_at']));
    }

    public function store(Request $request, User $employee): JsonResponse
    {
        $data = $request->validate(['purpose' => ['required', Rule::in(['personnel', 'profile', 'signature', 'certificate', 'government-id', 'portal'])],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,gif', 'max:10240']]);
        $actor = $request->user();
        $purpose = $data['purpose'];
        abort_unless(match ($purpose) {
            'portal' => $actor->hasAbility('portal.manage'),
            'profile' => $actor->id === $employee->id || $actor->hasAbility('staff.manage'),
            'personnel', 'signature' => $actor->hasAbility('staff.manage'),
            default => ($actor->id === $employee->id && $actor->role !== 'applicant') || $actor->hasAbility('pds.write'),
        }, 403);
        $upload = $request->file('file');
        if (in_array($purpose, ['profile', 'signature'], true)) {
            $request->validate(['file' => ['image', 'mimes:jpg,jpeg,png', 'max:2048', 'dimensions:max_width=4000,max_height=4000']]);
        }
        $path = $upload->store('hris/'.$employee->id, 'local');
        abort_unless(is_string($path), 503, 'File storage is unavailable. Please try again.');
        try {
            $record = DB::transaction(function () use ($employee, $actor, $purpose, $upload, $path) {
                $record = PrivateFile::create(['user_id' => $employee->id, 'actor_id' => $actor->id, 'purpose' => $purpose,
                    'original_name' => mb_substr(basename($upload->getClientOriginalName()), 0, 255), 'path' => $path,
                    'mime' => $upload->getMimeType(), 'size' => $upload->getSize(), 'checksum' => hash_file('sha256', $upload->getRealPath())]);
                Audit::record('file.uploaded', $record, $actor->id);

                return $record;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return response()->json(['message' => 'File uploaded.', 'id' => $record->id, 'original_name' => $record->original_name], 201);
    }

    public function download(Request $request, PrivateFile $file): StreamedResponse
    {
        $actor = $request->user();
        $allowed = $actor->id === $file->user_id || $actor->hasAbility('staff.manage');
        if (in_array($file->purpose, ['certificate', 'government-id'], true)) {
            $allowed = $allowed || $actor->hasAbility('pds.read');
        } elseif ($file->purpose === 'portal') {
            $allowed = $actor->hasAbility('portal.manage') || ($actor->role !== 'applicant' && PortalItem::where('file_id', $file->id)->where('status', 'Active')->exists());
        }
        abort_unless($allowed, 403);
        if (! Storage::disk('local')->exists($file->path)) {
            Audit::record('file.missing', $file, $actor->id);
            abort(404, 'The file is missing. Please contact HR to repair this attachment.');
        }

        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'];
        if ($request->boolean('preview') && in_array($file->mime, ['image/jpeg', 'image/png', 'image/gif'], true)) {
            return Storage::disk('local')->response($file->path, $file->original_name, $headers);
        }

        return Storage::disk('local')->download($file->path, $file->original_name, $headers);
    }
}
