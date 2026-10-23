@extends('layouts.hris')
@section('title', 'Applicant examination')
@section('subtitle', 'The server enforces your deadline. Reloading does not restart the timer.')
@section('content')
<div class="alert alert-info" role="timer" id="exam-time" data-expires="{{ $attempt->expires_at->toIso8601String() }}">Deadline: {{ $attempt->expires_at->format('d M Y g:i:s A') }} Manila</div>
<form id="exam-form" data-endpoint="/careers/exams/{{ $attempt->id }}/submit"><div data-form-errors class="alert alert-danger d-none"></div>
@foreach($attempt->questions as $question)<section class="card mb-3"><div class="card-body"><h2 class="h6">{{ $loop->iteration }}. {{ $question['question'] }}</h2>
@if($question['image_file_id'])<img class="img-fluid mb-3" style="max-height:250px" src="/recruitment/images/{{ $question['image_file_id'] }}" alt="Question illustration">@endif
@if(in_array($question['question_type'], [1,2]))
<div class="vstack gap-2">@foreach($question['question_type'] === 2 ? [['id'=>'True','text'=>'True'],['id'=>'False','text'=>'False']] : $question['choices'] as $choice)
<label class="form-check"><input class="form-check-input" type="radio" name="answers[{{ $question['id'] }}]" value="{{ $choice['id'] }}"><span class="form-check-label">{{ $choice['text'] }}</span>@if($choice['image_file_id'] ?? null)<img class="img-fluid d-block" style="max-height:150px" src="/recruitment/images/{{ $choice['image_file_id'] }}" alt="Choice illustration">@endif</label>@endforeach</div>
@else<label class="form-label" for="answer-{{ $question['id'] }}">Your response</label><textarea class="form-control" id="answer-{{ $question['id'] }}" name="answers[{{ $question['id'] }}]" rows="{{ $question['question_type'] === 3 ? 6 : 2 }}" maxlength="30000"></textarea>@endif
</div></section>@endforeach
<button class="btn btn-primary" type="submit">Submit final answers</button></form>
@endsection
@push('scripts')<script>document.addEventListener('hris:ready',()=>{const form=document.querySelector('#exam-form'),timer=document.querySelector('#exam-time'),deadline=Date.parse(timer.dataset.expires);function tick(){const seconds=Math.max(0,Math.ceil((deadline-Date.now())/1000));timer.textContent=`Time remaining: ${Math.floor(seconds/60)}m ${seconds%60}s`;if(!seconds)form.querySelector('button[type="submit"]').disabled=true}tick();setInterval(tick,1000);HRIS.bindForm(form,{prepare:body=>{for(const key of Object.keys(body))delete body[key];body.answers={};for(const[key,value]of new FormData(form)){const match=key.match(/^answers\[(\d+)\]$/);if(match)body.answers[match[1]]=value}},success:()=>{window.location.assign('/careers')}})});</script>@endpush
