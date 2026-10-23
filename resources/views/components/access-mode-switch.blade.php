@if(auth()->user()?->isAdministrator())
<form method="POST" action="{{ route('account.access-mode') }}" class="account-access-switch">
    @csrf
    <select name="mode" aria-label="Account access" title="Switch account access" class="account-access-select" onchange="this.form.requestSubmit()">
        @foreach(['admin' => 'Admin', 'staff' => 'Staff', 'hr' => 'HR'] as $mode => $label)
            <option value="{{ $mode }}" @selected(auth()->user()->accessMode() === $mode)>{{ $label }}</option>
        @endforeach
    </select>
    <noscript><button type="submit">Switch access</button></noscript>
</form>
@endif
