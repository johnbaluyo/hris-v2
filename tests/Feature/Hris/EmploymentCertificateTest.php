<?php

namespace Tests\Feature\Hris;

use App\Models\Division;
use App\Models\EmploymentCertificate;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmploymentCertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_certificate_flow_computes_annual_amounts_and_preserves_identity_and_private_pdf(): void
    {
        Storage::fake('local');
        $employee = User::factory()->create(['name' => 'Original Employee', 'position_id' => Position::create(['title' => 'Original Position', 'division_id' => Division::create(['name' => 'Fictional division'])->id])->id]);
        $hr = User::factory()->create(['role' => 'hr']);
        config(['records.certificate_signer_ids' => [$hr->id], 'records.certificate_public_verification' => true]);
        $this->actingAs($employee)->get('/cert')->assertOk();
        $this->postJson('/cert', ['type' => 'current', 'purpose' => 'Fictional purpose'])->assertCreated();
        $certificate = EmploymentCertificate::firstOrFail();
        $token = $certificate->verification_token;
        $this->postJson('/cert', ['type' => 'current', 'purpose' => 'Duplicate'])->assertUnprocessable();
        $this->get('/verification/'.$token)->assertNotFound();
        $endpoint = '/cert/'.$certificate->id.'/transition';
        $this->postJson($endpoint, ['action' => 'receive'])->assertForbidden();
        $this->actingAs($hr)->postJson($endpoint, ['action' => 'generate'])->assertUnprocessable();
        foreach (['receive', 'acknowledge', 'approve'] as $action) {
            $this->postJson($endpoint, compact('action'))->assertOk();
        }
        $this->postJson($endpoint, ['action' => 'generate', 'basic_pay' => '100', 'pera' => '20', 'mid_year' => '30', 'grand_total_cents' => 999999])->assertOk();
        $certificate->refresh();
        $this->assertSame(12000, $certificate->annual_total_cents);
        $this->assertSame(3000, $certificate->benefit_total_cents);
        $this->assertSame(15000, $certificate->grand_total_cents);
        $this->postJson($endpoint, ['action' => 'generate'])->assertUnprocessable();
        $this->post('/cert/'.$certificate->id.'/complete', ['file' => UploadedFile::fake()->create('approved.pdf', 20, 'application/pdf')])->assertOk();
        $employee->forceFill(['name' => 'Renamed Employee'])->save();
        $employee->position->update(['title' => 'New Position']);
        $this->get('/cert/'.$certificate->id.'/print')->assertOk()->assertSee('Original Employee')->assertSee('Original Position')->assertDontSee('Renamed Employee');
        $this->get('/verification/'.$token)->assertOk()->assertDontSee('Original Employee')->assertDontSee('150.00');
        $fileId = $certificate->files()->first()->private_file_id;
        $this->actingAs($employee)->get('/files/'.$fileId)->assertOk();
        $this->actingAs(User::factory()->create())->get('/files/'.$fileId)->assertForbidden();
        $this->get('/cert/'.$certificate->id.'/print')->assertForbidden();
        $this->assertSame($token, $certificate->fresh()->verification_token);
    }

    public function test_rejection_and_manual_eligibility_are_explicit_and_unsigned_issuance_is_blocked(): void
    {
        $employee = User::factory()->create();
        $hr = User::factory()->create(['role' => 'hr']);
        $this->actingAs($employee)->postJson('/cert', ['type' => 'current', 'purpose' => 'Example', 'employee_id' => $hr->id])->assertForbidden();
        $former = User::factory()->create(['termination_date' => '2025-01-01', 'employee_type' => 'Permanent']);
        $this->actingAs($hr)->postJson('/cert', ['type' => 'manual', 'purpose' => 'Historical record', 'employee_id' => $employee->id])->assertUnprocessable();
        $this->postJson('/cert', ['type' => 'manual', 'purpose' => 'Historical record', 'employee_id' => $former->id])->assertCreated();
        $certificate = EmploymentCertificate::firstOrFail();
        $endpoint = '/cert/'.$certificate->id.'/transition';
        $this->postJson($endpoint, ['action' => 'reject'])->assertUnprocessable();
        $this->postJson($endpoint, ['action' => 'reject', 'reason' => 'Fictional correction required'])->assertOk();
        $this->postJson($endpoint, ['action' => 'receive'])->assertUnprocessable();
        $this->assertSame('Rejected', $certificate->fresh()->status);
        $certificate->update(['status' => 'Approved']);
        $this->postJson($endpoint, ['action' => 'generate'])->assertUnprocessable();
        $this->assertNull($certificate->fresh()->issued_at);
    }
}
