<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ match($kind) { 'ob' => 'Official Business Form', 'overtime' => 'Request for Authority to Enter Office Premises', 'cto' => 'CTO Form', default => 'Application for Leave' } }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;color:#000;font-size:12px}.sheet{width:100%;max-width:1000px;margin:0 auto;padding:12px}.form{width:100%;border-collapse:collapse}.form td,.form th{border:1px solid #000;padding:5px;vertical-align:top}.no-border td{border:0}.center{text-align:center}.right{text-align:right}.gray{background:#c5c5c5}.small{font-size:10px}.line{display:inline-block;min-width:210px;border-bottom:1px solid #000;text-align:center}.checks{font-family:DejaVu Sans,Arial,sans-serif}.page-break{page-break-after:always}.print-button{position:fixed;right:12px;top:12px;padding:8px 16px}.cto-title{font-family:"Arial Narrow",Arial,sans-serif}.cto-form td,.cto-form th{text-align:center;vertical-align:middle;height:35px}.signatures{height:90px!important;vertical-align:bottom!important}@media print{body{zoom:92%}.sheet{padding:0;max-width:none}.no-print{display:none!important}@page{margin:8mm}}
    </style>
</head>
<body>
@php
    $employee = $record->employee;
    $name = trim(($employee->first_name ?? '').' '.($employee->middle_name ?? '').' '.($employee->last_name ?? '')) ?: $employee->name;
    $formatDate = static fn($date, $format = 'M d, Y') => $date ? \Carbon\CarbonImmutable::parse($date)->format($format) : '';
    $formatTime = static fn($time) => $time ? \Carbon\CarbonImmutable::parse($time)->format('g:i a') : '';
    $minutes = static fn($value) => sprintf('%02d:%02d', intdiv((int)$value, 60), (int)$value % 60);
@endphp
<button class="print-button no-print" onclick="window.print()">Print</button>

@if($kind === 'ob')
    @for($copy = 0; $copy < 2; $copy++)
    <section class="sheet {{ $copy === 0 ? 'page-break' : '' }}">
        <table class="form">
            <tr><td style="width:25%"><b>FAD Form No. 01</b></td><td colspan="3" class="center"><b>OFFICIAL BUSINESS (OB) FORM</b></td></tr>
            <tr><td colspan="4" class="center"><b>PHILIPPINE STATISTICAL RESEARCH AND TRAINING INSTITUTE</b><br>Quezon City</td></tr>
            <tr><td colspan="2"><b>Printed Name of Employee:</b> {{ $name }}</td><td colspan="2"><b>Position:</b> {{ $employee->position?->title }}</td></tr>
            <tr><td colspan="2"><b>Division:</b> {{ $employee->division?->name }}</td><td><b>Date Filed:</b> {{ $formatDate($record->filed_on) }}</td><td><b>Date of OB:</b> {{ $formatDate($record->business_date) }}</td></tr>
            <tr><td colspan="2" class="center"><i><b>Itinerary/Destination</b></i></td><td colspan="2" class="center"><i><b>Time</b></i></td></tr>
            <tr><td rowspan="2" class="center" style="width:24%"><b>From</b><br><br>{{ $record->origin }}<br><br></td><td rowspan="2" class="center"><b>To</b><br><br>{{ $record->destination }}<br><br></td><td class="center"><b>Departure</b><br>{{ $formatTime($record->departure_time) }}</td><td class="center"><b>Expected Return</b><br>{{ $formatTime($record->return_time) }}</td></tr>
            <tr><td class="checks">{{ $record->go_directly ? '☒' : '☐' }} Go directly to destination<br>{{ !$record->go_directly ? '☒' : '☐' }} Report first to the office</td><td class="checks">{{ $record->return_to_office ? '☒' : '☐' }} Return to office<br>{{ !$record->return_to_office ? '☒' : '☐' }} Not expected to return</td></tr>
            <tr><td colspan="4" style="height:70px"><b>Purpose:</b> {{ $record->purpose }}</td></tr>
            <tr><td colspan="2" class="center" style="height:100px"><br><br><span class="line">{{ strtoupper($name) }}</span><br>Signature over Printed Name of Employee</td><td colspan="2" class="center"><b>Approved by:</b><br><br><br><span class="line"></span><br>Signature over Printed Name of Officer</td></tr>
            <tr><td colspan="4" class="center gray"><b>TO BE FILLED BY THE AGENCY OR COMPANY WHERE BUSINESS IS TRANSACTED</b></td></tr>
            <tr><td colspan="4" class="center" style="height:150px"><b>CERTIFICATE OF APPEARANCE</b><br><br>This is to certify that the person whose name is shown above personally appeared in this office as indicated and for the purpose stated.<br><br><br><span class="line"></span><br>Signature over Printed Name / Date</td></tr>
        </table>
        <div class="small"><b>INSTRUCTIONS:</b><br>1. Employees are required to accomplish an Official Business (OB) form at least <b>one (1) to three (3) days</b> before the scheduled official business. Employees who attended trainings or seminars are required to submit a copy of their certificate of attendance.<br>2. An OB is applicable only for <b>one (1) day</b> regardless of the duration and/or start/end time of the business, except warranted.<br>3. Failure to submit the duly approved OB forms or certificate of attendance/appearance shall be a ground for deduction from the vacation leave credits.<br>4. Employees must ensure that the Certificate of Appearance in this form is duly signed by the agency or company where business is transacted.</div>
    </section>
    @endfor
