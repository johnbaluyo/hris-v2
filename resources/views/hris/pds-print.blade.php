@extends('layouts.hris')
@section('title', 'PDS data review — '.$employee->name)
@section('subtitle', 'Review the current saved information before transferring it to the approved PDS form.')
@section('actions')<button class="btn btn-primary" data-print>Print</button>@endsection
@section('content')
<h1 class="h3">Personal data sheet — data review</h1><p>{{ $employee->name }} · Prepared {{ now('Asia/Manila')->format('d F Y H:i') }} Asia/Manila</p><p class="small text-body-secondary">This data review is not a certified government form. The approved form edition and declaration question wording must be confirmed by HR.</p>
@foreach($sections as $name=>$entries)<section class="print-section"><h2 class="h5 border-bottom pb-2">{{ ucwords(str_replace('-', ' ', $name)) }}</h2>@forelse($entries as $entry)<dl class="row record-grid">@foreach($entry->data as $key=>$value)<dt class="col-4">{{ ucwords(str_replace('_',' ',$key)) }}</dt><dd class="col-8">@if(is_array($value))@foreach($value as $child){{ is_array($child) ? implode(' · ', array_filter($child, fn($v)=>$v!==null&&$v!=='')) : $child }}<br>@endforeach @elseif(is_bool($value)){{ $value ? 'Yes':'No' }}@else{{ $value === null || $value === '' ? '—' : $value }}@endif</dd>@endforeach @if($entry->file_id)<dt class="col-4">Attachment</dt><dd class="col-8"><a href="/files/{{ $entry->file_id }}">Authorized attachment</a></dd>@endif</dl>@empty<p class="text-body-secondary">No information recorded.</p>@endforelse</section>@endforeach
@endsection
