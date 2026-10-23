@extends('layouts.hris')
@section('title', 'Your account')
@section('subtitle', 'Manage your sign-in details and account security.')
@section('content')<div class="card"><div class="card-body"><h2 class="h5">Welcome, {{ auth()->user()->name }}</h2><p>Browse open vacancies, maintain your applicant profile, and take enabled examinations.</p><a href="{{ route('careers') }}" class="btn btn-primary">Careers & applications</a> <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">Account settings</a></div></div>@endsection
