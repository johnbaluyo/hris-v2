@extends('layouts.report')
@section('title', 'Employment certificate record')
@section('content')
<h1>Employment certificate record</h1><p>Status: {{ $certificate->status }} · {{ ucfirst($certificate->type) }}</p><p>{{ $certificate->snapshot['name'] }}<br>{{ $certificate->snapshot['position'] }}<br>{{ $certificate->snapshot['division'] }}</p>
<p>Employment: {{ $certificate->snapshot['hired_on'] ?? 'Not recorded' }}@if($certificate->type === 'manual') through {{ $certificate->snapshot['terminated_on'] }}@endif</p>
<p>Purpose: {{ $certificate->purpose }}</p>
@if($certificate->type === 'current' && $certificate->compensation)<h2>Annual compensation amounts</h2><table><thead><tr><th>Component</th><th>Amount</th></tr></thead><tbody>@foreach($certificate->compensation as $field=>$cents)<tr><td>{{ ucwords(str_replace('_',' ',$field)) }}</td><td>{{ number_format($cents/100,2) }}</td></tr>@endforeach<tr><th>Annual basic pay + PERA</th><td>{{ number_format($certificate->annual_total_cents/100,2) }}</td></tr><tr><th>Benefits</th><td>{{ number_format($certificate->benefit_total_cents/100,2) }}</td></tr><tr><th>Grand total</th><td>{{ number_format($certificate->grand_total_cents/100,2) }}</td></tr></tbody></table>@endif
@if($certificate->issued_at)<p>Recorded issuer: {{ $certificate->snapshot['signer']['name'] }} · {{ $certificate->issued_at->format('d M Y g:i A') }} Manila</p>@endif
@foreach($certificate->files as $file)<p><a href="/files/{{ $file->private_file_id }}">Download completed certificate</a></p>@endforeach
<p><small>This is a record summary. The completed PDF is the issued document. Official certificate wording, branding and signature assets require HR approval.</small></p>
@endsection
