@extends('layouts.report')
@section('title', 'Applicant answer report')
@section('content')
<h1>Applicant answer report</h1><p>{{ $application->profile_snapshot['name'] }} · {{ $application->vacancy->position->title }}</p>
@forelse($application->attempts as $attempt)
<h2>{{ $attempt->category_snapshot['name'] }}</h2><p>Started: {{ $attempt->started_at->format('d M Y g:i A') }} Manila · {{ $attempt->submitted_at ? 'Submitted '.$attempt->submitted_at->format('d M Y g:i A') : 'Not submitted' }}</p>
@foreach($attempt->questions as $question)<section><h3>{{ $loop->iteration }}. {{ $question['question'] }}</h3>@if($question['image_file_id'])<img src="/recruitment/images/{{ $question['image_file_id'] }}" alt="Question illustration">@endif
@foreach($question['choices'] ?? [] as $choice)<p>{{ $choice['id'] }}. {{ $choice['text'] }}@if($choice['image_file_id'] ?? null)<br><img src="/recruitment/images/{{ $choice['image_file_id'] }}" alt="Choice illustration">@endif</p>@endforeach
<p><strong>Response:</strong> {{ $attempt->answers[$question['id']] ?? 'No response' }}</p></section>@endforeach
@empty<p>No examination attempts.</p>@endforelse
<p class="signature">Evaluator: ____________________ Date: ____________________</p><small>This report records responses. It does not assign a score or hiring outcome.</small>
@endsection
