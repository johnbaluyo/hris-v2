<?php

namespace App\Http\Controllers;

use App\Models\IdpPlan;
use App\Models\TrainingRequirement;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IdpController extends Controller
{
    public function index(Request $request): View
    {
        abort_if($request->user()->role === 'applicant', 403);

        return view('hris.idp', ['requirements' => TrainingRequirement::where('position_id', $request->user()->position_id)->whereNull('archived_at')->orderBy('category')->orderBy('title')->get()]);
    }

    public function data(Request $request): JsonResponse
    {
        $actor = $request->user();
        $query = IdpPlan::with(['employee:id,name,division_id,role', 'items.requirement:id,title'])
            ->when(! $actor->hasAbility('lnd.manage') && ! $actor->actsAsDirector(), function ($query) use ($actor) {
                $query->where(function ($scope) use ($actor) {
                    $scope->where('user_id', $actor->id);
                    if ($actor->actsAsDivisionChiefOf($actor->division_id)) {
                        $scope->orWhereHas('employee', fn ($employees) => $employees->where('division_id', $actor->division_id)->whereNotNull('division_id'));
                    }
                });
            });
        if (! $actor->hasAbility('lnd.manage') && ! $actor->actsAsDirector()) {
            $query->where(fn ($scope) => $scope->where('user_id', $actor->id)->orWhereNotNull('submitted_at'));
        }
        $rows = $query->latest('plan_year')->latest('id')->paginate(10);
        $rows->getCollection()->transform(fn (IdpPlan $plan) => $plan->toArray() + [
            'status' => $plan->approved_at ? 'Approved' : ($plan->submitted_at ? 'For Supervisor Approval' : 'Draft'),
            'can_edit' => $plan->user_id === $actor->id && $plan->submitted_at === null,
            'can_approve' => $this->canApprove($actor, $plan),
        ]);

        return response()->json($rows);
    }

    public function save(Request $request, ?IdpPlan $idp = null): JsonResponse
    {
        $actor = $request->user();
        if ($idp) {
            abort_unless($idp->user_id === $actor->id && $idp->submitted_at === null, 403);
        }
        $data = $request->validate([
            'plan_year' => ['required', 'integer', 'between:2000,2100', Rule::unique('idp_plans')->where('user_id', $actor->id)->ignore($idp?->id)],
            'coverage_years' => 'required|array|min:1|max:3', 'coverage_years.*' => 'required|integer|between:2000,2102|distinct',
            'purpose' => 'required|integer|between:1,4', 'other_purpose' => 'nullable|string|max:1000|required_if:purpose,4',
            'items' => 'required|array|min:1|max:50', 'items.*.id' => 'nullable|integer|distinct', 'items.*.training_requirement_id' => 'nullable|integer|exists:training_requirements,id',
            'items.*.proposal' => 'required|string|max:500', 'items.*.title' => 'required|string|max:255',
        ]);
        foreach ($data['items'] as $item) {
            if (isset($item['training_requirement_id']) && ! TrainingRequirement::whereKey($item['training_requirement_id'])->where('position_id', $actor->position_id)->whereNull('archived_at')->exists()) {
                throw ValidationException::withMessages(['items' => 'A selected requirement does not belong to your current position.']);
            }
        }
        $plan = DB::transaction(function () use ($idp, $actor, $data) {
            $plan = $idp ?? new IdpPlan(['user_id' => $actor->id]);
            $plan->fill(Arr::except($data, 'items'))->save();
            $kept = [];
            foreach ($data['items'] as $item) {
                if (isset($item['id'])) {
                    $record = $plan->items()->findOrFail((int) $item['id']);
                    $record->update(Arr::except($item, 'id'));
                } else {
                    $record = $plan->items()->create($item);
                }
                $kept[] = $record->id;
            }
            $plan->items()->whereNotIn('id', $kept)->delete();
            Audit::record('idp.saved', $plan, $actor->id);

            return $plan;
        });

        return response()->json(['message' => 'Development plan saved.', 'id' => $plan->id], $idp ? 200 : 201);
    }

    public function submit(Request $request, IdpPlan $idp): JsonResponse
    {
        abort_unless($idp->user_id === $request->user()->id, 403);
        if ($idp->submitted_at !== null) {
            throw ValidationException::withMessages(['status' => 'This plan has already been submitted.']);
        }
        abort_if($idp->items()->count() === 0, 422, 'Add at least one development item before submitting.');
        $idp->update(['submitted_at' => now(), 'employee_snapshot' => $this->snapshot($request->user())]);
        Audit::record('idp.submitted', $idp, $request->user()->id);

        return response()->json(['message' => 'Development plan submitted to your supervisor.']);
    }

    public function approve(Request $request, IdpPlan $idp): JsonResponse
    {
        abort_unless($this->canApprove($request->user(), $idp), 403);
        if ($idp->approved_at !== null) {
            throw ValidationException::withMessages(['status' => 'This plan is already approved.']);
        }
        $idp->update(['approved_by' => $request->user()->id, 'approved_at' => now()]);
        Audit::record('idp.approved', $idp, $request->user()->id);

        return response()->json(['message' => 'Development plan approved.']);
    }

    public function destroy(Request $request, IdpPlan $idp): JsonResponse
    {
        abort_unless($idp->user_id === $request->user()->id && $idp->submitted_at === null, 403);
        $idp->delete();

        return response()->json(['message' => 'Draft development plan deleted.']);
    }

    public function print(Request $request, IdpPlan $idp): View
    {
        abort_unless($idp->user_id === $request->user()->id || $request->user()->hasAbility('lnd.manage') || $this->canApprove($request->user(), $idp), 403);
        $idp->load(['employee.position', 'employee.division', 'items.requirement']);

        return view('hris.idp-print', compact('idp'));
    }

    private function canApprove(User $actor, IdpPlan $plan): bool
    {
        if ($plan->submitted_at === null || $plan->approved_at !== null || $actor->id === $plan->user_id) {
            return false;
        }
        $employee = $plan->relationLoaded('employee') ? $plan->employee : User::find($plan->user_id);

        return $employee instanceof User && ($employee->role === 'division_chief' ? $actor->actsAsDirector() : $actor->actsAsDivisionChiefOf($employee->division_id));
    }

    /** @return array<string, mixed> */
    private function snapshot(User $employee): array
    {
        $employee->loadMissing(['position', 'division']);

        return ['name' => $employee->name, 'position' => $employee->position?->title, 'salary_grade' => $employee->position?->salary_grade,
            'division' => $employee->division?->name, 'captured_at' => now()->toIso8601String()];
    }
}
