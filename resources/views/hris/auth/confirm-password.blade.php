@extends('layouts.hris-auth')
@section('title','Confirm your password')
@section('content')<p class="small text-body-secondary">Confirm your password to continue to account security.</p><form method="POST" action="{{ route('password.confirm.store') }}">@csrf<div class="row g-3"><x-hris.field name="password" type="password" required autocomplete="current-password" class="col-12"/></div><button class="btn btn-primary w-100 mt-3" type="submit">Confirm password</button></form>@endsection
