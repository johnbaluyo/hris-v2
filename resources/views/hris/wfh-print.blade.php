@extends('layouts.report')
@section('title', 'WFH consolidated accomplishments')
@section('content')
<h1>WFH consolidated accomplishments</h1><p>{{ $date }}</p>
@forelse($reports as $report)<h2>{{ $report->division_snapshot['name'] }}</h2><p>{{ $report->status }} · Approved submissions: {{ $report->submitted_count }}</p>
<table><thead><tr><th>Employee / position</th><th>Accomplishment / evidence fallback</th></tr></thead><tbody>@foreach($report->lines as $line)<tr><td>{{ $line->snapshot['employee']['name'] }}<br>{{ $line->snapshot['employee']['position'] }}</td><td>{{ $line->snapshot['accomplishment'] }}</td></tr>@endforeach</tbody></table>
@if($report->signed_at)<p class="signature">Signed by {{ $report->signer_snapshot['name'] }} · {{ $report->signed_at->format('d M Y g:i A') }} Manila</p>@endif
@if($report->approved_at)<p>Approved by {{ $report->approver_snapshot['name'] }} · {{ $report->approved_at->format('d M Y g:i A') }} Manila</p>@endif
@if(!$report->signed_at)<p><small>Draft snapshot. Contents are captured again at signature.</small></p>@endif
@empty<p>No approved division reports for this date.</p>@endforelse
@endsection
