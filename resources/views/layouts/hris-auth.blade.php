<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <script>
        try {
            const t = localStorage.getItem('hris-theme');
            document.documentElement.dataset.bsTheme = t === 'light' || t === 'dark' ? t : matchMedia(
                '(prefers-color-scheme:dark)').matches ? 'dark' : 'light'
        } catch (e) {
            document.documentElement.dataset.bsTheme = 'light'
        }
    </script>@vite(['resources/css/hris.css', 'resources/js/hris.js'])
</head>

<body class="auth-page" style="--auth-background: url('{{ asset('images/ssi-background.png') }}')">
    <main class="container py-4 auth-page-shell">
        <div class="auth-theme-toggle"><x-theme-toggle /></div>
        <div class="auth-card legacy-auth-card">
            <section class="auth-brand-panel" aria-label="Philippine Statistical Research and Training Institute">
                <img src="{{ asset('images/psrti-logo.png') }}" alt="PSRTI logo" class="auth-brand-logo">
                <p>Human Resource<br>Information System</p>
            </section>
            <section class="auth-form-panel">
                    <img src="{{ asset('images/psrti-logo.png') }}" alt="" class="auth-mobile-logo" aria-hidden="true">
                    <h1 class="h3 mb-4">@yield('title')</h1>
                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert" data-flash-message="{{ $errors->first() }}" data-flash-error="true">{{ $errors->first() }}</div>
                        @endif @if (session('status'))
                            <div class="alert alert-success" role="status" data-flash-message="{{ session('status') === 'verification-link-sent' ? 'Verification email sent.' : session('status') }}">
                                {{ session('status') === 'verification-link-sent' ? 'Verification email sent.' : session('status') }}
                            </div>
                        @endif @yield('content')
            </section>
        </div>
        <p class="auth-version">PSRTI HRIS</p>
    </main>@stack('scripts')
</body>

</html>
