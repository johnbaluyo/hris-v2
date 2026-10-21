<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class RebuildAttendance extends Command
{
    protected $signature = 'attendance:rebuild {--from=} {--to=} {--employee=}';

    protected $description = 'Idempotently rebuild attendance from immutable evidence; retains manual overrides and revisions, never posts credits';

    public function handle(AttendanceService $service): int
    {
        $from = $this->option('from') ?: today('Asia/Manila')->subDays(7)->toDateString();
        $to = $this->option('to') ?: today('Asia/Manila')->toDateString();
        if (! is_string($from) || ! is_string($to)) {
            $this->error('Dates must be strings in YYYY-MM-DD format.');

            return self::FAILURE;
        }
        $validator = Validator::make(['from' => $from, 'to' => $to, 'employee' => $this->option('employee')],
            ['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from|before_or_equal:today', 'employee' => 'nullable|integer|exists:users,id']);
        if ($validator->fails() || CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 366) {
            $this->error($validator->errors()->first() ?: 'Use a range of at most one year.');

            return self::FAILURE;
        }
        $count = 0;
        User::whereNotNull('atms_id')->where('role', '!=', 'applicant')->when($this->option('employee'), fn ($q, $id) => $q->whereKey($id))
            ->chunkById(100, function ($employees) use ($from, $to, $service, &$count) {
                foreach ($employees as $employee) {
                    for ($date = CarbonImmutable::parse($from); $date->toDateString() <= $to; $date = $date->addDay()) {
                        $service->rebuild($employee, $date->toDateString());
                        $count++;
                    }
                }
            });
        $this->info("Reviewed {$count} employee/date combinations. Manual overrides retained; no ledger effects.");

        return self::SUCCESS;
    }
}
