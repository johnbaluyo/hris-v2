<?php

namespace App\Http\Controllers;

use App\Models\ExamAttempt;
use App\Models\ExamCategory;
use App\Models\RecruitmentApplication;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RecruitmentExamController extends Controller
{
    private function eligible(Request $request, RecruitmentApplication $application, int $categoryId): void
    {
        abort_unless($request->user()->role === 'applicant' && $application->user_id === $request->user()->id, 403);
        abort_unless($application->allow_test && in_array($application->status, ['for assessment', 'short listed'], true)
            && $application->vacancy->status !== 'archived' && $application->vacancy->categories()->where('exam_categories.id', $categoryId)->exists(), 403, 'This examination is not enabled for your application.');
    }

    public function start(Request $request, RecruitmentApplication $application, ExamCategory $category): JsonResponse
    {
        $request->validate(['user_id' => 'prohibited', 'time_limit' => 'prohibited', 'category_id' => 'prohibited']);
        $attempt = DB::transaction(function () use ($request, $application, $category) {
            $application = RecruitmentApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $this->eligible($request, $application, $category->id);
            $existing = ExamAttempt::where('recruitment_application_id', $application->id)->where('exam_category_id', $category->id)->first();
            if ($existing) {
                return $existing;
            }
            $category = ExamCategory::whereKey($category->id)->lockForUpdate()->firstOrFail();
            abort_if($category->archived_at !== null, 422, 'This exam category is archived.');
            $questions = $category->questions()->whereNull('archived_at')->orderBy('id')->get(['id', 'question_type', 'question', 'choices', 'image_file_id'])->toArray();
            abort_if(empty($questions), 422, 'The exam has no active questions.');
            $attempt = ExamAttempt::create(['recruitment_application_id' => $application->id, 'exam_category_id' => $category->id,
                'questions' => $questions, 'category_snapshot' => $category->only('name', 'exam_type'), 'time_limit' => $category->time_limit, 'started_at' => now(), 'expires_at' => now()->addMinutes($category->time_limit)]);
            Audit::record('exam.started', $attempt, $request->user()->id);

            return $attempt;
        });

        return response()->json(['message' => 'Exam timer loaded.', 'id' => $attempt->id, 'url' => '/careers/exams/'.$attempt->id,
            'expires_at' => $attempt->expires_at, 'submitted_at' => $attempt->submitted_at]);
    }

    public function show(Request $request, ExamAttempt $attempt): View
    {
        $this->eligible($request, $attempt->application, $attempt->exam_category_id);
        abort_if($attempt->submitted_at !== null || $attempt->expires_at->lte(now()), 422, 'This exam is submitted or its time has expired.');

        return view('hris.recruitment-exam', compact('attempt'));
    }

    public function submit(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $data = $request->validate(['answers' => 'present|array', 'answers.*' => 'nullable|string|max:30000',
            'user_id' => 'prohibited', 'category_id' => 'prohibited', 'time_limit' => 'prohibited']);
        DB::transaction(function () use ($request, $attempt, $data) {
            $application = RecruitmentApplication::whereKey($attempt->recruitment_application_id)->lockForUpdate()->firstOrFail();
            $attempt = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            abort_unless($application->user_id === $request->user()->id && $request->user()->role === 'applicant', 403);
            if ($attempt->submitted_at !== null) {
                return;
            }
            $this->eligible($request, $application, $attempt->exam_category_id);
            abort_if($attempt->expires_at->lte(now()), 422, 'The exam time has expired.');
            $ids = array_map('strval', array_column($attempt->questions, 'id'));
            abort_if(array_diff(array_map('strval', array_keys($data['answers'])), $ids) !== [], 422, 'An answer refers to a question outside this attempt.');
            $answers = [];
            foreach ($attempt->questions as $question) {
                $answer = $data['answers'][$question['id']] ?? null;
                if ($answer !== null && $answer !== '') {
                    if ($question['question_type'] === 1) {
                        abort_unless(in_array($answer, array_column($question['choices'], 'id'), true), 422, 'Choose an answer from this question.');
                    } elseif ($question['question_type'] === 2) {
                        abort_unless(in_array($answer, ['True', 'False'], true), 422, 'Choose True or False.');
                    }
                }
                $answers[(string) $question['id']] = $answer;
            }
            $attempt->update(['answers' => $answers, 'submitted_at' => now()]);
            Audit::record('exam.submitted', $attempt, $request->user()->id);
        });

        return response()->json(['message' => 'Exam submitted. Your finalized answers are preserved.']);
    }
}
