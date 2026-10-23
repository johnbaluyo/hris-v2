@extends('layouts.hris')
@section('title', 'Welcome, '.strtok(auth()->user()->name, ' '))
@section('subtitle', 'Keep up with your workplace and manage your employee records.')
@section('actions')<a class="btn btn-primary" href="{{ route('attendance') }}">View my attendance</a><x-hris.office-punch :access="$officeAccess"/>@endsection
@section('content')
@if($wfhTasks)<div class="alert alert-info"><a href="{{ route('wfh-accomplishments') }}">{{ $wfhTasks }} WFH {{ Str::plural('item', $wfhTasks) }} awaiting your review</a></div>@endif
<div class="row g-4 mb-4">
    <div class="col-md-4"><a class="card portal-card h-100" href="{{ route('pds.index') }}"><div class="card-body d-flex gap-3"><div class="portal-icon" aria-hidden="true"><i class="fa-solid fa-address-card"></i></div><div><h2 class="h6 mt-1">Personal data sheet</h2><p class="small text-body-secondary mb-0">Review and update your personal records.</p></div></div></a></div>
    <div class="col-md-4"><a class="card portal-card h-100" href="{{ route('calendar') }}"><div class="card-body d-flex gap-3"><div class="portal-icon" aria-hidden="true"><i class="fa-solid fa-calendar-days"></i></div><div><h2 class="h6 mt-1">Your calendar</h2><p class="small text-body-secondary mb-0">Office activities and personal events.</p></div></div></a></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><h2 class="h6">Work from home</h2><p class="small text-body-secondary mb-2">{{ $wfh ? 'WFH time capture is available today.' : 'WFH punches are available on Wednesdays or dates covered by a Work From Home calendar event.' }}</p><button type="button" class="btn btn-outline-primary" id="clock-wfh" @disabled(! $wfh)>Record WFH punch</button></div></div></div>
</div>
<div class="row g-4">
    <div class="col-xl-8">
        <section class="mb-4"><h2 class="h5 mb-3">Applications</h2><div class="row g-3">@forelse($items->get('application', collect()) as $item)<div class="col-md-6"><a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer" class="card portal-card h-100"><div class="card-body">@if($item->file_id)<img src="/files/{{ $item->file_id }}?preview=1" width="48" height="48" class="object-fit-contain mb-3" alt="" loading="lazy">@endif<h3 class="h6">{{ $item->title }} ↗</h3><p class="small text-body-secondary mb-0">{{ $item->description }}</p></div></a></div>@empty<div class="col"><div class="card card-body text-body-secondary">No applications available.</div></div>@endforelse</div></section>
        <section><h2 class="h5 mb-3">Announcements</h2>@forelse($items->get('announcement', collect()) as $item)<article class="card mb-3"><div class="card-body"><div class="small text-body-secondary mb-2">{{ $item->created_at->format('d M Y') }}</div><h3 class="h6">{{ $item->title }}</h3><p class="mb-2 text-break" style="white-space: pre-wrap">{{ $item->description }}</p>@if($item->file_id)<a class="small" href="{{ route('files.download', $item->file_id) }}">Download attachment</a>@endif</div></article>@empty<div class="card card-body text-body-secondary">No announcements yet.</div>@endforelse</section>
    </div>
    <div class="col-xl-4">
        <section class="card mb-4"><div class="card-header bg-body"><h2 class="h6 mb-0">Today's birthdays</h2></div><div class="card-body">@forelse($birthdays as $person)<p class="small mb-2">{{ $person->name }}</p>@empty<p class="small text-body-secondary mb-0">No employee birthdays today.</p>@endforelse</div></section>
        <section class="card mb-4"><div class="card-header bg-body"><h2 class="h6 mb-0">Recent first time-in</h2></div><div class="card-body">@forelse($recent as $date => $punch)<div class="d-flex justify-content-between py-2 border-bottom"><span class="small">{{ $date }}</span><strong>{{ $punch->punched_at->format('g:i A') }}</strong></div>@empty<p class="text-body-secondary mb-0 small">No punches in the last three days.</p>@endforelse</div></section>
        <section class="card mb-4"><div class="card-header bg-body"><h2 class="h6 mb-0">Wellness</h2></div><div class="card-body">@forelse($items->get('wellness', collect()) as $item)<h3 class="h6">{{ $item->title }}</h3><p class="small text-body-secondary">{{ $item->description }}</p>@empty<p class="text-body-secondary small mb-0">No current wellness notices.</p>@endforelse</div></section>
        <section class="card"><div class="card-header bg-body"><h2 class="h6 mb-0">Useful links</h2></div><div class="list-group list-group-flush">@forelse($items->get('link', collect()) as $item)<a href="{{ $item->url }}" rel="noopener noreferrer" target="_blank" class="list-group-item list-group-item-action">{{ $item->title }} ↗<small class="d-block text-body-secondary">{{ $item->description }}</small></a>@empty<div class="card-body text-body-secondary small">No links available.</div>@endforelse</div></section>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('hris:ready', () => {
    document.querySelector('#clock-wfh').addEventListener('click', async event => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
            HRIS.feedback((await HRIS.request('/attendance/clock', {method: 'POST', body: {wfh: true}})).message);
        } catch (error) {
            HRIS.feedback(error.message, true);
        } finally {
            button.disabled = false;
        }
    });
});
</script>
@endpush
