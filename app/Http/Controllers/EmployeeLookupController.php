<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_if($request->user()->role === 'applicant', 403);
        $data = $request->validate(['search' => 'nullable|string|max:100']);

        return response()->json(User::where('is_archived', false)->where('role', '!=', 'applicant')
            ->where(fn ($q) => $q->whereNull('termination_date')->orWhereDate('termination_date', '>', today('Asia/Manila')))
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')->orderBy('id')->paginate(10, ['id', 'name']));
    }
}
