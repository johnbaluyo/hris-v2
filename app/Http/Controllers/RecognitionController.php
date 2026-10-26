<?php

namespace App\Http\Controllers;

use App\Models\DailyAttendance;
use App\Models\LeaveDate;
use App\Models\LeaveType;
use App\Models\RecognitionEvent;
use App\Models\RecognitionNominee;
use App\Models\RecognitionVote;
use App\Models\User;
use App\Support\Audit;
use App\Support\EmployeeSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RecognitionController extends Controller
{
    public function index(Request $request): View
    {
        abort_if($request->user()->role === 'applicant', 403);

        return view('hris.recognition', ['leaveTypes' => LeaveType::orderBy('name')->get()]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_if($request->user()->role === 'applicant', 403);
        $filters = $request->validate(['search' => 'nullable|string|max:100']);
        $page = RecognitionEvent::with('nominees')->when(! $request->user()->hasAbility('recognition.manage'), fn ($q) => $q->whereNull('archived_at'))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))->latest('id')->paginate(10);
        $page->getCollection()->transform(function (RecognitionEvent $event) use ($request) {
            $showCounts = $request->user()->hasAbility('recognition.manage') || ($event->closes_at !== null && $event->closes_at->lte(now()));
            if ($showCounts) {
                $event->load('nominees');
                $event->nominees->loadCount('votes');
            }
            $event->setAttribute('can_vote', $this->canVote($event, $request->user()) && ! RecognitionVote::where('recognition_event_id', $event->id)->where('user_id', $request->user()->id)->exists());
            $event->setAttribute('results_state', $event->published_at ? 'Confirmed winners' : 'Provisional');

            return $event;
        });

        return response()->json($page);
    }

    public function save(Request $request, ?RecognitionEvent $event = null): JsonResponse
    {
        abort_unless($request->user()->hasAbility('recognition.manage'), 403);
        $data = $request->validate(['name' => 'required|string|max:255', 'type' => 'required|in:voting,attendance',
            'coverage_start' => 'required|date_format:Y-m-d', 'coverage_end' => 'required|date_format:Y-m-d|after_or_equal:coverage_start',
            'recognition_date' => 'required|date_format:Y-m-d|after_or_equal:coverage_end',
            'opens_at' => 'nullable|required_if:type,voting|date', 'closes_at' => 'nullable|required_if:type,voting|date|after:opens_at',
            'allow_self_vote' => 'required|boolean', 'voter_types' => 'required|array|min:1',
            'voter_types.*' => ['string', 'distinct', Rule::in(['Permanent', 'Contractual Regular', 'CSW'])],
            'nominee_ids' => 'required|array|min:1|max:500', 'nominee_ids.*' => 'integer|distinct|exists:users,id']);
        $event = DB::transaction(function () use ($data, $request, $event) {
            if ($event) {
                $event = RecognitionEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
                abort_if($event->published_at || $event->archived_at || RecognitionVote::where('recognition_event_id', $event->id)->exists()
                    || ($event->opens_at !== null && $event->opens_at->lte(now())), 422, 'An opened, voted, published or archived event is immutable.');
                $event->update(Arr::except($data, 'nominee_ids'));
            } else {
                $event = RecognitionEvent::create(Arr::except($data, 'nominee_ids'));
            }
            $nominees = User::whereIn('id', $data['nominee_ids'])->get();
            foreach ($nominees as $nominee) {
                abort_unless($nominee->isEligible() && $nominee->role !== 'applicant' && in_array($nominee->employee_type, ['Permanent', 'Contractual Regular', 'CSW'], true), 422, 'Nominees must be active eligible employees.');
            }
            $event->nominees()->whereNotIn('user_id', $data['nominee_ids'])->delete();
            foreach ($nominees as $nominee) {
                $event->nominees()->updateOrCreate(['user_id' => $nominee->id], ['snapshot' => EmployeeSnapshot::for($nominee)]);
            }
            Audit::record('recognition.saved', $event, $request->user()->id);

            return $event;
        });

        return response()->json(['message' => 'Recognition event saved. Voting allows one immutable ballot per voter.', 'id' => $event->id]);
    }

    private function canVote(RecognitionEvent $event, User $user): bool
    {
        return $user->role !== 'applicant' && $user->isEligible() && in_array($user->employee_type, $event->voter_types, true)
            && $event->type === 'voting' && $event->archived_at === null && $event->published_at === null
            && $event->opens_at !== null && $event->closes_at !== null && $event->opens_at->lte(now()) && $event->closes_at->gt(now());
    }

    public function vote(Request $request, RecognitionEvent $event): JsonResponse
    {
        $data = $request->validate(['nominee_id' => 'required|integer', 'user_id' => 'prohibited']);
        DB::transaction(function () use ($data, $request, $event) {
            $event = RecognitionEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->canVote($event, $request->user()), 403, 'Voting is closed or you are not eligible.');
            $nominee = RecognitionNominee::where('recognition_event_id', $event->id)->whereKey($data['nominee_id'])->firstOrFail();
            abort_if(! $event->allow_self_vote && $nominee->user_id === $request->user()->id, 422, 'Self voting is disabled for this event.');
            $existing = RecognitionVote::where('recognition_event_id', $event->id)->where('user_id', $request->user()->id)->first();
            if ($existing) {
                abort_unless($existing->recognition_nominee_id === $nominee->id, 422, 'Your ballot is already final.');

                return;
            }
            RecognitionVote::create(['recognition_event_id' => $event->id, 'recognition_nominee_id' => $nominee->id, 'user_id' => $request->user()->id, 'created_at' => now()]);
        });

        return response()->json(['message' => 'Your ballot was recorded.']);
    }

    public function publish(Request $request, RecognitionEvent $event): JsonResponse
    {
        abort_unless($request->user()->hasAbility('recognition.manage'), 403);
        $data = $request->validate(['winner_ids' => 'required|array|min:1', 'winner_ids.*' => 'integer|distinct']);
        DB::transaction(function () use ($data, $request, $event) {
            $event = RecognitionEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_if($event->archived_at || $event->published_at || ($event->type === 'voting' && $event->closes_at->gt(now())), 422, 'Only a closed, unpublished event can have confirmed winners.');
            abort_unless($event->nominees()->whereIn('id', $data['winner_ids'])->count() === count($data['winner_ids']), 422, 'Every winner must be a nominee in this event.');
            $event->nominees()->whereIn('id', $data['winner_ids'])->update(['winner' => true]);
            $event->update(['published_at' => now(), 'published_by' => $request->user()->id]);
            Audit::record('recognition.winners.confirmed', $event, $request->user()->id);
        });

        return response()->json(['message' => 'Winners confirmed separately from provisional rankings.']);
    }

    public function archive(Request $request, RecognitionEvent $event): JsonResponse
    {
        abort_unless($request->user()->hasAbility('recognition.manage'), 403);
        $event->update(['archived_at' => now()]);
        Audit::record('recognition.archived', $event, $request->user()->id);

        return response()->json(['message' => 'Event archived; ballots and recognition history are retained.']);
    }

    public function attendance(Request $request): View
    {
        abort_unless($request->user()->hasAbility('recognition.manage'), 403);
        $data = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'leave_type_ids' => 'sometimes|array', 'leave_type_ids.*' => 'integer|distinct|exists:leave_types,id', 'include_recent_hires' => 'required|boolean']);
        $data['leave_type_ids'] ??= [];
        abort_if(CarbonImmutable::parse($data['from'])->diffInDays($data['to']) > 366, 422, 'Choose at most one year.');
        $rows = [];
        $users = User::whereIn('employee_type', ['Permanent', 'Contractual Regular'])->where('role', '!=', 'executive_director')
            ->whereNotNull('date_hired')->whereDate('date_hired', '<=', $data['include_recent_hires'] ? $data['to'] : $data['from'])
            ->where(fn ($q) => $q->whereNull('termination_date')->orWhereDate('termination_date', '>=', $data['from']))->get();
        foreach ($users as $user) {
            $attendance = DailyAttendance::where('user_id', $user->id)->whereBetween('attendance_date', [$data['from'], $data['to']])->get();
            $leaveDays = LeaveDate::whereBetween('leave_date', [$data['from'], $data['to']])->whereHas('request', fn ($q) => $q->where('user_id', $user->id)
                ->whereIn('leave_type_id', $data['leave_type_ids'])->whereRaw("(select status from request_statuses where request_type = 'LEAVE' and request_id = leave_requests.id order by id desc limit 1) = 'Approved'"))->sum('day_fraction');
            $leaveByType = fn (array $types) => (float) LeaveDate::whereBetween('leave_date', [$data['from'], $data['to']])->whereHas('request', fn ($q) => $q->where('user_id', $user->id)
                ->whereIn('leave_type_id', $types)->whereRaw("(select status from request_statuses where request_type = 'LEAVE' and request_id = leave_requests.id order by id desc limit 1) = 'Approved'"))->sum('day_fraction');
            $rows[] = ['employee' => EmployeeSnapshot::for($user), 'leave_days' => (float) $leaveDays,
                'vl' => $leaveByType([1, 3]), 'sl' => $leaveByType([2]), 'other_leaves' => $leaveByType([4, 5]),
                'late_minutes' => (int) $attendance->sum('late_minutes'), 'undertime_minutes' => (int) $attendance->sum('undertime_minutes'),
                'penalty_minutes' => (int) $attendance->sum('late_minutes') + (int) $attendance->sum('undertime_minutes'),
                'review_days' => $attendance->where('needs_review', true)->count(), 'recorded_days' => $attendance->count()];
        }
        usort($rows, fn ($a, $b) => [$a['leave_days'], $a['penalty_minutes'], $a['employee']['id']] <=> [$b['leave_days'], $b['penalty_minutes'], $b['employee']['id']]);

        return view('hris.recognition-attendance', compact('rows', 'data'));
    }
}
