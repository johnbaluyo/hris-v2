@extends('layouts.report')
@section('title', 'Provisional attendance statistics')
@section('content')
<h1>Provisional attendance statistics</h1><p>{{ $data['from'] }} through {{ $data['to'] }} · Recent hires: {{ $data['include_recent_hires'] ? 'included' : 'employed by coverage start' }}</p><p>Selected leave types: {{ implode(', ', $data['leave_type_ids']) ?: 'None' }}. Sorted by approved leave days, then numeric late + undertime minutes, then employee ID. This report does not select award winners.</p>
<table><thead><tr><th>Employee</th><th>Approved leave days</th><th>Late minutes</th><th>Undertime minutes</th><th>Recorded days</th><th>Needs review</th></tr></thead><tbody>@forelse($rows as $row)<tr><td>{{ $row['employee']['name'] }}</td><td>{{ $row['leave_days'] }}</td><td>{{ $row['late_minutes'] }}</td><td>{{ $row['undertime_minutes'] }}</td><td>{{ $row['recorded_days'] }}</td><td>{{ $row['review_days'] }}</td></tr>@empty<tr><td colspan="6">No eligible employees.</td></tr>@endforelse</tbody></table>
@endsection
