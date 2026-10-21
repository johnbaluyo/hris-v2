<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\PortalItem;
use App\Models\PrivateFile;
use App\Models\RawPunch;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        if ($user->role === 'applicant') {
            return view('hris.applicant');
        }
        $today = today('Asia/Manila')->toDateString();
        $items = PortalItem::where('status', 'Active')->where(fn ($q) => $q->where('kind', '!=', 'wellness')->orWhere(fn ($q) => $q->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)))
            ->latest('id')->get()->groupBy('kind');
        $recent = RawPunch::where('user_id', $user->id)->where('punched_at', '>=', today('Asia/Manila')->subDays(2))
            ->orderBy('punched_at')->get()->groupBy(fn ($punch) => $punch->punched_at->toDateString())->map(fn ($punches) => $punches->first());
        $wfh = CalendarEvent::where('event_type', 'Work From Home')->whereDate('starts_at', '<=', $today)->whereDate('ends_at', '>=', $today)->exists();
        $birthdays = User::where('is_archived', false)->whereIn('employee_type', array_diff(config('hris.employee_types'), ['Applicant']))
            ->whereNull('termination_date')->whereMonth('birthday', today('Asia/Manila')->month)->whereDay('birthday', today('Asia/Manila')->day)
            ->orderBy('name')->get(['id', 'name']);

        return view('hris.dashboard', compact('items', 'recent', 'wfh', 'birthdays'));
    }

    public function manage(): View
    {
        Gate::authorize('portal.manage');

        return view('hris.portal');
    }

    public function data(Request $request): JsonResponse
    {
        Gate::authorize('portal.manage');
        $data = $request->validate(['kind' => ['nullable', Rule::in(['application', 'link', 'wellness', 'announcement'])], 'search' => 'nullable|string|max:100']);
        $query = PortalItem::when($data['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', "%{$search}%"));
        $page = min(max(1, $request->integer('page', 1)), max(1, (int) ceil((clone $query)->count() / 10)));

        return response()->json($query->latest('id')->paginate(10, ['*'], 'page', $page));
    }

    public function save(Request $request, ?PortalItem $item = null): JsonResponse
    {
        Gate::authorize('portal.manage');
        $data = $request->validate(['kind' => ['required', Rule::in(['application', 'link', 'wellness', 'announcement'])],
            'title' => 'required|string|max:255', 'description' => 'nullable|string|max:5000',
            'url' => 'nullable|required_if:kind,application,link|url:http,https|max:2048',
            'status' => 'required|in:Active,Inactive', 'starts_on' => 'nullable|required_if:kind,wellness|date_format:Y-m-d',
            'ends_on' => 'nullable|required_if:kind,wellness|date_format:Y-m-d|after_or_equal:starts_on', 'file_id' => 'nullable|integer|exists:private_files,id']);
        if (! empty($data['url'])) {
            abort_if(parse_url($data['url'], PHP_URL_USER) !== null || parse_url($data['url'], PHP_URL_PASS) !== null, 422, 'URLs must not contain credentials.');
        }
        if (! empty($data['file_id'])) {
            $file = PrivateFile::where('purpose', 'portal')->whereKey($data['file_id'])->firstOrFail();
            abort_if($data['kind'] === 'application' && ! in_array($file->mime, ['image/jpeg', 'image/png', 'image/gif'], true), 422, 'Application logos must be JPG, PNG or GIF images.');
        }
        DB::transaction(function () use ($request, $item, $data) {
            $item ??= new PortalItem;
            $before = $item->attributesToArray();
            $item->fill($data + ['author_id' => $request->user()->id])->save();
            Audit::record('portal.saved', $item, $request->user()->id, $before);
        });

        return response()->json(['message' => 'Portal content saved.']);
    }

    public function destroy(Request $request, PortalItem $item): JsonResponse
    {
        Gate::authorize('portal.manage');
        DB::transaction(function () use ($request, $item) {
            Audit::record('portal.removed', $item, $request->user()->id, $item->attributesToArray());
            $item->delete();
        });

        return response()->json(['message' => 'Portal content removed.']);
    }
}
