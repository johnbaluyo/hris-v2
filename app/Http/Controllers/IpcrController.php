<?php

namespace App\Http\Controllers;

use App\Models\Ipcr;
use App\Models\IpcrAccomplishment;
use App\Models\IpcrIndicator;
use App\Models\IpcrOutput;
use App\Models\RequestStatus;
use App\Models\User;
use App\Services\Performance\IpcrRatingCalculator;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IpcrController extends Controller
{
    public function index(Request $request): View
    {
        abort_if($request->user()->role === 'applicant', 403);

        return view('hris.ipcr');
    }

    public function data(Request $request): JsonResponse
    {
        $actor = $request->user();
        $query = Ipcr::with(['employee:id,name,division_id,role', 'outputs.indicators.accomplishments'])
            ->addSelect(['current_status' => RequestStatus::select('status')->whereColumn('request_id', 'ipcrs.id')->where('request_type', 'IPCR')->latest('id')->limit(1)]);
        if (! $actor->hasAbility('ipcr.report') && ! $actor->actsAsDirector()) {
            $query->where(function ($scope) use ($actor) {
                $scope->where('user_id', $actor->id);
                if ($actor->actsAsDivisionChiefOf($actor->division_id)) {
                    $scope->orWhereHas('employee', fn ($employees) => $employees->where('division_id', $actor->division_id));
                }
            });
        }
        $rows = $query->latest('period_start')->paginate(10);
        $rows->getCollection()->transform(fn (Ipcr $ipcr) => $ipcr->toArray() + ['can_edit' => $this->isOwnerDraft($actor, $ipcr), 'can_rate' => $this->canReview($actor, $ipcr)]);

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['period_start' => 'required|date_format:Y-m-d', 'period_end' => 'required|date_format:Y-m-d|after_or_equal:period_start']);
        if (Ipcr::where('user_id', $request->user()->id)->whereDate('period_start', '<=', $data['period_end'])->whereDate('period_end', '>=', $data['period_start'])->exists()) {
            throw ValidationException::withMessages(['period_start' => 'This period overlaps an existing IPCR.']);
        }
        $ipcr = DB::transaction(function () use ($request, $data) {
            $record = Ipcr::create($data + ['user_id' => $request->user()->id]);
            RequestStatus::create(['request_type' => 'IPCR', 'request_id' => $record->id, 'status' => 'Draft', 'actor_id' => $request->user()->id, 'action' => 'created', 'created_at' => now()]);

            return $record;
        });

        return response()->json(['message' => 'IPCR draft created.', 'id' => $ipcr->id], 201);
    }

    public function show(Request $request, Ipcr $ipcr): JsonResponse
    {
        $this->authorizeRead($request->user(), $ipcr);

        return response()->json($ipcr->load(['employee:id,name,division_id,role', 'outputs.indicators.accomplishments', 'statuses.actor:id,name,role']));
    }

    public function saveOutput(Request $request, Ipcr $ipcr, ?IpcrOutput $output = null): JsonResponse
    {
        abort_unless($this->isOwnerDraft($request->user(), $ipcr), 403);
        if ($output) {
            abort_unless($output->ipcr_id === $ipcr->id, 404);
        }
        $data = $request->validate(['category' => 'required|integer|between:1,3', 'task' => 'required|string|max:3000']);
        $record = $output ?? new IpcrOutput(['ipcr_id' => $ipcr->id]);
        $record->fill($data)->save();

        return response()->json(['message' => 'Output saved.', 'id' => $record->id], $output ? 200 : 201);
    }

    public function saveIndicator(Request $request, Ipcr $ipcr, IpcrOutput $output, ?IpcrIndicator $indicator = null): JsonResponse
    {
        abort_unless($this->isOwnerDraft($request->user(), $ipcr) && $output->ipcr_id === $ipcr->id, 403);
        if ($indicator) {
            abort_unless($indicator->ipcr_output_id === $output->id, 404);
        }
        $data = $request->validate(['description' => 'required|string|max:3000']);
        $record = $indicator ?? new IpcrIndicator(['ipcr_output_id' => $output->id]);
        $record->fill($data)->save();

        return response()->json(['message' => 'Success indicator saved.', 'id' => $record->id], $indicator ? 200 : 201);
    }

    public function saveAccomplishment(Request $request, Ipcr $ipcr, IpcrIndicator $indicator, ?IpcrAccomplishment $accomplishment = null): JsonResponse
    {
        abort_unless($this->isOwnerDraft($request->user(), $ipcr) && $indicator->output()->where('ipcr_id', $ipcr->id)->exists(), 403);
        if ($accomplishment) {
            abort_unless($accomplishment->ipcr_indicator_id === $indicator->id, 404);
        }
        $data = $request->validate(['accomplishment' => 'required|string|max:5000']);
        $record = $accomplishment ?? new IpcrAccomplishment(['ipcr_indicator_id' => $indicator->id]);
        $record->fill($data)->save();

        return response()->json(['message' => 'Actual accomplishment saved.', 'id' => $record->id], $accomplishment ? 200 : 201);
    }

    public function rate(Request $request, Ipcr $ipcr, IpcrAccomplishment $accomplishment, IpcrRatingCalculator $calculator): JsonResponse
    {
        abort_unless($this->canReview($request->user(), $ipcr) && $accomplishment->indicator()->whereHas('output', fn ($query) => $query->where('ipcr_id', $ipcr->id))->exists(), 403);
        $data = $request->validate(['quality' => 'nullable|integer|between:1,5', 'efficiency' => 'nullable|integer|between:1,5', 'timeliness' => 'nullable|integer|between:1,5']);
        $accomplishment->update($data + ['rated_by' => $request->user()->id]);
        $rating = $calculator->calculate($ipcr->fresh());
        $ipcr->update(['overall_rating' => $rating['overall']]);
        Audit::record('ipcr.rated', $accomplishment, $request->user()->id);

        return response()->json(['message' => 'Rating saved.', 'rating' => $rating]);
    }

    public function submit(Request $request, Ipcr $ipcr): JsonResponse
    {
        abort_unless($this->isOwnerDraft($request->user(), $ipcr), 403);
        abort_if(! $ipcr->outputs()->whereHas('indicators', fn ($query) => $query->whereHas('accomplishments'))->exists(), 422, 'Add an output, indicator and accomplishment before submitting.');
        DB::transaction(function () use ($request, $ipcr) {
            $employee = $request->user()->loadMissing(['position', 'division']);
            $ipcr->update(['employee_snapshot' => ['name' => $employee->name, 'position' => $employee->position?->title, 'division' => $employee->division?->name]]);
            RequestStatus::create(['request_type' => 'IPCR', 'request_id' => $ipcr->id, 'status' => 'For Review', 'actor_id' => $employee->id, 'action' => 'submit', 'created_at' => now()]);
        });

        return response()->json(['message' => 'IPCR submitted for review.']);
    }

    public function destroy(Request $request, Ipcr $ipcr): JsonResponse
    {
        abort_unless($this->isOwnerDraft($request->user(), $ipcr), 403);
        DB::transaction(function () use ($ipcr) {
            RequestStatus::where('request_type', 'IPCR')->where('request_id', $ipcr->id)->delete();
            $ipcr->delete();
        });

        return response()->json(['message' => 'IPCR draft deleted.']);
    }

    public function destroyOutput(Request $request, Ipcr $ipcr, IpcrOutput $output): JsonResponse
    {
        abort_unless($this->isOwnerDraft($request->user(), $ipcr) && $output->ipcr_id === $ipcr->id, 403);
        $output->delete();

        return response()->json(['message' => 'Output deleted.']);
    }

    public function destroyIndicator(Request $request, Ipcr $ipcr, IpcrOutput $output, IpcrIndicator $indicator): JsonResponse
    {
        abort_unless($this->isOwnerDraft($request->user(), $ipcr) && $output->ipcr_id === $ipcr->id && $indicator->ipcr_output_id === $output->id, 403);
        $indicator->delete();

        return response()->json(['message' => 'Success indicator deleted.']);
    }

    public function destroyAccomplishment(Request $request, Ipcr $ipcr, IpcrIndicator $indicator, IpcrAccomplishment $accomplishment): JsonResponse
    {
        abort_unless($this->isOwnerDraft($request->user(), $ipcr) && $indicator->output()->where('ipcr_id', $ipcr->id)->exists() && $accomplishment->ipcr_indicator_id === $indicator->id, 403);
        $accomplishment->delete();

        return response()->json(['message' => 'Accomplishment deleted.']);
    }

    public function print(Request $request, Ipcr $ipcr, IpcrRatingCalculator $calculator): View
    {
        $this->authorizeRead($request->user(), $ipcr);
        $ipcr->load(['employee.division', 'outputs.indicators.accomplishments']);
        $rating = $calculator->calculate($ipcr);
        $supervisor = User::where('division_id', $ipcr->employee->division_id)->where('role', 'division_chief')->where('is_archived', false)->first();
        $director = User::where('role', 'executive_director')->where('is_archived', false)->first();

        return view('hris.ipcr-print', compact('ipcr', 'rating', 'supervisor', 'director'));
    }

    private function currentStatus(Ipcr $ipcr): ?string
    {
        return RequestStatus::where('request_type', 'IPCR')->where('request_id', $ipcr->id)->latest('id')->value('status');
    }

    private function isOwnerDraft(User $actor, Ipcr $ipcr): bool
    {
        return $actor->id === $ipcr->user_id && $this->currentStatus($ipcr) === 'Draft';
    }

    private function canReview(User $actor, Ipcr $ipcr): bool
    {
        if ($actor->id === $ipcr->user_id || $this->currentStatus($ipcr) !== 'For Review') {
            return false;
        }
        $employee = $ipcr->relationLoaded('employee') ? $ipcr->employee : User::find($ipcr->user_id);

        return $employee instanceof User && ($employee->role === 'division_chief' ? $actor->actsAsDirector() : $actor->actsAsDivisionChiefOf($employee->division_id));
    }

    private function authorizeRead(User $actor, Ipcr $ipcr): void
    {
        $employee = User::findOrFail($ipcr->user_id);
        abort_unless($actor->id === $employee->id || $actor->hasAbility('ipcr.report') || $actor->actsAsDirector() || $actor->actsAsDivisionChiefOf($employee->division_id), 403);
    }
}
