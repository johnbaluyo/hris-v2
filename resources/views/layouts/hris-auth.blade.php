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

<body>
    <main class="container py-4">
        <div class="d-flex justify-content-end"><x-theme-toggle /></div>
        <div class="auth-card">
            <div class="text-center mb-4"><a href="{{ route('home') }}" class="h3 text-decoration-none fw-bold">HRIS</a>
                <p class="text-body-secondary small mt-2">Human Resource Information System</p>
            </div>
            <div class="card">
                <div class="card-body p-4">
                    <h1 class="h4 mb-3">@yield('title')</h1>
                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert" data-flash-message="{{ $errors->first() }}" data-flash-error="true">{{ $errors->first() }}</div>
                        @endif @if (session('status'))
                            <div class="alert alert-success" role="status" data-flash-message="{{ session('status') === 'verification-link-sent' ? 'Verification email sent.' : session('status') }}">
                                {{ session('status') === 'verification-link-sent' ? 'Verification email sent.' : session('status') }}
                            </div>
                        @endif @yield('content')
                </div>
            </div>
        </div>
    </main>@stack('scripts')
</body>

</html>
