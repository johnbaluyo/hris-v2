<?php

namespace App\Http\Controllers;

use App\Jobs\SendEventMail;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        abort_if($request->user()->role === 'applicant', 403);

        return view('hris.calendar');
    }

    /** @return Builder<CalendarEvent> */
    private function visible(User $user): Builder
    {
        abort_if($user->role === 'applicant', 403);

        return CalendarEvent::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhereHas('participants', fn ($q) => $q->where('users.id', $user->id))
                ->orWhereNotIn('event_type', ['My Calendar', 'Executive Director Activities']);
            if ($user->hasAbility('calendar.manage') || $user->role === 'executive_director') {
                $q->orWhere('event_type', 'Executive Director Activities');
            }
        });
    }

    /** @return Builder<CalendarEvent> */
    private function filtered(Request $request): Builder
    {
        $filters = $request->validate(['calendar' => 'nullable|in:personal,office,ed', 'search' => 'nullable|string|max:100',
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $query = $this->visible($request->user());
        match ($filters['calendar'] ?? 'personal') {
            'office' => $query->whereNotIn('event_type', ['My Calendar', 'Work From Home', 'Executive Director Activities']),
            'ed' => $query->where('event_type', 'Executive Director Activities'),
            default => $query->where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhereHas('participants', fn ($q) => $q->where('users.id', $request->user()->id))),
        };
        $query->when($filters['search'] ?? null, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('ends_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('starts_at', '<=', $d));

        return $query;
    }

    public function data(Request $request): JsonResponse
    {
        $query = $this->filtered($request)->with('participants:id,name');
        $page = min(max(1, $request->integer('page', 1)), max(1, (int) ceil((clone $query)->count() / 10)));
        $events = $query->orderBy('starts_at')->orderBy('id')->paginate(10, ['*'], 'page', $page);
        $events->getCollection()->transform(function (CalendarEvent $event) use ($request) {
            return $event->toArray() + ['feed_start' => $event->all_day ? $event->starts_at->toDateString() : $event->starts_at->format('Y-m-d\TH:i:sP'),
                'feed_end' => $event->all_day ? $event->ends_at->addDay()->toDateString() : $event->ends_at->format('Y-m-d\TH:i:sP'),
                'can_edit' => Gate::forUser($request->user())->allows('update', $event)];
        });

        return response()->json($events);
    }

    public function feed(Request $request): JsonResponse
    {
        $range = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        abort_if(CarbonImmutable::parse($range['from'])->diffInDays(CarbonImmutable::parse($range['to'])) > 93, 422, 'Choose a calendar range of at most 93 days.');
        $events = $this->filtered($request)->orderBy('starts_at')->orderBy('id')->get();

        return response()->json($events->map(fn (CalendarEvent $event) => [
            'id' => $event->id, 'title' => $event->title, 'allDay' => $event->all_day,
            'start' => $event->all_day ? $event->starts_at->toDateString() : $event->starts_at->format('Y-m-d\TH:i:sP'),
            'end' => $event->all_day ? $event->ends_at->addDay()->toDateString() : $event->ends_at->format('Y-m-d\TH:i:sP'),
            'extendedProps' => ['calendar' => $event->event_type, 'can_edit' => Gate::forUser($request->user())->allows('update', $event)],
        ]));
    }

    public function show(CalendarEvent $event): JsonResponse
    {
        Gate::authorize('view', $event);

        return response()->json($event->load('participants:id,name'));
    }

    public function save(Request $request, ?CalendarEvent $event = null): JsonResponse
    {
        abort_if($request->user()->role === 'applicant', 403);
        if ($event) {
            Gate::authorize('update', $event);
        }
        $data = $request->validate(['event_type' => 'required|in:My Calendar,Office,Executive Director Activities,Work From Home',
            'title' => 'required|string|max:255', 'description' => 'nullable|string|max:3000', 'all_day' => 'required|boolean',
            'starts_at' => 'required|date', 'ends_at' => 'required|date|after_or_equal:starts_at',
            'participants' => 'sometimes|array|max:500', 'participants.*' => 'integer|distinct|exists:users,id']);
        if ($data['event_type'] !== 'My Calendar') {
            Gate::authorize('calendar.manage');
        }
        if ($event?->event_type !== null && $event->event_type !== $data['event_type']) {
            abort(422, 'Keep the calendar type when editing. Create a new event to change its audience.');
        }
        if ($data['event_type'] === 'Work From Home') {
            $data['all_day'] = true;
        }
        if (User::whereIn('id', $data['participants'] ?? [])->where(fn ($q) => $q->where('role', 'applicant')->orWhere('is_archived', true)->orWhereDate('termination_date', '<=', today('Asia/Manila')))->exists()) {
            abort(422, 'Participants must be current, active employees.');
        }
        foreach (['starts_at', 'ends_at'] as $key) {
            $date = CarbonImmutable::parse($data[$key], 'Asia/Manila')->setTimezone('Asia/Manila');
            $data[$key] = ($data['all_day'] ? $date->startOfDay() : $date)->format('Y-m-d H:i:s');
        }
        DB::transaction(function () use ($event, $data, $request) {
            $event ??= new CalendarEvent;
            $before = $event->attributesToArray();
            $participants = $data['participants'] ?? [];
            unset($data['participants']);
            $event->fill($data + ['user_id' => $event->exists ? $event->user_id : $request->user()->id])->save();
            $event->participants()->sync($participants);
            Audit::record('calendar.saved', $event, $request->user()->id, $before);
            if ($event->event_type !== 'My Calendar') {
                sort($participants);
                $revision = hash('sha256', json_encode([$data, $participants], JSON_THROW_ON_ERROR));
                foreach ($participants as $userId) {
                    DB::table('event_mail_deliveries')->insertOrIgnore(['calendar_event_id' => $event->id, 'user_id' => $userId, 'revision' => $revision]);
                    SendEventMail::dispatch($event->id, $userId, $revision)->afterCommit();
                }
            }
        });

        return response()->json(['message' => 'Calendar event saved.']);
    }

    public function destroy(Request $request, CalendarEvent $event): JsonResponse
    {
        Gate::authorize('delete', $event);
        DB::transaction(function () use ($event, $request) {
            Audit::record('calendar.removed', $event, $request->user()->id, $event->attributesToArray());
            $event->delete();
        });

        return response()->json(['message' => 'Event removed.']);
    }
}
