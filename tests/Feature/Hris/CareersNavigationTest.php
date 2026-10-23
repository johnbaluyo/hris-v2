<?php

namespace Tests\Feature\Hris;

use App\Models\Division;
use App\Models\Position;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentVacancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CareersNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_recruitment_is_under_administration_and_staff_careers_is_last_in_records(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $this->actingAs($hr)->get('/careers')->assertOk()->assertSeeInOrder(['HR ADMINISTRATION', 'Careers & recruitment'], false);
        $staff = User::factory()->create();
        $page = $this->actingAs($staff)->get('/careers')->assertOk()->assertDontSee('Careers & recruitment', false);
        $page->assertSeeInOrder(['DEVELOPMENT & RECORDS', 'Personnel documents', '> Careers</a>'], false);
    }

    public function test_chiefs_and_directors_have_read_only_careers_even_when_assigned_as_evaluators(): void
    {
        $division = Division::create(['name' => 'Example division']);
        $vacancy = RecruitmentVacancy::create(['position_id' => Position::create(['division_id' => $division->id, 'title' => 'Example vacancy'])->id]);
        $applicant = User::factory()->create(['role' => 'applicant']);
        $application = RecruitmentApplication::create(['recruitment_vacancy_id' => $vacancy->id, 'user_id' => $applicant->id, 'profile_snapshot' => []]);
        foreach (['division_chief', 'executive_director', 'chief_admin_officer'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            DB::table('ability_grants')->insert(['user_id' => $user->id, 'ability' => 'recruitment.manage']);
            $vacancy->evaluators()->attach($user->id, ['role' => 'Reviewer']);
            $this->actingAs($user)->get('/careers')->assertOk()->assertDontSee('id="new-vacancy"', false)->assertDontSee('Upload document');
            $this->getJson('/careers/vacancies')->assertOk();
            $this->getJson('/careers/applications')->assertOk()->assertJsonCount(1, 'data');
            $this->postJson('/hr/recruitment/vacancies', [])->assertForbidden();
            $this->postJson('/hr/recruitment/categories', [])->assertForbidden();
            $this->postJson('/hr/recruitment/applications/'.$application->id.'/review', [])->assertForbidden();
            $this->post('/careers/documents', ['application_id' => $application->id, 'side' => 'evaluation', 'document_type' => 'Other', 'file' => UploadedFile::fake()->create('example.pdf', 1, 'application/pdf')])->assertForbidden();
        }
    }
}