@elseif($kind === 'overtime')
    <section class="sheet">
        <table class="form">
            <tr><td style="width:25%"><b>FAD Form No. 03</b></td><td colspan="3" class="center"><b>REQUEST FOR AUTHORITY TO<br>ENTER OFFICE PREMISES</b></td></tr>
            <tr><td colspan="4" class="center"><b>PHILIPPINE STATISTICAL RESEARCH AND TRAINING INSTITUTE</b><br>Quezon City</td></tr>
            <tr><td colspan="2"><b>Name of Employee/CSW:</b> {{ $name }}</td><td colspan="2"><b>Date of Filing:</b> {{ $formatDate($record->filed_on, 'M-d-Y') }}</td></tr>
            <tr><td colspan="2"><b>Division:</b> {{ $employee->division?->name }}</td><td colspan="2"><b>Date/s and Time of Entry to the Office Premises:</b><br>@foreach($record->dates as $date){{ $formatDate($date->overtime_date) }}{{ !$loop->last ? ', ' : '' }}@endforeach at {{ $formatTime($record->start_time) }}</td></tr>
            <tr><td colspan="2"><b>Position:</b> {{ $employee->position?->title }}</td><td colspan="2"><b>Purpose:</b> {{ $record->purpose }}</td></tr>
            <tr><td colspan="4" class="center gray"><b>DETAILS OF APPLICATION</b></td></tr>
            <tr><td colspan="2" class="checks">{{ $record->day_type === 1 ? '☒' : '☐' }} Saturday/Sunday<br>{{ $record->day_type === 2 ? '☒' : '☐' }} Legal Holiday<br>{{ $record->day_type === 3 ? '☒' : '☐' }} Declared Non-Working Holiday</td><td colspan="2" class="checks">{{ $record->overtime_type === 1 ? '☒' : '☐' }} With overtime pay<br>{{ $record->overtime_type === 2 ? '☒' : '☐' }} Without overtime pay<br>{{ $record->overtime_type === 3 ? '☒' : '☐' }} With compensatory time off<br>Requested hours: {{ $minutes($record->requested_minutes) }}</td></tr>
            <tr><td colspan="2" class="center signatures"><span class="line">{{ strtoupper($name) }}</span><br>Signature over Printed Name</td><td colspan="2" class="center signatures"><b>Approved by:</b><br><br><span class="line"></span><br>Executive Director</td></tr>
            <tr><td colspan="4"><b>Status:</b> {{ is_string($status) ? $status : $status?->status }}</td></tr>
        </table>
    </section>
@elseif($kind === 'cto')
    @for($copy = 0; $copy < 2; $copy++)
    <section class="sheet cto-title {{ $copy === 0 ? 'page-break' : '' }}">
        <div class="right"><b>FAD Form No. 04</b></div>
        <div class="center"><b>PHILIPPINE STATISTICAL RESEARCH AND TRAINING INSTITUTE</b><h2>Compensatory Time - Off (CTO) Form</h2></div>
        <p>Name: <b>{{ strtoupper($name) }}</b><br>Position/Division: {{ $employee->position?->title }} / {{ $employee->division?->name }}</p>
        <table class="form cto-form">
            <thead><tr><th>AS OF</th><th>EARNED<br>COCs/BALANCE</th><th>DATE OF CTO</th><th>USED<br>COCs</th><th>REMAINING<br>COCs</th><th>REMARKS</th><th>EMPLOYEE<br>SIGNATURE</th></tr></thead>
            <tbody>
            @foreach($record->dates as $date)
                <tr><td>{{ $formatDate($record->filed_on) }}</td><td>{{ $minutes($record->balance_snapshot_minutes) }}</td><td>{{ $formatDate($date->cto_date) }}<br>{{ $date->portion }}</td><td>{{ $minutes($date->minutes) }}</td><td>{{ $minutes(max(0, $record->balance_snapshot_minutes - $record->total_minutes)) }}</td><td>{{ $status }}</td><td></td></tr>
            @endforeach
            </tbody>
        </table>
        <div style="display:flex;justify-content:space-between;margin-top:45px;text-align:center"><div>Certified by:<br><br><span class="line"></span><br>Human Resource Management Officer</div><div>Approved by:<br><br><span class="line"></span><br>Authorized Official</div></div>
    </section>
    @endfor
