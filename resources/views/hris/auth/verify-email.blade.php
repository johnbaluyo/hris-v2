@extends('layouts.hris-auth')
@section('title','Verify your email')
@section('content')<p class="small text-body-secondary">Use the verification link in your email before accessing employee records.</p><form method="POST" action="{{ route('verification.send') }}">@csrf<button class="btn btn-primary w-100" type="submit">Send verification email</button></form><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-secondary w-100 mt-3" type="submit" data-test="logout-button">Sign out</button></form>@endsection
