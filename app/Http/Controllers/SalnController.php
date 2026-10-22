<?php

namespace App\Http\Controllers;

use App\Models\SalnEntry;
use App\Models\SalnFiling;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalnController extends Controller
{
    private const SECTIONS = ['declarant', 'children', 'real_property', 'personal_property', 'liability', 'business_interest', 'government_relative'];

    public function index(Request $request): View
    {
        abort_if($request->user()->role === 'applicant', 403);

        return view('hris.saln');
    }

    public function data(Request $request): JsonResponse
    {
        $query = SalnFiling::with(['employee:id,name', 'entries']);
        if (! $request->user()->hasAbility('saln.custodian')) {
            $query->where('user_id', $request->user()->id);
        }
        $rows = $query->latest('compliance_date')->paginate(10);
        $rows->getCollection()->transform(fn (SalnFiling $filing) => $filing->toArray() + $this->totals($filing));

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['filing_type' => 'required|integer|between:1,3', 'compliance_type' => 'required|integer|between:1,3', 'compliance_date' => 'required|date_format:Y-m-d']);
        $filing = SalnFiling::create($data + ['user_id' => $request->user()->id]);
        Audit::record('saln.created', $filing, $request->user()->id);

        return response()->json(['message' => 'SALN filing created.', 'id' => $filing->id], 201);
    }

    public function show(Request $request, SalnFiling $saln): JsonResponse
    {
        $this->authorizeRead($request, $saln);

        return response()->json($saln->load('entries')->toArray() + $this->totals($saln));
    }

    public function saveEntry(Request $request, SalnFiling $saln, ?SalnEntry $entry = null): JsonResponse
    {
        abort_unless($saln->user_id === $request->user()->id, 403);
        if ($entry) {
            abort_unless($entry->saln_filing_id === $saln->id, 404);
        }
        $data = $request->validate(['section' => ['required', Rule::in(self::SECTIONS)], 'data' => 'required|array|max:30',
            'declared_amount' => 'nullable|numeric|min:0|max:999999999999.99']);
        $requiredAmount = in_array($data['section'], ['real_property', 'personal_property', 'liability'], true);
        if ($requiredAmount && ! isset($data['declared_amount'])) {
            return response()->json(['message' => 'The declared amount is required for this section.', 'errors' => ['declared_amount' => ['The declared amount is required.']]], 422);
        }
        $clean = $this->cleanData($data['section'], $data['data']);
        if ($data['section'] === 'declarant') {
            $existing = $saln->entries()->where('section', 'declarant')->latest('id')->first();
            $entry = $existing instanceof SalnEntry ? $existing : new SalnEntry(['saln_filing_id' => $saln->id]);
        } else {
            $entry ??= new SalnEntry(['saln_filing_id' => $saln->id]);
        }
        $isNew = ! $entry->exists;
        $before = $entry->attributesToArray();
        $entry->fill(['section' => $data['section'], 'data' => $clean, 'declared_amount' => $requiredAmount ? $data['declared_amount'] : null])->save();
        Audit::record('saln-entry.saved', $entry, $request->user()->id, $before);

        return response()->json(['message' => 'SALN section saved.', 'id' => $entry->id], $isNew ? 201 : 200);
    }

    public function destroyEntry(Request $request, SalnFiling $saln, SalnEntry $entry): JsonResponse
    {
        abort_unless($saln->user_id === $request->user()->id && $entry->saln_filing_id === $saln->id, 403);
        $entry->delete();

        return response()->json(['message' => 'SALN entry deleted.']);
    }

    public function print(Request $request, SalnFiling $saln): View
    {
        $this->authorizeRead($request, $saln);
        $saln->load(['employee', 'entries']);
        $totals = $this->totals($saln);

        return view('hris.saln-print', compact('saln', 'totals'));
    }

    private function authorizeRead(Request $request, SalnFiling $filing): void
    {
        abort_unless($filing->user_id === $request->user()->id || $request->user()->hasAbility('saln.custodian'), 403);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function cleanData(string $section, array $data): array
    {
        $allowed = match ($section) {
            'declarant' => ['last_name', 'first_name', 'middle_name', 'position', 'office', 'office_address', 'spouse_last_name', 'spouse_first_name', 'spouse_middle_name', 'spouse_position', 'spouse_office', 'spouse_office_address'],
            'children' => ['name', 'birth_date', 'age'],
            'real_property' => ['description', 'kind', 'location', 'assessed_value', 'fair_value', 'acquisition_year', 'acquisition_mode'],
            'personal_property' => ['description', 'acquired_year'],
            'liability' => ['nature', 'creditor'],
            'business_interest' => ['business_name', 'address', 'nature', 'acquisition_date'],
            'government_relative' => ['name', 'relationship', 'position', 'office'],
            default => [],
        };

        return collect($data)->only($allowed)->map(fn ($value) => is_string($value) ? mb_substr(trim($value), 0, 2000) : $value)->all();
    }

    /** @return array{total_assets: string, total_liabilities: string, net_worth: string} */
    private function totals(SalnFiling $filing): array
    {
        $entries = $filing->relationLoaded('entries') ? $filing->entries : $filing->entries()->get();
        $assets = $entries->whereIn('section', ['real_property', 'personal_property'])->sum(fn (SalnEntry $entry) => (int) round(((float) $entry->declared_amount) * 100));
        $liabilities = $entries->where('section', 'liability')->sum(fn (SalnEntry $entry) => (int) round(((float) $entry->declared_amount) * 100));

        return ['total_assets' => number_format($assets / 100, 2, '.', ''), 'total_liabilities' => number_format($liabilities / 100, 2, '.', ''), 'net_worth' => number_format(($assets - $liabilities) / 100, 2, '.', '')];
    }
}
