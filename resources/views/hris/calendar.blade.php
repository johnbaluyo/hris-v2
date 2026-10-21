@extends('layouts.hris')
@section('title', 'Calendars')
@section('subtitle', 'Your personal events, office activities and executive schedules. Dates use Asia/Manila.')
@section('actions')<button class="btn btn-primary" id="create-event">Add event</button>@endsection
@section('content')
<section class="card calendar-card mb-4" aria-label="Event calendar">
    <div class="card-header bg-body d-flex align-items-center gap-3 flex-wrap">
        <label for="calendar-filter" class="form-label mb-0">Calendar</label>
        <select id="calendar-filter" name="calendar" class="form-select w-auto"><option value="personal">My calendar</option><option value="office">Office calendar</option><option value="ed">Executive calendar</option></select>
    </div>
    <div class="card-body">
        <div id="event-calendar" aria-label="Monthly event calendar"></div>
        <div class="d-flex align-items-center gap-2 mt-3"><span id="calendar-status" class="small text-body-secondary" role="status"></span><button type="button" id="calendar-retry" class="btn btn-outline-secondary btn-sm" hidden>Retry calendar</button></div>
    </div>
</section>
<x-hris.table id="calendar-table" endpoint="/calendar/data" search filters :columns="[['key'=>'title','label'=>'Event'],['key'=>'event_type','label'=>'Calendar'],['key'=>'starts_at','label'=>'Starts'],['key'=>'ends_at','label'=>'Ends (inclusive)'],['key'=>'all_day','label'=>'All day']]" :actions="[['name'=>'details','label'=>'Details'],['name'=>'edit','label'=>'Edit','editable'=>true],['name'=>'delete','label'=>'Delete','editable'=>true,'danger'=>true]]">
    <div><label for="calendar-from" class="form-label">From</label><input type="date" id="calendar-from" name="from" class="form-control" data-table-filter></div><div><label for="calendar-to" class="form-label">Through</label><input type="date" id="calendar-to" name="to" class="form-control" data-table-filter></div>
</x-hris.table>
<x-hris.modal id="event-modal" title="Calendar event"><form id="event-form"><div class="modal-body"><div data-form-errors class="alert alert-danger d-none" role="alert" tabindex="-1"></div><div class="row g-3"><x-hris.field name="event_type" label="Calendar type" type="select" :options="auth()->user()->hasAbility('calendar.manage') ? ['My Calendar'=>'My calendar','Office'=>'Office','Executive Director Activities'=>'Executive Director Activities','Work From Home'=>'Work From Home'] : ['My Calendar'=>'My calendar']" required/><x-hris.field name="title" required/><x-hris.field name="description" type="textarea" class="col-12"/><x-hris.field name="starts_at" label="Starts (Manila time)" type="datetime-local" required/><x-hris.field name="ends_at" label="Ends (inclusive for all-day events)" type="datetime-local" required/><div class="col-12 form-check ms-2"><input id="all-day" name="all_day" class="form-check-input" type="checkbox" checked><label class="form-check-label" for="all-day">All day</label></div><x-hris.employee-picker name="participants" label="Participants" multiple/></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save event</button></div></form></x-hris.modal>
@endsection
@push('scripts')
<script>
document.addEventListener('hris:ready', async () => {
    const table = document.querySelector('#calendar-table'), form = document.querySelector('#event-form');
    function edit(row, defaults = {}) {
        const picker = form.elements.namedItem('participants');
        for (const person of row?.participants || []) {
            if (![...picker.options].some(option => option.value === String(person.id))) picker.append(new Option(person.name, person.id));
        }
        HRIS.fillForm(form, row ? {...row, participants: row.participants.map(person => person.id)} : {event_type: 'My Calendar', all_day: true, ...defaults});
        form.dataset.endpoint = '/calendar/events' + (row ? `/${row.id}` : '');
        form.dataset.method = row ? 'PUT' : 'POST';
        HRIS.openModal('event-modal');
    }
    async function details(id) {
        const event = await HRIS.request(`/calendar/events/${id}`);
        HRIS.showDetails(event.title, {calendar: event.event_type, starts: event.starts_at, ends: event.ends_at, description: event.description, participants: event.participants.map(person => person.name)});
    }
    const calendar = await HRIS.mountCalendar(document.querySelector('#event-calendar'), {
        loadEvents: async ({from, to}) => {
            const selectedFrom = document.querySelector('#calendar-from').value;
            const selectedTo = document.querySelector('#calendar-to').value;
            from = selectedFrom && selectedFrom > from ? selectedFrom : from;
            to = selectedTo && selectedTo < to ? selectedTo : to;
            if (from > to) return [];
            const params = new URLSearchParams({from, to, calendar: document.querySelector('#calendar-filter').value, search: table.querySelector('input[type="search"]')?.value || ''});
            return HRIS.request(`/calendar/feed?${params}`);
        },
        onDateClick: date => edit(null, {starts_at: `${date}T00:00:00+08:00`, ends_at: `${date}T00:00:00+08:00`}),
        onEventClick: async (id, canEdit) => { try { if (canEdit) edit(await HRIS.request(`/calendar/events/${id}`)); else await details(id); } catch (error) { HRIS.feedback(error.message, true); } },
    });
    document.querySelector('#create-event').onclick = () => edit(null);
    const refresh = () => { table.table.load(); calendar.refetchEvents(); };
    HRIS.bindForm(form, {success: refresh});
    document.querySelector('#calendar-filter').addEventListener('change', event => {
        table.table.params.set('calendar', event.target.value);
        table.table.page = 1;
        refresh();
    });
    table.addEventListener('table:loaded', () => calendar.refetchEvents());
    table.addEventListener('table:action', async ({detail: {action, row}}) => {
        try {
            if (action === 'details') await details(row.id);
            if (action === 'edit') edit(row);
            if (action === 'delete' && await HRIS.confirm('Delete this event?')) {
                HRIS.feedback((await HRIS.request(`/calendar/events/${row.id}`, {method: 'DELETE'})).message);
                refresh();
            }
        } catch (error) { HRIS.feedback(error.message, true); }
    });
});
</script>
@endpush
