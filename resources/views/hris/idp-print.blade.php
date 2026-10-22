@extends('layouts.hris')
@section('title', 'Individual development plan')
@section('actions')<button class="btn btn-primary" data-print>Print</button>@endsection
@section('content')
<h2 class="h5">{{ $idp->employee->name }} - {{ $idp->plan_year }}</h2><p>{{ $idp->employee->position?->title }} / {{ $idp->employee->division?->name }}</p>
<table class="table table-bordered"><thead><tr><th>Position requirement</th><th>Proposal</th><th>Training / intervention</th></tr></thead><tbody>@foreach($idp->items as $item)<tr><td>{{ $item->requirement?->title ?? 'Employee-proposed' }}</td><td>{{ $item->proposal }}</td><td>{{ $item->title }}</td></tr>@endforeach</tbody></table>
<p>Status: {{ $idp->approved_at ? 'Supervisor approved '.$idp->approved_at->timezone('Asia/Manila')->format('M d, Y') : ($idp->submitted_at ? 'Submitted for supervisor approval' : 'Draft') }}</p>
@endsection
