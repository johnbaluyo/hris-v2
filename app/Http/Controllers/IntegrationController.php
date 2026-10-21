<?php

namespace App\Http\Controllers;

use App\Jobs\SyncPartnerUsers;
use App\Models\IntegrationRun;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IntegrationController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('integration.sync');

        return response()->json(IntegrationRun::latest('id')->paginate(10));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('integration.sync');
        $data = $request->validate(['partner' => ['required', Rule::in(array_keys(config('hris.partners')))]]);
        $run = DB::transaction(function () use ($request, $data) {
            $run = IntegrationRun::create($data + ['actor_id' => $request->user()->id]);
            Audit::record('integration.queued', $run, $request->user()->id);
            SyncPartnerUsers::dispatch($run->id)->afterCommit();

            return $run;
        });

        return response()->json(['message' => 'Synchronization queued.', 'id' => $run->id], 202);
    }
}
