<?php

namespace App\Console\Commands;

use App\Models\RawPunch;
use App\Models\User;
use App\Support\AttendanceIdentity;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MatchAttendanceIdentity extends Command
{
    protected $signature = 'attendance:match-identity {employee} {atms_id} {--from=} {--to=} {--reason=}';

    protected $description = 'Explicit operator reconciliation of unmatched raw evidence to an employee; requires dates and reason and preserves source values';

    public function handle(): int
    {
        $data = ['employee' => $this->argument('employee'), 'atms_id' => $this->argument('atms_id'), 'from' => $this->option('from'), 'to' => $this->option('to'), 'reason' => $this->option('reason')];
        $validator = Validator::make($data, ['employee' => 'required|integer|exists:users,id', 'atms_id' => 'required|string|regex:/^[0-9]{1,50}$/D',
            'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'reason' => 'required|string|max:1000']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $values = $validator->validated();
        $identity = AttendanceIdentity::normalize($values['atms_id']);
        $count = DB::transaction(function () use ($values, $identity) {
            $employee = User::whereKey($values['employee'])->lockForUpdate()->firstOrFail();
            $query = RawPunch::whereNull('user_id')->where('atms_id', $identity)->where('punched_at', '>=', $values['from'].' 00:00:00')
                ->where('punched_at', '<', CarbonImmutable::parse($values['to'])->addDay()->toDateString().' 00:00:00');
            $count = 0;
            foreach ($query->orderBy('id')->lazyById(100) as $punch) {
                $before = $punch->attributesToArray();
                $punch->update(['user_id' => $employee->id]);
                Audit::record('attendance.identity-reconciled', $punch, null, $before, $values['reason']);
                $count++;
            }

            return $count;
        });
        $this->info("Matched {$count} previously unmatched punches. Rebuild the relevant employee/date range to update DTR results.");

        return self::SUCCESS;
    }
}
