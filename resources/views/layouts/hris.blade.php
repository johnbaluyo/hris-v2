<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', 'Employee portal') · {{ config('app.name', 'HRIS') }}</title>
    @include('partials.theme-init')
    @vite(['resources/css/hris.css', 'resources/js/hris.js'])
    @stack('head')
</head>
<body>
<a href="#main" class="visually-hidden-focusable position-absolute p-3 bg-body z-3">Skip to content</a>
<aside class="offcanvas-lg offcanvas-start hris-sidebar" tabindex="-1" id="navigation" aria-labelledby="navigation-title">
    <div class="offcanvas-header"><a id="navigation-title" href="{{ route('dashboard') }}" class="hris-brand">HR<span>IS</span><small>People & workplace</small></a><button class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#navigation" aria-label="Close navigation"></button></div>
    <div class="offcanvas-body d-flex flex-column">
        <div class="nav-section">WORKSPACE</div>
        <nav class="nav flex-column gap-1" aria-label="Main navigation">
            <a class="nav-link {{ request()->routeIs('dashboard', 'ssi') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span><i class="fa-solid fa-house" aria-hidden="true"></i></span> Employee portal</a>
            @if(auth()->user()->role !== 'applicant')
            <a class="nav-link {{ request()->is('profile*') ? 'active' : '' }}" href="{{ route('pds.index') }}"><span><i class="fa-solid fa-address-card" aria-hidden="true"></i></span> My profile & PDS</a>
            <a class="nav-link {{ request()->is('calendar*') ? 'active' : '' }}" href="{{ route('calendar') }}"><span><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span> Calendars</a>
            <a class="nav-link {{ request()->is('attendance*') ? 'active' : '' }}" href="{{ route('attendance') }}"><span><i class="fa-solid fa-clock" aria-hidden="true"></i></span> Attendance</a>
            <a class="nav-link {{ request()->routeIs('attendance.review') ? 'active' : '' }}" href="{{ route('attendance.review') }}"><span><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span> Monthly attendance review</a>
            @endif
            @can('staff.manage')<div class="nav-section mt-4">HR ADMINISTRATION</div><a class="nav-link {{ request()->is('hr/staff*') ? 'active' : '' }}" href="{{ route('staff.index') }}"><span><i class="fa-solid fa-users" aria-hidden="true"></i></span> Staff directory</a>@endcan
            @can('organization.manage')
            <a class="nav-link {{ request()->is('organization/divisions') ? 'active' : '' }}" href="{{ route('organization', 'divisions') }}"><span><i class="fa-solid fa-sitemap" aria-hidden="true"></i></span> Divisions</a>
            <a class="nav-link {{ request()->is('organization/positions') ? 'active' : '' }}" href="{{ route('organization', 'positions') }}"><span><i class="fa-solid fa-briefcase" aria-hidden="true"></i></span> Positions</a>
            @endcan
            @can('delegations.manage')<a class="nav-link {{ request()->is('delegations') ? 'active' : '' }}" href="{{ route('delegations') }}"><span><i class="fa-solid fa-user-clock" aria-hidden="true"></i></span> Temporary delegations</a>@endcan
            @can('portal.manage')<a class="nav-link {{ request()->is('portal/manage') ? 'active' : '' }}" href="{{ route('portal.manage') }}"><span><i class="fa-solid fa-bullhorn" aria-hidden="true"></i></span> Portal content</a>@endcan
        </nav>
        <div class="sidebar-footer mt-auto pt-5 d-flex align-items-center gap-2"><x-account-avatar :user="auth()->user()" /><div><span class="badge text-bg-light mb-2">{{ str_replace('_', ' ', auth()->user()->role) }}</span><p class="mb-0 small">{{ auth()->user()->name }}</p></div></div>
    </div>
</aside>
<div class="hris-shell">
    <header class="hris-header d-flex align-items-center gap-3">
        <button class="btn btn-outline-secondary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#navigation" aria-controls="navigation" aria-label="Open navigation"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
        <span class="small text-body-secondary d-none d-sm-block">Human Resource Information System</span>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <x-theme-toggle />
            <a href="{{ route('profile.edit') }}" class="d-flex align-items-center gap-2 text-decoration-none" aria-label="Account"><x-account-avatar :user="auth()->user()" /><span class="small d-none d-md-inline text-body">Account</span></a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span class="ms-1 d-none d-sm-inline">Sign out</span><span class="visually-hidden d-sm-none">Sign out</span></button></form>
        </div>
    </header>
    <main id="main" class="hris-main" tabindex="-1">
        <div class="page-heading d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div><div class="eyebrow">{{ now('Asia/Manila')->format('l, d F Y') }}</div><h1 class="h3 mb-1">@yield('title', 'Employee portal')</h1><p class="text-body-secondary mb-0">@yield('subtitle', 'Your people, records and working day in one place.')</p></div>
            <div class="d-flex gap-2 flex-wrap">@yield('actions')</div>
        </div>
        <div id="feedback" class="alert d-none" role="status" aria-live="polite" tabindex="-1"></div>
        @yield('content')
    </main>
    <footer class="px-4 py-3 small text-body-secondary border-top">HRIS · Asia/Manila</footer>
</div>
@stack('scripts')
</body>
</html>
