@extends('layouts.hris')
@section('title', 'IPCR')
@section('actions')<button class="btn btn-primary" data-print>Print</button>@endsection
@section('content')
<h2 class="h5">{{ $ipcr->employee->name }} - {{ $ipcr->period_start->format('M d, Y') }} to {{ $ipcr->period_end->format('M d, Y') }}</h2>
@foreach($ipcr->outputs->groupBy('category') as $category=>$outputs)<h3 class="h6 mt-4">Category {{ $category }}</h3>@foreach($outputs as $output)<section class="mb-3"><strong>{{ $output->task }}</strong><ul>@foreach($output->indicators as $indicator)<li>{{ $indicator->description }}<ul>@foreach($indicator->accomplishments as $item)<li>{{ $item->accomplishment }} - Q: {{ $item->quality ?? '-' }}, E: {{ $item->efficiency ?? '-' }}, T: {{ $item->timeliness ?? '-' }}</li>@endforeach</ul></li>@endforeach</ul></section>@endforeach @endforeach
<p><strong>Overall rating:</strong> {{ $rating['overall'] }} ({{ $rating['description'] }})</p>
@endsection
