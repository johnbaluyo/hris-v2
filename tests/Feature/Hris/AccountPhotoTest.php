<?php

namespace Tests\Feature\Hris;

use App\Models\PrivateFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function image(string $name = 'avatar.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+juxkAAAAASUVORK5CYII='));
    }

    public function test_accounts_can_upload_their_own_picture_and_hr_can_view_it_but_unrelated_accounts_cannot(): void
    {
        Storage::fake('local');
        $owner = User::factory()->unverified()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->post('/account/profile-photo', ['file' => $this->image(), 'purpose' => 'personnel', 'user_id' => $other->id], ['Accept' => 'application/json'])
            ->assertCreated();
        $file = PrivateFile::firstOrFail();
        $this->assertSame($owner->id, $file->user_id);
        $this->assertSame('profile', $file->purpose);
        $url = '/employees/'.$owner->id.'/avatar';
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'hr']))->get($url)->assertOk();
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_missing_photos_have_a_private_svg_fallback_and_invalid_replacements_preserve_the_original(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['name' => 'Avery Example']);
        $url = '/employees/'.$user->id.'/avatar';
        $this->actingAs($user)->get($url)->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('AE');
        $this->post('/account/profile-photo', ['file' => $this->image()], ['Accept' => 'application/json'])->assertCreated();
        $first = PrivateFile::firstOrFail();
        $this->post('/account/profile-photo', ['file' => UploadedFile::fake()->create('not-an-image.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('private_files', 1);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        Storage::disk('local')->delete($first->path);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->get($url.'?fallback=1')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_self_photo_permission_does_not_allow_uploading_another_accounts_picture_or_personnel_documents(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->post('/employees/'.$other->id.'/files', ['purpose' => 'profile', 'file' => $this->image()], ['Accept' => 'application/json'])->assertForbidden();
        $this->post('/employees/'.$user->id.'/files', ['purpose' => 'personnel', 'file' => $this->image()], ['Accept' => 'application/json'])->assertForbidden();
        $this->assertDatabaseCount('private_files', 0);
    }
}
