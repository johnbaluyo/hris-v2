@extends('layouts.hris-auth')
@section('title','Password recovery')
@section('content')<p class="text-body-secondary small">Enter your account email to request a password setup or reset link.</p><form method="POST" action="{{ route('password.email') }}">@csrf<div class="row g-3"><x-hris.field name="email" type="email" :value="old('email')" class="col-12" required autocomplete="email"/></div><button class="btn btn-primary w-100 mt-3" type="submit">Send password link</button></form><a href="{{ route('login') }}" class="d-block text-center mt-3 small">Return to sign in</a>@endsection
