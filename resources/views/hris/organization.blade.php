@extends('layouts.hris')
@section('title', ucfirst($kind))
@section('subtitle', 'Maintain organization references used by employee assignments.')
@section('actions')<button class="btn btn-primary" id="create-record">Add {{ $kind === 'positions' ? 'position' : 'division' }}</button>@endsection
@section('content')
<x-hris.table id="organization-table" :endpoint="'/organization/'.$kind" :columns="$kind === 'divisions' ? [['key'=>'name','label'=>'Division'],['key'=>'acronym','label'=>'Acronym']] : [['key'=>'title','label'=>'Position'],['key'=>'code','label'=>'Code'],['key'=>'division_id','label'=>'Division ID'],['key'=>'plantilla','label'=>'Plantilla'],['key'=>'status','label'=>'Status']]" :actions="[['name'=>'edit','label'=>'Edit']]"/>
<x-hris.modal id="organization-modal" title="Organization record"><form id="organization-form" data-endpoint="/organization/{{ $kind }}"><input type="hidden" name="id"><div class="modal-body"><div data-form-errors class="alert alert-danger d-none" role="alert" tabindex="-1"></div><div class="row g-3">
@if($kind === 'divisions')<x-hris.field name="name" required/><x-hris.field name="acronym"/>
@else<x-hris.field name="title" required/><x-hris.field name="code"/><x-hris.field name="division_id" label="Division" type="select" :options="$divisions->pluck('name','id')->all()" required/><x-hris.field name="classification" type="select" :options="['Staff'=>'Staff','Supervisory'=>'Supervisory']" required/><x-hris.field name="plantilla" type="select" :options="['true'=>'Yes','false'=>'No']" required/><x-hris.field name="status" type="select" :options="['Open for Application'=>'Open for Application','Filled Up'=>'Filled Up','archived'=>'Archived']" required/>
@endif
</div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save record</button></div></form></x-hris.modal>
@endsection
@push('scripts')<script>document.addEventListener('hris:ready',()=>{const form=document.querySelector('#organization-form'),table=document.querySelector('#organization-table');HRIS.bindForm(form,{success:()=>table.table.load()});document.querySelector('#create-record').addEventListener('click',()=>{HRIS.fillForm(form,{id:null});HRIS.openModal('organization-modal')});table.addEventListener('table:action',({detail:{row}})=>{HRIS.fillForm(form,{...row,plantilla:String(row.plantilla)});HRIS.openModal('organization-modal')})});</script>@endpush
