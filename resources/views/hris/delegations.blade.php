@extends('layouts.hris')
@section('title', 'Temporary delegations')
@section('subtitle', 'Grant division attendance access for an inclusive, explicit date range.')
@section('actions')<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#delegation-modal">Add delegation</button>@endsection
@section('content')
<x-hris.table id="delegation-table" endpoint="/delegations" :columns="[['key'=>'user_id','label'=>'Employee ID'],['key'=>'division_id','label'=>'Division ID'],['key'=>'ability','label'=>'Ability'],['key'=>'period_start','label'=>'Starts'],['key'=>'period_end','label'=>'Ends']]"/>
<x-hris.modal id="delegation-modal" title="Temporary division access"><form id="delegation-form" data-endpoint="/delegations"><div class="modal-body"><div data-form-errors class="alert alert-danger d-none" role="alert" tabindex="-1"></div><div class="row g-3"><x-hris.employee-picker name="user_id" label="Employee" required/><x-hris.field name="division_id" label="Division" type="select" :options="$divisions->pluck('name','id')->all()" required/><input type="hidden" name="ability" value="attendance.division"><x-hris.field name="period_start" type="date" required/><x-hris.field name="period_end" type="date" required/></div></div><div class="modal-footer"><button class="btn btn-primary" type="submit">Save delegation</button></div></form></x-hris.modal>
@endsection
@push('scripts')<script>document.addEventListener('hris:ready',()=>HRIS.bindForm(document.querySelector('#delegation-form'),{success:()=>document.querySelector('#delegation-table').table.load()}));</script>@endpush
