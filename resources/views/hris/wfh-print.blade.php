<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reports->count() > 1 ? 'Agency' : 'Division' }} WFH Consolidated Report</title>
    <style>body{font-family:Arial,sans-serif;font-size:12px}.title{text-align:center;margin-bottom:8px}table{width:100%;border-collapse:collapse;margin-top:8px}th,td{border:1px solid #333;padding:6px;vertical-align:top}th{background:#f0f0f0}.division-block{margin-bottom:28px;page-break-inside:avoid}.accomp-col{width:50%}.signature{margin-top:18px;text-align:left}.agency-signature{margin-top:40px;text-align:center}.bullet-list{margin:0;padding-left:1rem}.no-print{position:fixed;right:12px;top:12px}@media print{.no-print{display:none}}</style>
</head>
<body>
<button class="no-print" onclick="window.print()">Print</button>
<div class="title"><h3 style="margin:0">Work From Home Consolidated Accomplishment Report</h3><p style="margin:4px 0">Date: {{ \Carbon\CarbonImmutable::parse($date)->format('F j, Y') }}</p></div>
@forelse($reports as $report)
    <div class="division-block">
        <h4 style="margin-bottom:0">Division: {{ $report->division_snapshot['name'] ?? '' }}</h4>
        <table><thead><tr><th>Name</th><th>Position</th><th class="accomp-col">Accomplishments</th></tr></thead><tbody>
        @foreach($report->lines as $line)
            @php($items = preg_split('/\r\n|\r|\n/', trim((string)($line->snapshot['accomplishment'] ?? ''))))
            <tr><td>{{ $line->snapshot['employee']['name'] ?? '' }}</td><td>{{ $line->snapshot['employee']['position'] ?? '' }}</td><td>@if(count(array_filter($items)))<ul class="bullet-list">@foreach($items as $item)@if(trim($item)!=='')<li>{{ $item }}</li>@endif @endforeach</ul>@else no record @endif</td></tr>
        @endforeach
        </tbody></table>
        <div class="signature"><strong>Validated by:</strong><br><br><strong>{{ mb_strtoupper($report->signer_snapshot['name'] ?? '') }}</strong><br>{{ $report->signer_snapshot['position'] ?? 'Division Chief' }}, {{ $report->division_snapshot['name'] ?? '' }}</div>
    </div>
@empty
    <p>No approved division reports for this date.</p>
@endforelse
@if($reports->count() > 1)
    @php($approver = $reports->first(fn($report) => $report->approver_snapshot)['approver_snapshot'] ?? null)
    <div class="agency-signature"><strong>Approved by:</strong><br><br><strong>{{ mb_strtoupper($approver['name'] ?? '') }}</strong><br>{{ $approver['position'] ?? 'Executive Director III / Head of Agency' }}</div>
@endif
<script>window.onload=()=>window.print()</script>
</body>
</html>
