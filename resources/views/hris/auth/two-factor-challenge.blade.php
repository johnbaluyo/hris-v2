@extends('layouts.hris-auth')
@section('title','Two-factor verification')
@section('content')<form method="POST" action="{{ route('two-factor.login.store') }}">@csrf<div class="row g-3"><x-hris.field name="code" label="Authentication code" inputmode="numeric" autocomplete="one-time-code" class="col-12"/><x-hris.field name="recovery_code" label="Or use a recovery code" autocomplete="off" class="col-12"/></div><button class="btn btn-primary w-100 mt-3" type="submit">Verify</button></form>@endsection
