<?php

namespace Tests\Feature\Hris;

use App\Models\Division;
use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\Position;
use App\Models\SalaryGrade;
use App\Models\User;
use App\Services\Payroll\PayrollCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: User} */
    private function payrollActors(): array
    {
        $division = Division::create(['name' => 'Finance Test Division']);
        $position = Position::create(['division_id' => $division->id, 'title' => 'Example Analyst', 'classification' => 'Staff', 'salary_grade' => 10]);
        $employee = User::factory()->create(['division_id' => $division->id, 'position_id' => $position->id, 'step_increment' => 2, 'employee_type' => 'Permanent']);
        $hr = User::factory()->create(['role' => 'hr']);
        SalaryGrade::create(['grade' => 10, 'step' => 2, 'monthly_salary' => 40000, 'effective_from' => '2026-01-01']);

        return [$employee, $hr];
    }

    public function test_payroll_is_restricted_calculated_on_server_and_immutable_after_finalization(): void
    {
        [$employee, $hr] = $this->payrollActors();
        $this->actingAs($employee)->get('/hr/payroll')->assertForbidden();
        $response = $this->actingAs($hr)->postJson('/hr/payroll', ['type' => 'Regular', 'payroll_number' => 'PAY-2026-10-A', 'fund_cluster' => '01',
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31'])->assertCreated();
        $payroll = Payroll::findOrFail($response->json('id'));
        $this->postJson('/hr/payroll/'.$payroll->id.'/lines', ['user_id' => $employee->id, 'basic_salary' => 1, 'pera' => 2000,
            'rata' => 1000, 'other_compensation' => 500, 'statutory_deductions' => 2500, 'other_deductions' => 500])->assertOk()
            ->assertJsonPath('totals.gross', '43500.00')->assertJsonPath('totals.net', '40500.00');
        $line = PayrollLine::firstOrFail();
        $this->assertSame('40000.00', $line->basic_salary);
        $this->postJson('/hr/payroll/'.$payroll->id.'/finalize')->assertOk();
        $this->postJson('/hr/payroll/'.$payroll->id.'/lines', ['user_id' => $employee->id])->assertStatus(409);
        $employee->update(['name' => 'Changed after payroll']);
        $this->assertNotSame($employee->fresh()->name, $line->fresh()->employee_snapshot['name']);
        $this->get('/hr/payroll/'.$payroll->id.'/export')->assertOk()->assertDownload('payroll-PAY-2026-10-A.xls');
        $this->get('/hr/payroll/'.$payroll->id.'/payslips/print')->assertOk()->assertSee('P A Y S L I P')->assertSee('Pay Week 4');
    }

    public function test_finalized_csw_payroll_line_exports_legacy_disbursement_voucher(): void
    {
        [$employee, $hr] = $this->payrollActors();
        $payroll = Payroll::create(['type' => 'CSW', 'payroll_number' => 'CSW-01', 'fund_cluster' => '01', 'period_start' => '2026-10-01', 'period_end' => '2026-10-15', 'status' => 'Finalized', 'created_by' => $hr->id]);
        $line = PayrollLine::create(['payroll_id' => $payroll->id, 'user_id' => $employee->id, 'employee_snapshot' => ['name' => 'Juan Dela Cruz', 'position' => 'Project Development Officer III', 'division' => 'Training Division'], 'basic_salary' => 30000, 'pera' => 0, 'rata' => 0, 'other_compensation' => 0, 'statutory_deductions' => 2400, 'other_deductions' => 0, 'late_minutes' => 0, 'undertime_minutes' => 0, 'absence_days' => 0, 'gross' => 30000, 'total_deductions' => 2400, 'net' => 27600, 'calculation_inputs' => []]);

        $this->actingAs($hr)->get('/hr/payroll/'.$payroll->id.'/lines/'.$line->id.'/voucher/export')
            ->assertOk()->assertDownload('DV_Juan_Dela_Cruz_'.now()->format('Y-m-d').'.xls');
    }

    public function test_weekly_allocation_always_reconciles_to_net(): void
    {
        $result = app(PayrollCalculator::class)->regular(['basic_salary' => 10003, 'pera' => 2000, 'rata' => 1000, 'statutory_deductions' => 3, 'other_deductions' => 0]);
        $this->assertEqualsWithDelta((float) $result['net'], array_sum(array_map('floatval', $result['weekly'])), .001);
    }
}
