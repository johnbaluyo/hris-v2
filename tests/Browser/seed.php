<?php

use App\Models\Division;
use App\Models\Holiday;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\RawPunch;
use App\Models\User;
use App\Services\EmploymentHistory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('testing') || config('database.default') !== 'sqlite' || basename(config('database.connections.sqlite.database')) !== 'browser-test.sqlite') {
    throw new RuntimeException('Browser fixtures may run only against the isolated browser-test.sqlite database.');
}

$division = Division::create(['name' => 'Example Research Division', 'acronym' => 'ERD']);
$position = Position::create(['title' => 'Example Analyst', 'division_id' => $division->id, 'classification' => 'Staff']);
$adminPosition = Position::create(['title' => 'Example Administrator Position', 'division_id' => $division->id, 'classification' => 'Supervisory']);
$admin = User::factory()->create(['name' => 'Example Administrator', 'email' => 'browser-admin@example.test',
    'password' => 'Browser-test-password-123!', 'role' => 'system_admin', 'division_id' => $division->id,
    'position_id' => $adminPosition->id, 'date_hired' => '2020-01-01', 'atms_id' => '9001']);
app(EmploymentHistory::class)->backfill($admin, $admin->id);
foreach (['staff.manage', 'pds.read', 'pds.write', 'attendance.read', 'attendance.correct', 'attendance.export', 'recruitment.manage', 'certificates.manage', 'recognition.manage'] as $ability) {
    DB::table('ability_grants')->insert(['user_id' => $admin->id, 'ability' => $ability]);
}
for ($index = 1; $index <= 15; $index++) {
    $employeePosition = $index === 1 ? $position : Position::create(['title' => 'Example Analyst '.$index, 'division_id' => $division->id, 'classification' => 'Staff']);
    $employee = User::factory()->create(['name' => 'Example Employee '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        'first_name' => 'Example', 'last_name' => 'Employee '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        'email' => 'employee'.$index.'@example.test', 'employee_type' => 'Permanent', 'division_id' => $division->id,
        'position_id' => $employeePosition->id, 'date_hired' => '2020-01-01', 'atms_id' => (string) (9100 + $index)]);
    PositionAssignment::create(['user_id' => $employee->id, 'position_id' => $employeePosition->id, 'actor_id' => $admin->id, 'assume_date' => '2020-01-01']);
    app(EmploymentHistory::class)->backfill($employee, $admin->id);
    if ($index === 2) {
        RawPunch::create(['atms_id' => $employee->atms_id, 'punched_at' => today('Asia/Manila')->subDay()->format('Y-m-d').' 08:00:59', 'device' => 'EXAMPLE-READER']);
    }
}
Position::create(['title' => 'Example New Employee Position', 'division_id' => $division->id, 'classification' => 'Staff']);
Holiday::create(['name' => 'Example Agency Holiday', 'holiday_date' => '2026-10-22', 'type' => 'Regular']);
User::factory()->create(['name' => 'Example Applicant', 'email' => 'browser-applicant@example.test', 'password' => 'Browser-test-password-123!', 'role' => 'applicant', 'employee_type' => 'Applicant']);
Position::create(['title' => 'Example Recruitment Position', 'division_id' => $division->id, 'classification' => 'Staff']);
User::factory()->create(['name' => 'Example Division Chief', 'email' => 'browser-chief@example.test', 'password' => 'Browser-test-password-123!', 'role' => 'division_chief', 'division_id' => $division->id, 'employee_type' => 'Permanent']);
