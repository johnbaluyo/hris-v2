@extends('layouts.hris')
@section('title', 'Division learning plan')
@section('actions')<button class="btn btn-primary" data-print>Print</button>@endsection
@section('content')
<h2 class="h5">{{ $plan->division->name }} - {{ $plan->plan_year }}</h2>
<table class="table table-bordered"><thead><tr><th>Employee</th><th>Proposal</th><th>Training / intervention</th><th>Free</th><th class="text-end">Budget</th></tr></thead><tbody>@foreach($plan->lines as $line)<tr><td>{{ $line->snapshot['employee'] ?? '-' }}</td><td>{{ $line->snapshot['proposal'] ?? '-' }}</td><td>{{ $line->snapshot['title'] ?? '-' }}</td><td>{{ $line->is_free ? 'Yes' : 'No' }}</td><td class="text-end">{{ $line->budget }}</td></tr>@endforeach</tbody><tfoot><tr><th colspan="4">Total ({{ $plan->staff_count }} staff)</th><th class="text-end">{{ $plan->total_budget }}</th></tr></tfoot></table>
@endsection
