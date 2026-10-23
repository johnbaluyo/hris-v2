<?php

namespace App\Http\Controllers;

use App\Models\ApplicantProfile;
use App\Models\ExamAttempt;
use App\Models\ExamCategory;
use App\Models\ExamQuestion;
use App\Models\Position;
use App\Models\PrivateFile;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentDocument;
use App\Models\RecruitmentVacancy;
use App\Models\User;
use App\Services\Files\PrivateUpload;
use App\Support\Audit;
use App\Support\WorkflowHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecruitmentController extends Controller
{
    public function index(Request $request): View
    {
        return view('hris.recruitment', ['manage' => $request->user()->hasAbility('recruitment.manage'),
            'canUpload' => ! $request->user()->hasReadOnlyCareers() && ($request->user()->role === 'applicant'
                || $request->user()->hasAbility('recruitment.manage') || DB::table('recruitment_evaluators')->where('user_id', $request->user()->id)->exists()),
            'positions' => $request->user()->hasAbility('recruitment.manage') ? Position::orderBy('title')->get(['id', 'title', 'code']) : collect(),
            'categories' => $request->user()->hasAbility('recruitment.manage') ? ExamCategory::whereNull('archived_at')->get() : collect(),
            'profile' => ApplicantProfile::where('user_id', $request->user()->id)->first()->data ?? []]);
    }

    public function vacancies(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => 'nullable|string|max:100']);
        $query = RecruitmentVacancy::with('position:id,title,code,division_id,salary_grade', 'categories:id,name,exam_type,time_limit');
        if ($request->user()->hasAbility('recruitment.manage')) {
            $query->with('evaluators:id,name');
        }
        if (! $request->user()->hasAbility('recruitment.manage')) {
            $query->where('status', 'Open for Application')->where(fn ($q) => $q->whereNull('closes_on')->orWhereDate('closes_on', '>=', today('Asia/Manila')))
                ->whereNotIn('id', RecruitmentApplication::where('user_id', $request->user()->id)->select('recruitment_vacancy_id'));
        }

        return response()->json($query->when($data['search'] ?? null, fn ($q, $search) => $q->whereHas('position', fn ($p) => $p->where('title', 'like', "%{$search}%")))->latest('id')->paginate(10));
    }

    public function saveVacancy(Request $request, ?RecruitmentVacancy $vacancy = null): JsonResponse
    {
        Gate::authorize('recruitment.manage');
        $data = $request->validate(['position_id' => ['required', 'integer', Rule::exists('positions', 'id'), Rule::unique('recruitment_vacancies')->ignore($vacancy)],
            'status' => 'required|in:Open for Application,Filled Up,archived', 'description' => 'nullable|string|max:10000', 'closes_on' => 'nullable|date_format:Y-m-d',
            'category_ids' => 'sometimes|array', 'category_ids.*' => ['integer', 'distinct', Rule::exists('exam_categories', 'id')->whereNull('archived_at')],
            'evaluators' => 'sometimes|array|max:50', 'evaluators.*.user_id' => 'required|integer|distinct|exists:users,id', 'evaluators.*.role' => 'required|string|max:100']);
        if ($vacancy) {
            abort_unless($vacancy->position_id === (int) $data['position_id'], 422, 'The vacancy position cannot be changed.');
        }
        DB::transaction(function () use ($data, $request, $vacancy) {
            $vacancy = RecruitmentVacancy::updateOrCreate(['id' => $vacancy?->id], Arr::except($data, ['category_ids', 'evaluators']));
            $vacancy->categories()->sync($data['category_ids'] ?? []);
            $evaluators = [];
            foreach ($data['evaluators'] ?? [] as $entry) {
                $user = User::whereKey($entry['user_id'])->firstOrFail();
                abort_unless($user->isEligible() && $user->role !== 'applicant', 422, 'Evaluators must be active employees.');
                $evaluators[$user->id] = ['role' => $entry['role']];
            }
            $vacancy->evaluators()->sync($evaluators);
            Audit::record('recruitment.vacancy.saved', $vacancy, $request->user()->id);
        });

        return response()->json(['message' => 'Vacancy and assignments saved.']);
    }

    public function bank(): JsonResponse
    {
        Gate::authorize('recruitment.manage');

        return response()->json(ExamCategory::with(['questions' => fn ($q) => $q->whereNull('archived_at')])->latest('id')->paginate(10));
    }

    public function saveCategory(Request $request, ?ExamCategory $category = null): JsonResponse
    {
        Gate::authorize('recruitment.manage');
        $data = $request->validate(['name' => 'required|string|max:255', 'exam_type' => 'required|string|max:100', 'time_limit' => 'required|integer|min:1|max:480', 'archive' => 'sometimes|boolean']);
        $category = ExamCategory::updateOrCreate(['id' => $category?->id], Arr::except($data, 'archive') + ['archived_at' => ! empty($data['archive']) ? now() : null]);
        Audit::record('recruitment.category.saved', $category, $request->user()->id);

        return response()->json(['message' => 'Exam category saved.', 'id' => $category->id]);
    }

    public function saveQuestion(Request $request, ExamCategory $category, PrivateUpload $uploads, ?ExamQuestion $question = null): JsonResponse
    {
        Gate::authorize('recruitment.manage');
        abort_if($category->archived_at !== null, 422, 'This category is archived.');
        abort_if($question && $question->exam_category_id !== $category->id, 404);
        $data = $request->validate(['question_type' => 'required|integer|in:1,2,3,4', 'question' => 'required|string|max:20000',
            'choices' => 'nullable|array|max:20', 'choices.*.text' => 'required|string|max:2000',
            'choices.*.image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048', 'archive' => 'sometimes|boolean']);
        abort_if((int) $data['question_type'] === 1 && count($data['choices'] ?? []) < 2, 422, 'Multiple choice questions need at least two choices.');
        $stored = [];
        try {
            $question = DB::transaction(function () use ($request, $data, $category, $question, $uploads, &$stored) {
                $imageId = $question?->image_file_id;
                if ($request->hasFile('image')) {
                    $file = $uploads->store($request->file('image'), $request->user(), $request->user(), 'exam-image');
                    $stored[] = $file;
                    $imageId = $file->id;
                }
                $choices = [];
                foreach ($data['choices'] ?? [] as $index => $choice) {
                    $image = null;
                    if ($request->hasFile("choices.{$index}.image")) {
                        $image = $uploads->store($request->file("choices.{$index}.image"), $request->user(), $request->user(), 'exam-image');
                        $stored[] = $image;
                    }
                    $choices[] = ['id' => (string) ($index + 1), 'text' => $choice['text'], 'image_file_id' => $image->id ?? ($question->choices[$index]['image_file_id'] ?? null)];
                }
                $question = ExamQuestion::updateOrCreate(['id' => $question?->id], ['exam_category_id' => $category->id,
                    'question_type' => $data['question_type'], 'question' => $data['question'], 'choices' => $choices,
                    'image_file_id' => $imageId, 'archived_at' => ! empty($data['archive']) ? now() : null]);
                Audit::record('recruitment.question.saved', $question, $request->user()->id);

                return $question;
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $file) {
                Storage::disk('local')->delete($file->path);
            }
            throw $exception;
        }

        return response()->json(['message' => 'Question version saved. Existing exam snapshots are preserved.', 'id' => $question->id]);
    }

    public function profile(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'applicant', 403);
        $data = $request->validate(['phone' => 'nullable|string|max:40', 'address' => 'nullable|string|max:1000',
            'education' => 'nullable|string|max:5000', 'experience' => 'nullable|string|max:10000',
            'user_id' => 'prohibited', 'role' => 'prohibited']);
        ApplicantProfile::updateOrCreate(['user_id' => $request->user()->id], ['data' => $data]);

        return response()->json(['message' => 'Applicant profile saved.']);
    }

    public function apply(Request $request, RecruitmentVacancy $vacancy): JsonResponse
    {
        $request->validate(['user_id' => 'prohibited', 'status' => 'prohibited', 'allow_test' => 'prohibited']);
        abort_unless($request->user()->role === 'applicant', 403);
        $application = DB::transaction(function () use ($request, $vacancy) {
            $vacancy = RecruitmentVacancy::whereKey($vacancy->id)->lockForUpdate()->firstOrFail();
            abort_unless($vacancy->status === 'Open for Application' && ($vacancy->closes_on === null || $vacancy->closes_on->gte(today('Asia/Manila'))), 422, 'This vacancy is closed.');
            abort_if(RecruitmentApplication::where('user_id', $request->user()->id)->where('recruitment_vacancy_id', $vacancy->id)->exists(), 422, 'You already applied for this vacancy.');
            $application = RecruitmentApplication::create(['recruitment_vacancy_id' => $vacancy->id, 'user_id' => $request->user()->id,
                'profile_snapshot' => ['name' => $request->user()->name, 'profile' => ApplicantProfile::where('user_id', $request->user()->id)->first()->data ?? []]]);
            WorkflowHistory::record('RECRUITMENT', $application, $request->user(), 'apply', 'pending application');

            return $application;
        });

        return response()->json(['message' => 'Application submitted.', 'id' => $application->id], 201);
    }

    public function applications(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => 'nullable|string|max:100']);
        $actor = $request->user();
        $query = RecruitmentApplication::with('applicant:id,name', 'vacancy.position:id,title', 'vacancy.categories:id,name,time_limit', 'attempts:id,recruitment_application_id,exam_category_id,expires_at,submitted_at', 'history.actor:id,name,role', 'generalDocuments')
            ->with(['documents' => fn ($q) => $q->when($actor->role === 'applicant', fn ($d) => $d->where('side', 'requirement'))]);
        if (! $actor->hasAbility('recruitment.manage')) {
            $query->where(fn ($q) => $q->where('user_id', $actor->id)->orWhereHas('vacancy.evaluators', fn ($e) => $e->where('users.id', $actor->id)));
        }

        return response()->json($query->when($data['search'] ?? null, fn ($q, $search) => $q->whereHas('applicant', fn ($u) => $u->where('name', 'like', "%{$search}%")))->latest('id')->paginate(10));
    }

    public function review(Request $request, RecruitmentApplication $application): JsonResponse
    {
        Gate::authorize('recruitment.manage');
        $data = $request->validate(['status' => 'required|in:pending application,for assessment,hired,short listed,failed',
            'remark' => 'nullable|string|max:5000', 'allow_test' => 'required|boolean']);
        DB::transaction(function () use ($request, $application, $data) {
            $application = RecruitmentApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $application->update($data);
            WorkflowHistory::record('RECRUITMENT', $application, $request->user(), 'review', $data['status'], $data['remark'] ?? null);
        });

        return response()->json(['message' => 'Application reviewed.']);
    }

    public function upload(Request $request, PrivateUpload $uploads): JsonResponse
    {
        abort_if($request->user()->hasReadOnlyCareers(), 403);
        $data = $request->validate(['application_id' => 'nullable|integer|exists:recruitment_applications,id',
            'document_type' => 'required|string|in:TOR,WES,PDS,CSE,IPCR,Other', 'side' => 'required|in:requirement,evaluation',
            'file' => 'required|file|mimes:pdf|max:10000']);
        $application = isset($data['application_id']) ? RecruitmentApplication::whereKey($data['application_id'])->firstOrFail() : null;
        $actor = $request->user();
        if ($data['side'] === 'evaluation') {
            abort_unless($application && ($actor->hasAbility('recruitment.manage') || $application->vacancy->evaluators()->where('users.id', $actor->id)->exists()), 403);
        } else {
            abort_unless(($actor->role === 'applicant' && (! $application || $application->user_id === $actor->id)) || $actor->hasAbility('recruitment.manage'), 403);
        }
        $owner = $application->applicant ?? $actor;
        abort_unless($owner->role === 'applicant', 422, 'Select an applicant application for this document.');
        $file = $uploads->store($request->file('file'), $owner, $actor, 'recruitment-'.$data['side']);
        try {
            $document = RecruitmentDocument::create(['user_id' => $owner->id, 'recruitment_application_id' => $application?->id,
                'private_file_id' => $file->id, 'document_type' => $data['document_type'], 'side' => $data['side']]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($file->path);
            $file->delete();
            throw $exception;
        }

        return response()->json(['message' => 'Private document uploaded.', 'id' => $document->id], 201);
    }

    public function documents(Request $request): JsonResponse
    {
        return response()->json(RecruitmentDocument::where('user_id', $request->user()->id)->where('side', 'requirement')->latest('id')->paginate(10));
    }

    public function report(Request $request, RecruitmentApplication $application): View
    {
        abort_unless($request->user()->hasAbility('recruitment.manage') || $application->vacancy->evaluators()->where('users.id', $request->user()->id)->exists(), 403);

        return view('hris.recruitment-report', ['application' => $application->load('attempts', 'vacancy.position', 'applicant')]);
    }

    public function image(Request $request, PrivateFile $file): StreamedResponse
    {
        abort_unless($file->purpose === 'exam-image', 404);
        $actor = $request->user();
        $allowed = $actor->hasAbility('recruitment.manage');
        if (! $allowed) {
            $attempts = ExamAttempt::whereHas('application', fn ($q) => $q->where('user_id', $actor->id)
                ->orWhereHas('vacancy.evaluators', fn ($e) => $e->where('users.id', $actor->id)))->get();
            foreach ($attempts as $attempt) {
                $isOwner = $attempt->application->user_id === $actor->id;
                if ($isOwner && ($attempt->submitted_at !== null || $attempt->expires_at->lte(now()) || ! $attempt->application->allow_test)) {
                    continue;
                }
                foreach ($attempt->questions as $question) {
                    if ($question['image_file_id'] === $file->id || in_array($file->id, array_column($question['choices'], 'image_file_id'), true)) {
                        $allowed = true;
                    }
                }
            }
        }
        abort_unless($allowed, 403);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->response($file->path, $file->original_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
