<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\DivisionLearningPlan;
use App\Models\IdpItem;
use App\Models\PdsEntry;
use App\Models\Position;
use App\Models\TrainingRequirement;
use App\Models\TrainingRequirementLink;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LearningDevelopmentController extends Controller
{
    public function index(Request $request): View
    {
        abort_if($request->user()->role === 'applicant', 403);

        return view('hris.learning-development', ['positions' => Position::where('status', '!=', 'archived')->orderBy('title')->get(), 'divisions' => Division::orderBy('name')->get()]);
    }

    public function requirements(Request $request): JsonResponse
    {
        $data = $request->validate(['position_id' => 'nullable|integer|exists:positions,id', 'search' => 'nullable|string|max:100']);
        $query = TrainingRequirement::query()->whereNull('archived_at')->with('position:id,title')
            ->when($data['position_id'] ?? null, fn ($query, $id) => $query->where('position_id', $id))
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('title', 'like', "%{$search}%"));

        return response()->json($query->orderBy('category')->orderBy('title')->paginate(10));
    }

    public function saveRequirement(Request $request, ?TrainingRequirement $requirement = null): JsonResponse
    {
        abort_unless($request->user()->hasAbility('lnd.manage'), 403);
        $data = $request->validate(['position_id' => 'required|integer|exists:positions,id', 'category' => 'required|integer|between:1,3',
            'title' => ['required', 'string', 'max:255', Rule::unique('training_requirements')->where(fn ($query) => $query->where('position_id', $request->integer('position_id'))->where('category', $request->integer('category')))->ignore($requirement?->id)]]);
        $record = $requirement ?? new TrainingRequirement(['created_by' => $request->user()->id]);
        $before = $record->attributesToArray();
        $record->fill($data)->save();
        Audit::record('training-requirement.saved', $record, $request->user()->id, $before);

        return response()->json(['message' => 'Training requirement saved.', 'id' => $record->id], $requirement ? 200 : 201);
    }

    public function archiveRequirement(Request $request, TrainingRequirement $requirement): JsonResponse
    {
        abort_unless($request->user()->hasAbility('lnd.manage'), 403);
        $requirement->update(['archived_at' => now()]);
        Audit::record('training-requirement.archived', $requirement, $request->user()->id);

        return response()->json(['message' => 'Requirement archived; historical links were preserved.']);
    }

    public function copyRequirements(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAbility('lnd.manage'), 403);
        $data = $request->validate(['source_position_id' => 'required|different:target_position_id|exists:positions,id', 'target_position_id' => 'required|exists:positions,id']);
        $created = 0;
        DB::transaction(function () use ($data, $request, &$created) {
            foreach (TrainingRequirement::where('position_id', $data['source_position_id'])->whereNull('archived_at')->get() as $source) {
                $record = TrainingRequirement::firstOrCreate(['position_id' => $data['target_position_id'], 'category' => $source->category, 'title' => $source->title], ['created_by' => $request->user()->id]);
                $created += $record->wasRecentlyCreated ? 1 : 0;
            }
        });

        return response()->json(['message' => "{$created} requirement(s) copied; duplicates skipped."]);
    }

    public function plans(Request $request): JsonResponse
    {
        $actor = $request->user();
        $query = DivisionLearningPlan::with(['division:id,name', 'lines']);
        if (! $actor->hasAbility('lnd.manage') && ! $actor->actsAsDirector()) {
            $query->whereIn('division_id', $this->managedDivisions($actor));
        }

        return response()->json($query->latest('plan_year')->paginate(10));
    }

    public function createPlan(Request $request): JsonResponse
    {
        $data = $request->validate(['division_id' => 'required|integer|exists:divisions,id', 'plan_year' => 'required|integer|between:2000,2100']);
        $this->authorizeDivision($request, (int) $data['division_id']);
        $plan = DivisionLearningPlan::firstOrCreate(['division_id' => $data['division_id'], 'plan_year' => $data['plan_year']], ['created_by' => $request->user()->id]);
        if (! $plan->wasRecentlyCreated) {
            throw ValidationException::withMessages(['plan_year' => 'A learning plan already exists for this division and year.']);
        }

        return response()->json(['message' => 'Division learning plan created.', 'id' => $plan->id], 201);
    }

    public function eligibleItems(Request $request, DivisionLearningPlan $plan): JsonResponse
    {
        $this->authorizeDivision($request, $plan->division_id);
        $items = IdpItem::with('plan.employee:id,name,division_id')->whereHas('plan', fn ($query) => $query->where('plan_year', $plan->plan_year)->whereNotNull('approved_at')
            ->whereHas('employee', fn ($employees) => $employees->where('division_id', $plan->division_id)))->get();

        return response()->json($items);
    }

    public function saveLines(Request $request, DivisionLearningPlan $plan): JsonResponse
    {
        $this->authorizeDivision($request, $plan->division_id);
        $data = $request->validate(['lines' => 'required|array|max:500', 'lines.*.idp_item_id' => 'required|integer|distinct|exists:idp_items,id',
            'lines.*.budget' => 'required|numeric|min:0|max:999999999999.99', 'lines.*.is_free' => 'required|boolean']);
        DB::transaction(function () use ($data, $plan) {
            $ids = [];
            foreach ($data['lines'] as $line) {
                $item = IdpItem::query()->with('plan.employee')->findOrFail((int) $line['idp_item_id']);
                if ($item->plan->approved_at === null || $item->plan->plan_year !== $plan->plan_year || $item->plan->employee->division_id !== $plan->division_id) {
                    throw ValidationException::withMessages(['lines' => 'Every item must come from an approved IDP in this division and plan year.']);
                }
                $record = $plan->lines()->updateOrCreate(['idp_item_id' => $item->id], ['user_id' => $item->plan->user_id,
                    'budget' => $line['is_free'] ? 0 : $line['budget'], 'is_free' => $line['is_free'],
                    'snapshot' => ['employee' => $item->plan->employee->name, 'proposal' => $item->proposal, 'title' => $item->title]]);
                $ids[] = $record->id;
            }
            $plan->lines()->whereNotIn('id', $ids)->delete();
            $plan->update(['total_budget' => $plan->lines()->sum('budget'), 'staff_count' => $plan->lines()->distinct()->count('user_id')]);
        });

        return response()->json(['message' => 'Division learning-plan budget saved.']);
    }

    public function matrix(Request $request, Position $position): JsonResponse
    {
        $this->authorizePositionRead($request, $position);
        $employee = $position->employee()->where('is_archived', false)->first();
        $requirements = TrainingRequirement::where('position_id', $position->id)->whereNull('archived_at')->orderBy('category')->orderBy('title')->get();
        $training = $employee instanceof User
            ? PdsEntry::where('user_id', $employee->id)->where('section', 'learning')->latest('id')->get()
            : collect();
        $links = TrainingRequirementLink::with('training')->whereIn('pds_entry_id', $training->pluck('id'))->get()->keyBy('pds_entry_id');
        $fulfilled = [];
        $unfulfilled = [];
        foreach ($requirements as $requirement) {
            $matches = $links->where('training_requirement_id', $requirement->id)->pluck('training')->filter()->values();
            $row = $requirement->toArray() + ['training' => $matches];
            if ($matches->isEmpty()) {
                $unfulfilled[] = $row;
            } else {
                $fulfilled[] = $row;
            }
        }
        $validIds = $requirements->pluck('id');
        $uncategorized = $training->filter(function (PdsEntry $entry) use ($links, $validIds) {
            $link = $links->get($entry->id);

            return ! $link instanceof TrainingRequirementLink || ! $validIds->contains($link->training_requirement_id);
        })->values();

        return response()->json(['position' => $position, 'employee' => $employee, 'fulfilled' => $fulfilled,
            'unfulfilled' => $unfulfilled, 'uncategorized' => $uncategorized]);
    }

    public function linkTraining(Request $request, TrainingRequirement $requirement, PdsEntry $training): JsonResponse
    {
        abort_unless($request->user()->hasAbility('lnd.manage'), 403);
        abort_unless($training->section === 'learning', 422, 'Only learning and development entries can fulfill a requirement.');
        $employeePosition = User::whereKey($training->user_id)->value('position_id');
        abort_unless((int) $employeePosition === $requirement->position_id, 422, 'The training employee is not the incumbent of this requirement position.');
        TrainingRequirementLink::updateOrCreate(['pds_entry_id' => $training->id], ['training_requirement_id' => $requirement->id, 'actor_id' => $request->user()->id]);

        return response()->json(['message' => 'Completed training linked to the requirement.']);
    }

    public function unlinkTraining(Request $request, PdsEntry $training): JsonResponse
    {
        abort_unless($request->user()->hasAbility('lnd.manage'), 403);
        TrainingRequirementLink::where('pds_entry_id', $training->id)->delete();

        return response()->json(['message' => 'Training moved to uncategorized history.']);
    }

    public function printPlan(Request $request, DivisionLearningPlan $plan): View
    {
        $this->authorizeDivision($request, $plan->division_id);
        $plan->load(['division', 'lines']);

        return view('hris.learning-plan-print', compact('plan'));
    }

    /** @return array<int, int> */
    private function managedDivisions(User $actor): array
    {
        return Division::query()->pluck('id')->filter(fn ($id) => $actor->actsAsDivisionChiefOf((int) $id))->map(fn ($id) => (int) $id)->all();
    }

    private function authorizeDivision(Request $request, int $divisionId): void
    {
        abort_unless($request->user()->hasAbility('lnd.manage') || $request->user()->actsAsDirector() || $request->user()->actsAsDivisionChiefOf($divisionId), 403);
    }

    private function authorizePositionRead(Request $request, Position $position): void
    {
        $actor = $request->user();
        abort_unless($actor->position_id === $position->id || $actor->hasAbility('lnd.manage') || $actor->actsAsDirector()
            || $actor->actsAsDivisionChiefOf($position->division_id), 403);
    }
}
