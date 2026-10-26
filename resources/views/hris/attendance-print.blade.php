<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily Time Record{{ isset($employee) ? ' - '.$employee->name : ' - Bulk Print' }}</title>
    <style>
        body { margin: 0cm; font-family: Arial, sans-serif; color: #000; }
        table { border-collapse: collapse; }
        tr.noBorder td { border: 0; }
        td { padding: 3px; }
        .print-button { position: fixed; top: 12px; right: 12px; z-index: 10; padding: 8px 16px; }
        .dtr-table { width: 100%; border: 3px solid #000; font-size: 15px; }
        .dtr-table td { border: 1px solid #000; }
        .statistics-table { border: 1px solid #000; }
        .statistics-table td { border: 1px solid #000; }
        .text-center { text-align: center; }
        footer { break-after: page; page-break-after: always; }
        footer:last-of-type { break-after: auto; page-break-after: auto; }
        @page { size: A4 portrait; margin: 5mm; }
        @media print { body { zoom: 80%; } .no-print { display: none !important; } }
    </style>
</head>
<body>
@php
    $printReports = isset($reports)
        ? collect($reports)
        : collect([compact('employee', 'rows', 'from', 'to', 'remarks', 'reportDays', 'statistics')]);
@endphp
@foreach($printReports as $printReport)
    @php
        $employee = $printReport['employee'];
        $rows = $printReport['rows'];
        $from = $printReport['from'];
        $to = $printReport['to'];
        $reportDays = $printReport['reportDays'];
        $statistics = $printReport['statistics'];
        $start = \Carbon\CarbonImmutable::parse($from);
        $end = \Carbon\CarbonImmutable::parse($to);
        $employeeName = trim(($employee->first_name ?? '').' '.(!empty($employee->middle_name) ? mb_substr($employee->middle_name, 0, 1).'. ' : '').($employee->last_name ?? ''));
        $employeeName = $employeeName !== '' ? $employeeName : $employee->name;
        $workweek = $employee->workweek ?? $rows->first()?->workweek ?? 5;
        $formatTime = static fn ($time): string => $time ? \Carbon\CarbonImmutable::parse($time)->format('g:i a') : '';
        $formatMinutes = static function ($minutes): string {
            $minutes = max(0, (int) ($minutes ?? 0));
            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        };
        $totals = ['worked' => 0, 'late' => 0, 'late_count' => 0, 'undertime' => 0, 'undertime_count' => 0, 'absent' => 0, 'overtime' => 0];
        foreach ($statistics as $week) {
            foreach ($totals as $key => $unused) $totals[$key] += $week[$key];
        }
    @endphp
    @if($loop->first)<button type="button" class="print-button no-print" onclick="window.print()">Print</button>@endif
    <div style="font-family:Arial">
        <div align="center" style="font-size:20px">
            <b>PHILIPPINE STATISTICAL RESEARCH AND TRAINING INSTITUTE</b><br>
            Daily Time Record From <b>{{ $start->format('F') }} 1 to {{ $end->day }}, {{ $end->year }}</b><br><br>
        </div>
        <div align="left">
            Employee Name: <b>{{ $employeeName }}</b><br><br>
            Division:<b> {{ $employee->division?->acronym }}</b><br>
            <div align="right">CS Form No. 48</div>
            <b>{{ $workweek }} days</b>
        </div>
        <br><br>
        <table border="3" class="dtr-table">
            <thead><tr><td><b>Date</b></td><td><b>In 1</b></td><td><b>Out 1</b></td><td><b>In 2</b></td><td><b>Out 2</b></td><td><b>OT in</b></td><td><b>OT out</b></td><td><b>Reg. Hrs.</b></td><td><b>OT Hrs.</b></td><td><b>OB Per.</b></td></tr></thead>
            <tbody>
            @foreach($reportDays as $day)
                @php
                    $date = $day['date']; $attendance = $day['attendance']; $holiday = $day['holiday'];
                    $business = $day['business']; $leave = $day['leave']; $cto = $day['cto'];
                    $beforeEmployment = $employee->date_hired && $date->lt($employee->date_hired);
                    $weekend = $workweek === 4 ? $date->isFriday() || $date->isWeekend() : $date->isWeekend();
                    $dateLabel = $date->format('F d, Y').($date->isWeekend() ? ' '.$date->format('D') : '');
                    $obTypes = [1 => '(WORK-RELATED)', 2 => '(MEDICAL)', 3 => '(BEREAVEMENT)'];
                    $fullDayCto = $cto && (int) $cto['date']->minutes >= ($workweek === 4 ? 600 : 480);
                    $fullDayBusiness = $business && (!$attendance || ($business->go_directly && !$business->return_to_office));
                    $holidayType = strtoupper((string) ($holiday?->type ?? ''));
                    $holidayLabel = $holiday ? ($holidayType === 'WORK SUSPENSION' ? 'WORK SUSPENSION'.($holiday->suspension_time ? ' (at '.$formatTime($holiday->suspension_time).')' : '') : trim($holidayType.' HOLIDAY'.($holiday->name ? ' ('.$holiday->name.')' : ''))) : null;
                    $fullLabel = $fullDayCto ? 'CTO' : ($fullDayBusiness ? trim('OFFICIAL BUSINESS '.($obTypes[$business->type] ?? '')) : ($leave ? strtoupper($leave->type?->name ?? 'LEAVE') : (!$attendance ? $holidayLabel : null)));
                    $notes = collect([$business && !$fullDayBusiness ? 'OB' : null, $holiday && $attendance ? '**'.($holidayLabel ?? 'HOLIDAY') : null, $cto && !$fullDayCto ? 'CTO ('.$formatMinutes($cto['date']->minutes).' hours)' : null, $attendance?->notes])->filter()->implode(' ');
                    $marker = collect([$attendance && $attendance->late_minutes > 0 ? 'LT' : null, $attendance && $attendance->undertime_minutes > 0 ? 'UT' : null])->filter()->implode('+');
                @endphp
                <tr>
                    <td>{{ $dateLabel }}</td>
                    @if($beforeEmployment)
                        @for($column = 0; $column < 9; $column++)<td></td>@endfor
                    @elseif($fullLabel)
                        <td colspan="4" class="text-center"><center>{{ $fullLabel }}</center></td><td></td><td></td><td></td><td></td><td></td>
                    @elseif(!$attendance)
                        @if($weekend)
                            @for($column = 0; $column < 9; $column++)<td></td>@endfor
                        @else
                            <td colspan="4" class="text-center"><center>NO RECORD</center></td><td></td><td></td><td></td><td></td><td>{{ $notes }}</td>
                        @endif
                    @else
                        <td>{{ $formatTime($attendance->time_in) }}</td><td>{{ $formatTime($attendance->lunch_out) }}</td><td>{{ $formatTime($attendance->lunch_in) }}</td><td>{{ $formatTime($attendance->time_out) }}</td>
                        <td>{{ $formatTime($attendance->ot_in) }}</td><td>{{ $formatTime($attendance->ot_out) }}</td>
                        <td>{{ $attendance->worked_minutes !== null ? $formatMinutes($attendance->worked_minutes) : '' }}{{ $marker ? ' '.$marker : '' }}</td>
                        <td>{{ $attendance->overtime_minutes ? $formatMinutes($attendance->overtime_minutes) : '' }}</td><td>{{ $notes }}</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
        <br>
        Daily Time Record Statistics:
        <table border="1" class="statistics-table">
            <thead><tr><td></td>@for($week = 1; $week <= 6; $week++)<td>Week No. {{ $week }}</td>@endfor<td>Total</td></tr></thead>
            <tbody>
            @foreach([['Regular Hours:', 'worked', true], ['Hours Tardy:', 'late', true], ['Hours Undertime:', 'undertime', true], ['Time Tardy:', 'late_count', false], ['Times Undertime:', 'undertime_count', false]] as [$label, $key, $asTime])
                <tr><td>{{ $label }}</td>@for($week = 1; $week <= 6; $week++)<td>{{ $asTime ? $formatMinutes($statistics[$week][$key]) : $statistics[$week][$key] }}</td>@endfor<td>{{ $asTime ? $formatMinutes($totals[$key]) : $totals[$key] }}</td></tr>
            @endforeach
                <tr><td>Halfday:</td>@for($column = 0; $column < 7; $column++)<td></td>@endfor</tr>
                <tr><td>Times Absent:</td>@for($week = 1; $week <= 6; $week++)<td>{{ $statistics[$week]['absent'] }}</td>@endfor<td>{{ $totals['absent'] }}</td></tr>
                <tr><td>Overtime Hours:</td>@for($week = 1; $week <= 6; $week++)<td>{{ $formatMinutes($statistics[$week]['overtime']) }}</td>@endfor<td>{{ $formatMinutes($totals['overtime']) }}</td></tr>
            </tbody>
        </table>
        <br><br><br>
        I certify on my honor that the above is a true and correct report of the hours of work performed, record of which was made daily at the time of arrival at and departure from office.
        <br><br><br>
        <table align="center"><tr>
            <td>______________________<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Employee Signature</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
            <td>______________________<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Validated By:</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
            <td>______________________<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Supervisor:</td>
        </tr></table>
        <div hidden>{{ $rows->pluck('policy_version')->filter()->unique()->implode(' ') }}</div>
    </div>
    <footer></footer>
@endforeach
</body>
</html>
