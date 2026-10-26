<?php

namespace Tests\Feature\Hris;

use App\Models\PdsEntry;
use App\Models\PrivateFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdsTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_snapshots_sync_only_allowlisted_fields_and_preserve_zero_and_clearing(): void
    {
        $user = User::factory()->create();
        $path = '/profile/'.$user->id.'/personal';
        $data = ['first_name' => 'Avery', 'last_name' => 'Example', 'mobile_no' => '555-0100', 'height' => 0];
        $this->actingAs($user)->postJson($path, ['data' => $data])->assertOk();
        $this->assertSame('Avery Example', $user->fresh()->name);
        $this->assertSame(0, $user->pdsEntries()->latest('id')->first()->data['height']);
        $data['mobile_no'] = null;
        $this->postJson($path, ['data' => $data])->assertOk();
        $this->assertNull($user->fresh()->mobile_no);
        $this->postJson($path, ['data' => $data])->assertOk();
        $this->assertDatabaseCount('pds_entries', 2);
        $data['role'] = 'system_admin';
        $this->postJson($path, ['data' => $data])->assertUnprocessable();
        $this->assertSame('staff', $user->fresh()->role);
        $this->getJson($path.'/data')->assertJsonCount(1, 'data');
        $this->getJson($path.'/data?history=1')->assertJsonPath('total', 2);
    }

    public function test_every_documented_section_can_be_saved_and_reloaded(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $sections = [
            'family' => ['spouse_fname' => 'Morgan', 'children' => [['name' => 'Taylor', 'birthday' => '2015-02-01']]],
            'education' => ['educ_level' => 'COLLEGE', 'school' => 'Example University', 'date_from' => 2000, 'date_to' => 2004],
            'eligibility' => ['eligibility' => 'Example qualification', 'rating' => '0'],
            'experience' => ['date_from' => '2020-01-01', 'date_to' => 'Present', 'position' => 'Analyst', 'department' => 'Research', 'salary' => 0],
            'voluntary' => ['org_name_address' => 'Example volunteers', 'hours' => 0],
            'learning' => ['ld_title' => 'Example training', 'hours' => 0, 'ld_type' => '3'],
            'other' => ['special_skills_hobbies' => 'Writing'],
            'declarations' => array_fill_keys(['34_a', '35_a', '35_b', '36_a', '37_a', '38_a', '38_b', '39_a', '40_a', '40_b', '40_c'], 'No'),
            'references' => ['name_1' => 'Morgan Example', 'contact_1' => '555-0101'],
            'government-id' => ['gov_id' => 'Example ID', 'id_no' => 'SYNTHETIC-001'],
        ];
        foreach ($sections as $section => $data) {
            $this->postJson('/profile/'.$user->id.'/'.$section, ['data' => $data])->assertOk();
            $this->getJson('/profile/'.$user->id.'/'.$section.'/data')->assertOk()->assertJsonCount(1, 'data');
        }
        $this->get('/profile/'.$user->id.'/print')->assertOk()->assertSee('PERSONAL DATA SHEET')->assertSee('Example University')->assertSee('Example training');
    }

    public function test_child_ids_and_prints_cannot_cross_employee_boundaries(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $entry = PdsEntry::create(['user_id' => $other->id, 'section' => 'education', 'data' => ['school' => 'Private'], 'actor_id' => $other->id]);
        $this->actingAs($owner)->getJson('/profile/'.$other->id.'/education/data')->assertForbidden();
        $this->get('/profile/'.$other->id.'/print')->assertForbidden();
        $this->putJson('/profile/'.$owner->id.'/education/'.$entry->id, ['data' => ['educ_level' => 'COLLEGE', 'school' => 'Injected']])->assertNotFound();
        $this->deleteJson('/profile/'.$owner->id.'/education/'.$entry->id)->assertNotFound();
        $this->assertSame('Private', $entry->fresh()->data['school']);
    }

    public function test_ongoing_sentinel_and_present_are_normalized_but_unknown_dates_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['Present', '0000-00-02'] as $original) {
            $this->postJson('/profile/'.$user->id.'/experience', ['data' => ['date_from' => '2020-01-01', 'date_to' => $original, 'position' => 'Analyst', 'department' => 'Research']])->assertOk();
            $entry = $user->pdsEntries()->latest('id')->first();
            $this->assertTrue($entry->data['is_ongoing']);
            $this->assertNull($entry->data['date_to']);
            $this->assertSame($original, $entry->data['original_date_to']);
        }
        $this->postJson('/profile/'.$user->id.'/experience', ['data' => ['date_from' => '2020-01-01', 'date_to' => '0000-00-00', 'is_ongoing' => false, 'position' => 'Analyst', 'department' => 'Research']])->assertUnprocessable();
    }

    public function test_repeatable_rows_support_school_edits_and_audited_removal(): void
    {
        $user = User::factory()->create();
        $data = ['educ_level' => 'COLLEGE', 'school' => 'First school'];
        $this->actingAs($user)->postJson('/profile/'.$user->id.'/education', ['data' => $data])->assertOk();
        $entry = $user->pdsEntries()->first();
        $data['school'] = 'Corrected school';
        $this->putJson('/profile/'.$user->id.'/education/'.$entry->id, ['data' => $data])->assertOk();
        $this->assertSame('Corrected school', $entry->fresh()->data['school']);
        $this->deleteJson('/profile/'.$user->id.'/education/'.$entry->id)->assertOk();
        $this->assertSoftDeleted('pds_entries', ['id' => $entry->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'pds.education.removed', 'subject_id' => $entry->id]);
    }

    public function test_declarations_require_explanations_for_affirmative_answers(): void
    {
        $user = User::factory()->create();
        $data = array_fill_keys(['34_a', '35_a', '35_b', '36_a', '37_a', '38_a', '38_b', '39_a', '40_a', '40_b', '40_c'], 'No');
        $data['35_a'] = 'Yes';
        $this->actingAs($user)->postJson('/profile/'.$user->id.'/declarations', ['data' => $data])->assertUnprocessable()->assertJsonValidationErrors('data.35_a2');
    }

    public function test_private_attachments_retain_the_original_until_a_valid_replacement_is_saved(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);
        $upload = $this->post('/employees/'.$user->id.'/files', ['purpose' => 'government-id', 'file' => UploadedFile::fake()->create('id.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
        $id = $upload->json('id');
        $file = PrivateFile::findOrFail($id);
        $this->assertNotSame('id.pdf', basename($file->path));
        $data = ['gov_id' => 'Example ID', 'id_no' => 'SYNTHETIC-001'];
        $this->postJson('/profile/'.$user->id.'/government-id', ['data' => $data, 'file_id' => $id])->assertOk();
        $this->get('/files/'.$id)->assertOk();
        $this->actingAs($other)->get('/files/'.$id)->assertForbidden();
        $this->actingAs($user);
        $replacement = $this->post('/employees/'.$user->id.'/files', ['purpose' => 'government-id', 'file' => UploadedFile::fake()->create('replacement.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $this->postJson('/profile/'.$user->id.'/government-id', ['data' => $data, 'file_id' => $replacement])->assertOk();
        $this->assertEquals($replacement, $user->pdsEntries()->latest('id')->first()->file_id);
        Storage::disk('local')->assertExists($file->path);
        $this->assertTrue($user->pdsEntries()->where('file_id', $id)->exists());
        $this->actingAs($user)->postJson('/profile/'.$user->id.'/government-id', ['data' => $data, 'remove_file' => true])->assertOk();
        $this->assertNull($user->pdsEntries()->latest('id')->first()->file_id);
        Storage::disk('local')->assertExists($file->path);
        Storage::disk('local')->delete($file->path);
        $this->get('/files/'.$id)->assertNotFound();
        $this->assertDatabaseHas('audit_events', ['action' => 'file.missing']);
    }

    public function test_private_image_previews_require_the_same_record_authorization(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $image = UploadedFile::fake()->createWithContent('id.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+juxkAAAAASUVORK5CYII='));
        $id = $this->actingAs($owner)->post('/employees/'.$owner->id.'/files', ['purpose' => 'government-id', 'file' => $image], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $response = $this->get('/files/'.$id.'?preview=1')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline', $response->headers->get('content-disposition'));
        $this->actingAs($other)->get('/files/'.$id.'?preview=1')->assertForbidden();
    }
}
