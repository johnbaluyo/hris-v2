<?php

namespace Tests\Feature\Hris;

use App\Models\Division;
use App\Models\ExamAttempt;
use App\Models\ExamCategory;
use App\Models\ExamQuestion;
use App\Models\Position;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentDocument;
use App\Models\RecruitmentVacancy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecruitmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_question_images_are_private_and_question_edits_do_not_replace_attempt_images(): void
    {
        Storage::fake('local');
        $hr = User::factory()->create(['role' => 'hr']);
        $category = ExamCategory::create(['name' => 'Image exam', 'exam_type' => 'Written', 'time_limit' => 20]);
        $this->actingAs($hr)->post('/hr/recruitment/categories/'.$category->id.'/questions', ['question_type' => 3, 'question' => 'Illustrated essay', 'image' => UploadedFile::fake()->image('example.png')])->assertOk();
        $question = ExamQuestion::firstOrFail();
        $imageId = $question->image_file_id;
        $applicant = $this->applicant();
        $vacancy = $this->vacancy();
        $vacancy->categories()->attach($category);
        $application = RecruitmentApplication::create(['recruitment_vacancy_id' => $vacancy->id, 'user_id' => $applicant->id, 'status' => 'for assessment', 'allow_test' => true, 'profile_snapshot' => []]);
        $this->actingAs($applicant)->get('/recruitment/images/'.$imageId)->assertForbidden();
        $this->postJson('/careers/applications/'.$application->id.'/exams/'.$category->id.'/start', [])->assertOk();
        $this->get('/recruitment/images/'.$imageId)->assertOk();
        $this->get('/files/'.$imageId)->assertForbidden();
        $this->actingAs($this->applicant())->get('/recruitment/images/'.$imageId)->assertForbidden();
        $this->actingAs($hr)->post('/hr/recruitment/categories/'.$category->id.'/questions/'.$question->id, ['question_type' => 3, 'question' => 'New illustrated essay', 'image' => UploadedFile::fake()->image('replacement.png')])->assertOk();
        $this->assertNotSame($imageId, $question->fresh()->image_file_id);
        $this->assertSame($imageId, ExamAttempt::firstOrFail()->questions[0]['image_file_id']);
    }

    private function vacancy(): RecruitmentVacancy
    {
        return RecruitmentVacancy::create(['position_id' => Position::create(['title' => 'Fictional analyst', 'division_id' => Division::firstOrCreate(['name' => 'Fictional division'])->id])->id]);
    }

    private function applicant(): User
    {
        return User::factory()->create(['role' => 'applicant', 'employee_type' => 'Applicant']);
    }

    public function test_applicant_can_manage_only_own_profile_and_apply_once_to_open_vacancies(): void
    {
        $applicant = $this->applicant();
        $vacancy = $this->vacancy();
        $this->actingAs($applicant)->get('/careers')->assertOk();
        $this->postJson('/careers/profile', ['phone' => 'Example phone', 'experience' => 'Fictional experience'])->assertOk();
        $this->postJson('/careers/profile', ['user_id' => $this->applicant()->id])->assertUnprocessable();
        $this->postJson('/careers/vacancies/'.$vacancy->id.'/apply', [])->assertCreated();
        $this->postJson('/careers/vacancies/'.$vacancy->id.'/apply', [])->assertUnprocessable();
        $this->getJson('/careers/vacancies')->assertJsonCount(0, 'data');
        $application = RecruitmentApplication::firstOrFail();
        $this->assertSame('Fictional experience', $application->profile_snapshot['profile']['experience']);
        $this->actingAs($this->applicant())->getJson('/careers/applications')->assertJsonCount(0, 'data');
        $vacancy->update(['status' => 'Filled Up']);
        $this->postJson('/careers/vacancies/'.$vacancy->id.'/apply', [])->assertUnprocessable();
        $this->postJson('/hr/recruitment/vacancies', [])->assertForbidden();
    }

    public function test_exam_uses_server_deadline_snapshots_all_types_and_submits_once(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 08:00:00', 'Asia/Manila'));
        $applicant = $this->applicant();
        $vacancy = $this->vacancy();
        $category = ExamCategory::create(['name' => 'Example assessment', 'exam_type' => 'Written', 'time_limit' => 20]);
        $vacancy->categories()->attach($category);
        $answers = [];
        foreach ([1, 2, 3, 4] as $type) {
            $question = ExamQuestion::create(['exam_category_id' => $category->id, 'question_type' => $type, 'question' => 'Original question '.$type,
                'choices' => $type === 1 ? [['id' => '1', 'text' => 'First choice', 'image_file_id' => null], ['id' => '2', 'text' => 'Second choice', 'image_file_id' => null]] : []]);
            $answers[$question->id] = match ($type) {
                1 => '1', 2 => 'True', 3 => 'An essay response.', default => null
            };
        }
        $this->actingAs($applicant)->postJson('/careers/vacancies/'.$vacancy->id.'/apply', [])->assertCreated();
        $application = RecruitmentApplication::firstOrFail();
        $start = '/careers/applications/'.$application->id.'/exams/'.$category->id.'/start';
        $this->postJson($start, [])->assertForbidden();
        $application->update(['status' => 'for assessment', 'allow_test' => true]);
        $this->postJson($start, ['time_limit' => 9999])->assertUnprocessable();
        $this->postJson($start, [])->assertOk();
        $attempt = ExamAttempt::firstOrFail();
        $this->assertSame('2026-10-23 08:20:00', $attempt->expires_at->format('Y-m-d H:i:s'));
        $this->get('/careers/exams/'.$attempt->id)->assertOk()->assertSee('Original question 3');
        ExamQuestion::query()->update(['question' => 'Changed question']);
        $this->travel(5)->minutes();
        $this->postJson($start, [])->assertJsonPath('id', $attempt->id);
        $this->assertDatabaseCount('exam_attempts', 1);
        $submit = '/careers/exams/'.$attempt->id.'/submit';
        $this->postJson($submit, ['answers' => [999 => 'Foreign answer']])->assertUnprocessable();
        $this->postJson($submit, ['answers' => $answers])->assertOk();
        $this->postJson($submit, ['answers' => array_fill_keys(array_keys($answers), 'Overwrite')])->assertOk();
        $this->assertSame($answers, $attempt->fresh()->answers);
        $this->get('/recruitment/applications/'.$application->id.'/report')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'hr']))->get('/recruitment/applications/'.$application->id.'/report')->assertOk()->assertSee('Original question 3')->assertDontSee('Changed question');
    }

    public function test_foreign_expired_and_revoked_attempts_are_rejected_and_applications_do_not_share_answers(): void
    {
        $applicant = $this->applicant();
        $vacancy = $this->vacancy();
        $category = ExamCategory::create(['name' => 'Timed', 'exam_type' => 'Written', 'time_limit' => 1]);
        $category->questions()->create(['question_type' => 3, 'question' => 'Example essay', 'choices' => []]);
        $vacancy->categories()->attach($category);
        $application = RecruitmentApplication::create(['recruitment_vacancy_id' => $vacancy->id, 'user_id' => $applicant->id,
            'status' => 'for assessment', 'allow_test' => true, 'profile_snapshot' => []]);
        $start = '/careers/applications/'.$application->id.'/exams/'.$category->id.'/start';
        $this->actingAs($this->applicant())->postJson($start, [])->assertForbidden();
        $this->actingAs($applicant)->postJson($start, [])->assertOk();
        $attempt = ExamAttempt::firstOrFail();
        $this->travel(2)->minutes();
        $this->postJson('/careers/exams/'.$attempt->id.'/submit', ['answers' => []])->assertUnprocessable();
        $this->assertNull($attempt->fresh()->submitted_at);
        $this->assertNull($attempt->fresh()->answers);
        $otherVacancy = $this->vacancy();
        $otherVacancy->categories()->attach($category);
        $other = RecruitmentApplication::create(['recruitment_vacancy_id' => $otherVacancy->id, 'user_id' => $applicant->id,
            'status' => 'for assessment', 'allow_test' => true, 'profile_snapshot' => []]);
        $this->postJson('/careers/applications/'.$other->id.'/exams/'.$category->id.'/start', [])->assertOk();
        $this->assertDatabaseCount('exam_attempts', 2);
        $other->update(['allow_test' => false]);
        $this->postJson('/careers/exams/'.ExamAttempt::latest('id')->first()->id.'/submit', ['answers' => []])->assertForbidden();
    }

    public function test_evaluation_documents_are_private_to_hr_and_assigned_evaluators(): void
    {
        Storage::fake('local');
        $applicant = $this->applicant();
        $vacancy = $this->vacancy();
        $application = RecruitmentApplication::create(['recruitment_vacancy_id' => $vacancy->id, 'user_id' => $applicant->id, 'profile_snapshot' => []]);
        $evaluator = User::factory()->create();
        $vacancy->evaluators()->attach($evaluator->id, ['role' => 'Document reviewer']);
        $payload = ['application_id' => $application->id, 'document_type' => 'Other', 'side' => 'evaluation', 'file' => UploadedFile::fake()->create('evaluation.pdf', 20, 'application/pdf')];
        $this->actingAs($applicant)->post('/careers/documents', $payload)->assertForbidden();
        $this->actingAs($evaluator)->post('/careers/documents', $payload)->assertCreated();
        $fileId = $application->documents()->firstOrFail()->private_file_id;
        $this->get('/files/'.$fileId)->assertOk();
        $this->actingAs($applicant)->get('/files/'.$fileId)->assertForbidden();
        $this->post('/careers/documents', ['document_type' => 'TOR', 'side' => 'requirement', 'file' => UploadedFile::fake()->create('requirement.pdf', 10, 'application/pdf')])->assertCreated();
        $this->actingAs($evaluator)->getJson('/careers/applications')->assertJsonCount(1, 'data.0.general_documents');
        $this->get('/files/'.RecruitmentDocument::whereNull('recruitment_application_id')->first()->private_file_id)->assertOk();
        $this->actingAs(User::factory()->create())->get('/files/'.$fileId)->assertForbidden();
        $this->actingAs($applicant)->post('/careers/documents', ['document_type' => 'TOR', 'side' => 'requirement', 'file' => UploadedFile::fake()->create('bad.exe', 1, 'application/octet-stream')])->assertSessionHasErrors('file');
    }
}
