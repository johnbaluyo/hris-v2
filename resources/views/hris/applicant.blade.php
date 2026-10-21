@extends('layouts.hris')
@section('title', 'Your account')
@section('subtitle', 'Manage your sign-in details and account security.')
@section('content')<div class="card"><div class="card-body"><h2 class="h5">Welcome, {{ auth()->user()->name }}</h2><p>Employee records and the staff portal are available to employee accounts.</p><a href="{{ route('profile.edit') }}" class="btn btn-primary">Account settings</a></div></div>@endsection
