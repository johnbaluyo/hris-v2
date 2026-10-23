<?php

namespace App\Http\Controllers;

use App\Models\OfficePunchWindow;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OfficePunchWindowController extends Controller
{
    public function data(): JsonResponse
    {
        Gate::authorize('attendance.office-punch.manage');

        $page = OfficePunchWindow::latest('id')->paginate(10);
        $page->getCollection()->transform(function (OfficePunchWindow $window) {
            $window->setAttribute('can_revoke', $window->revoked_at === null && $window->ends_at->gt(now('Asia/Manila')));

            return $window;
        });

        return response()->json($page);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('attendance.office-punch.manage');
        $data = $request->validate(['starts_at' => 'required|date_format:Y-m-d\TH:i', 'ends_at' => 'required|date_format:Y-m-d\TH:i|after:starts_at|after:now',
            'all_employees' => 'sometimes|accepted', 'reason' => 'required|string|max:1000', 'employee_ids' => 'prohibited']);
        $window = DB::transaction(function () use ($request, $data) {
            $window = OfficePunchWindow::create(['starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'],
                'all_employees' => true, 'reason' => $data['reason'], 'created_by' => $request->user()->id]);
            Audit::record('office-punch-window.created', $window, $request->user()->id);

            return $window;
        });

        return response()->json(['message' => 'Office punch access window saved.', 'id' => $window->id], 201);
    }

    public function revoke(Request $request, OfficePunchWindow $window): JsonResponse
    {
        Gate::authorize('attendance.office-punch.manage');
        DB::transaction(function () use ($request, $window) {
            $window = OfficePunchWindow::whereKey($window->id)->lockForUpdate()->firstOrFail();
            if ($window->revoked_at === null) {
                $before = $window->attributesToArray();
                $window->update(['revoked_at' => now('Asia/Manila'), 'revoked_by' => $request->user()->id]);
                Audit::record('office-punch-window.revoked', $window, $request->user()->id, $before);
            }
        });

        return response()->json(['message' => 'Office punch access window revoked.']);
    }
}