@else
    @php
        $detailLabels = ['1'=>'Within the Philippines','2'=>'Abroad','3'=>'In Hospital','4'=>'Out Patient','5'=>"Completion of Master's Degree",'6'=>'BAR/Board Examination Review','7'=>'Monetization of Leave Credits','8'=>'Terminal Leave'];
        $vl = (float)($balances[1] ?? 0); $sl = (float)($balances[2] ?? 0);
    @endphp
    <section class="sheet">
        <div class="right"><i>Civil Service Form No. 6<br>Revised 2020</i></div>
        <div class="center"><b>Republic of the Philippines</b><br><b>PHILIPPINE STATISTICAL RESEARCH AND TRAINING INSTITUTE</b><h2>APPLICATION FOR LEAVE</h2></div>
        <table class="form">
            <tr><td><b>1. OFFICE/DEPARTMENT</b><br>{{ $employee->division?->name }}</td><td colspan="2"><b>2. NAME:</b><br>{{ strtoupper($employee->last_name ?? '') }}, {{ strtoupper($employee->first_name ?? $employee->name) }} {{ strtoupper($employee->middle_name ?? '') }}</td></tr>
            <tr><td><b>3. DATE OF FILING</b><br>{{ $formatDate($record->filed_on) }}</td><td><b>4. POSITION</b><br>{{ $employee->position?->title }}</td><td><b>5. SALARY</b><br></td></tr>
            <tr><td colspan="3" class="center gray"><b>6. DETAILS OF APPLICATION</b></td></tr>
            <tr><td colspan="2"><b>6.A TYPE OF LEAVE TO BE AVAILED OF</b><br><br><span class="checks">☒</span> {{ $record->type?->name }}<br><br><span class="checks">☐</span> Others (Specify)</td><td><b>6.B DETAILS OF LEAVE</b><br><br>{{ $detailLabels[$record->detail_code] ?? '' }}{{ $record->details ? ': '.$record->details : '' }}</td></tr>
            <tr><td colspan="2"><b>6.C NUMBER OF WORKING DAYS APPLIED FOR</b><br><br>{{ $record->requested_days }} day(s)<br><br><b>INCLUSIVE DATES</b><br>{{ $record->dates->pluck('leave_date')->map(fn($date) => $formatDate($date))->join(', ') }}</td><td><b>6.D COMMUTATION</b><br><br><span class="checks">{{ $record->commutation === 'not_requested' ? '☒' : '☐' }}</span> Not Requested<br><span class="checks">{{ $record->commutation === 'requested' ? '☒' : '☐' }}</span> Requested<br><br><br><span class="line">{{ strtoupper($name) }}</span><br>Signature of Applicant</td></tr>
            <tr><td colspan="3" class="center gray"><b>7. DETAILS OF ACTION ON APPLICATION</b></td></tr>
            <tr><td colspan="2"><b>7.A CERTIFICATION OF LEAVE CREDITS</b><br><br><table class="form"><tr><th></th><th>Vacation Leave</th><th>Sick Leave</th></tr><tr><td>Total Earned</td><td>{{ number_format($vl,3) }}</td><td>{{ number_format($sl,3) }}</td></tr><tr><td><i>Less this application</i></td><td>{{ $record->leave_type_id === 1 ? $record->requested_days : '' }}</td><td>{{ $record->leave_type_id === 2 ? $record->requested_days : '' }}</td></tr><tr><td>Balance</td><td>{{ number_format(max(0,$vl-($record->leave_type_id===1?(float)$record->requested_days:0)),3) }}</td><td>{{ number_format(max(0,$sl-($record->leave_type_id===2?(float)$record->requested_days:0)),3) }}</td></tr></table><br><div class="center"><span class="line"></span><br>Authorized Officer</div></td><td><b>7.B RECOMMENDATION</b><br><br><span class="checks">{{ $status === 'Approved' ? '☒' : '☐' }}</span> For approval<br><span class="checks">{{ str_contains((string)$status, 'Disapproved') ? '☒' : '☐' }}</span> For disapproval due to:<br>{{ $record->recommendation_reason }}<br><br><div class="center"><span class="line"></span><br>Authorized Officer</div></td></tr>
            <tr><td><b>7.C APPROVED FOR:</b><br><br>{{ $record->paid_days ?? $record->requested_days }} day(s) with pay<br>{{ $record->unpaid_days ?? 0 }} day(s) without pay<br>Others</td><td colspan="2"><b>7.D DISAPPROVED DUE TO:</b><br><br>{{ str_contains((string)$status, 'Disapproved') ? ($record->recommendation_reason ?: $status) : '' }}</td></tr>
            <tr><td colspan="3" class="center" style="height:100px"><br><br><span class="line"></span><br><b>Authorized Official</b></td></tr>
        </table>
    </section>
@endif
</body>
</html>
