<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePdsRequest;
use App\Models\PdsEntry;
use App\Models\PrivateFile;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PdsController extends Controller
{
    public function index(Request $request, ?User $employee = null): View
    {
        $employee ??= $request->user();
        Gate::authorize('readPds', $employee);

        return view('hris.pds', ['employee' => $employee, 'sections' => config('pds')]);
    }

    public function data(Request $request, User $employee, string $section): JsonResponse
    {
        Gate::authorize('readPds', $employee);
        abort_unless(config('pds.'.$section), 404);
        $query = $employee->pdsEntries()->where('section', $section)->latest('id');
        if (config('pds.'.$section.'.snapshot') && ! $request->boolean('history')) {
            return response()->json(['data' => $query->limit(1)->get(), 'current_page' => 1, 'last_page' => 1, 'total' => $query->exists() ? 1 : 0]);
        }

        return response()->json($query->paginate(10));
    }

    public function save(SavePdsRequest $request, User $employee, string $section, ?int $entry = null): JsonResponse
    {
        $validated = $request->validated();
        DB::transaction(function () use ($request, $employee, $section, $entry, $validated) {
            User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $old = $entry ? $employee->pdsEntries()->where('section', $section)->findOrFail($entry)
                : (config('pds.'.$section.'.snapshot') ? $employee->pdsEntries()->where('section', $section)->latest('id')->first() : null);
            $fileId = $request->boolean('remove_file') ? null : ($validated['file_id'] ?? $old?->file_id);
            if ($fileId) {
                abort_unless(in_array($section, ['learning', 'government-id'], true), 422, 'This section does not accept a file.');
                PrivateFile::where('user_id', $employee->id)->where('purpose', $section === 'learning' ? 'certificate' : 'government-id')->findOrFail($fileId);
            }
            $data = $validated['data'];
            $before = $old?->attributesToArray();
            // An identical snapshot save is a no-op; historical snapshot rows never change.
            if ($old && $old->data == $data && $old->file_id === $fileId) {
                return;
            }
            $record = config('pds.'.$section.'.snapshot') ? new PdsEntry : ($old ?? new PdsEntry);
            $record->fill(['user_id' => $employee->id, 'section' => $section, 'data' => $data, 'file_id' => $fileId, 'actor_id' => $request->user()->id])->save();
            if ($section === 'personal') {
                $allowed = array_intersect_key($data, array_flip(['first_name', 'middle_name', 'last_name', 'extension', 'birthday', 'sex', 'mobile_no', 'contact_person', 'contact_person_no']));
                $employee->forceFill($allowed);
                $employee->name = trim(implode(' ', array_filter([$employee->first_name, $employee->middle_name, $employee->last_name, $employee->extension])));
                $employee->save();
            }
            Audit::record('pds.'.$section.'.saved', $record, $request->user()->id, $before);
        });

        return response()->json(['message' => 'PDS section saved.']);
    }

    public function destroy(Request $request, User $employee, string $section, int $entry): JsonResponse
    {
        Gate::authorize('writePds', $employee);
        abort_unless(config('pds.'.$section) && ! config('pds.'.$section.'.snapshot'), 422, 'Snapshot sections retain their history. Save a new version to change current values.');
        DB::transaction(function () use ($request, $employee, $section, $entry) {
            $record = $employee->pdsEntries()->where('section', $section)->findOrFail($entry);
            Audit::record('pds.'.$section.'.removed', $record, $request->user()->id, $record->attributesToArray());
            $record->delete();
        });

        return response()->json(['message' => 'Row removed. Audit history is retained.']);
    }

    public function print(User $employee): View
    {
        Gate::authorize('readPds', $employee);
        $legacyAliases = [
            'experience' => 'work_experience',
            'voluntary' => 'voluntary_work',
            'learning' => 'learning_development',
            'other' => 'other_information',
            'declarations' => 'government_declarations',
            'references' => 'character_references',
            'government-id' => 'government_ids',
        ];
        $sections = [];
        foreach (config('pds') as $section => $definition) {
            $query = $employee->pdsEntries()->whereIn('section', array_filter([$section, $legacyAliases[$section] ?? null]))->latest('id');
            $entries = $definition['snapshot'] ? $query->limit(1)->get() : $query->get();
            if ($section === 'education') {
                $entries = $entries->sortBy(fn ($entry) => array_search($entry->data['educ_level'], ['ELEMENTARY', 'SECONDARY', 'VOCATIONAL / TRADE COURSE', 'COLLEGE', 'GRADUATE STUDIES'], true));
            } elseif (in_array($section, ['experience', 'learning'], true)) {
                $entries = $entries->sortByDesc(fn ($entry) => $entry->data[$section === 'experience' ? 'date_from' : 'date_to'] ?? '');
            }
            $sections[$section] = $entries;
        }

        return view('hris.pds-print', compact('employee', 'sections'));
    }
}
