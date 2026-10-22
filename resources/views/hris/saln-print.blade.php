@extends('layouts.hris')
@section('title', 'SALN working copy')
@section('actions')<button class="btn btn-primary" data-print>Print</button>@endsection
@section('content')
<h2 class="h5">{{ $saln->employee->name }} - as of {{ $saln->compliance_date->format('F d, Y') }}</h2>
@foreach($saln->entries->groupBy('section') as $section=>$entries)<h3 class="h6 mt-4">{{ ucwords(str_replace('_',' ',$section)) }}</h3><table class="table table-bordered"><tbody>@foreach($entries as $entry)<tr><td>@foreach($entry->data as $key=>$value)<strong>{{ ucwords(str_replace('_',' ',$key)) }}:</strong> {{ $value }}<br>@endforeach</td><td class="text-end">{{ $entry->declared_amount }}</td></tr>@endforeach</tbody></table>@endforeach
<dl class="row"><dt class="col-6">Total assets</dt><dd class="col-6">{{ $totals['total_assets'] }}</dd><dt class="col-6">Total liabilities</dt><dd class="col-6">{{ $totals['total_liabilities'] }}</dd><dt class="col-6">Net worth</dt><dd class="col-6">{{ $totals['net_worth'] }}</dd></dl>
@endsection
