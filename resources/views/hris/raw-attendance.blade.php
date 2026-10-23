@extends('layouts.hris')
@section('title', 'Raw attendance punches')
@section('subtitle', 'Review and correct attendance captures for all employees.')
@section('content')
<div class="alert alert-info">Corrections require a reason and retain the original capture. Affected daily attendance is recalculated automatically; manual DTR overrides are retained. All times use Asia/Manila.</div>
<x-hris.table id="raw-punch-table" endpoint="/hr/raw-attendance/data" search filters
    :columns="[['key'=>'employee_name','label'=>'Employee'],['key'=>'atms_id','label'=>'ATMS ID'],['key'=>'punched_at','label'=>'Punch time'],['key'=>'device','label'=>'Device'],['key'=>'record_type','label'=>'Record type'],['key'=>'revisions_count','label'=>'Corrections']]"
    :actions="[['name'=>'edit','label'=>'Edit'],['name'=>'history','label'=>'History']]">
    <div><label for="raw-from" class="form-label small">From</label><input id="raw-from" type="date" name="from" data-table-filter class="form-control" value="{{ today('Asia/Manila')->startOfMonth()->toDateString() }}"></div>
    <div><label for="raw-to" class="form-label small">To</label><input id="raw-to" type="date" name="to" data-table-filter class="form-control" value="{{ today('Asia/Manila')->endOfMonth()->toDateString() }}"></div>
    <div><label for="raw-matched" class="form-label small">Employee match</label><select id="raw-matched" name="matched" data-table-filter class="form-select"><option value="">All captures</option><option value="yes">Matched</option><option value="no">Unmatched</option></select></div>
</x-hris.table>
<x-hris.modal id="raw-punch-modal" title="Correct raw punch">
    <form id="raw-punch-form" data-method="PUT">
        <div class="modal-body">
            <div data-form-errors class="alert alert-danger d-none"></div>
            <p id="raw-punch-employee" class="fw-semibold"></p>
            <input type="hidden" name="version">
            <div class="row g-3">
                <x-hris.field name="punched_at" label="Punch time (Asia/Manila)" type="datetime-local" step="1" class="col-12" required/>
                <x-hris.field name="device" maxlength="100" required/>
                <x-hris.field name="record_type" label="Record type" maxlength="50"/>
                <x-hris.field name="reason" label="Reason for correction" type="textarea" maxlength="900" class="col-12" required/>
            </div>
            <p class="small text-body-secondary mt-3 mb-0">Employee identity and collector identifiers remain attached to this capture. Unmatched captures require attendance identity reconciliation before they can appear in a DTR.</p>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Save correction</button></div>
    </form>
</x-hris.modal>
<x-hris.modal id="raw-history-modal" title="Raw punch correction history">
    <div class="modal-body"><p id="raw-history-context" class="fw-semibold"></p><div id="raw-history-feedback" role="status"></div><div id="raw-history-entries"></div></div>
    <div class="modal-footer"><button type="button" id="raw-history-previous" class="btn btn-outline-secondary">Previous</button><span id="raw-history-page"></span><button type="button" id="raw-history-next" class="btn btn-outline-secondary">Next</button></div>
</x-hris.modal>
@endsection
@push('scripts')
<script>
document.addEventListener('hris:ready', () => {
    const table = document.querySelector('#raw-punch-table'), form = document.querySelector('#raw-punch-form');
    const entries = document.querySelector('#raw-history-entries'), status = document.querySelector('#raw-history-feedback');
    const previous = document.querySelector('#raw-history-previous'), next = document.querySelector('#raw-history-next');
    let selected, page = 1, requestNumber = 0;
    HRIS.bindForm(form, {success: () => table.table.load()});
    async function loadHistory(targetPage) {
        const currentRequest = ++requestNumber;
        previous.disabled = next.disabled = true;
        entries.replaceChildren();
        status.textContent = 'Loading history…';
        try {
            const result = await HRIS.request(`/hr/raw-attendance/${selected.id}/history?page=${targetPage}`);
            if (currentRequest !== requestNumber) return;
            page = result.current_page;
            status.textContent = result.total ? '' : 'No corrections recorded.';
            document.querySelector('#raw-history-page').textContent = `Page ${page} of ${result.last_page}`;
            for (const revision of result.data) {
                const section = document.createElement('section'); section.className = 'border rounded p-3 mb-3';
                const heading = document.createElement('p'); heading.className = 'fw-semibold';
                heading.textContent = `${revision.actor?.name ?? 'Administrator'} · ${new Date(revision.created_at).toLocaleString('en-PH', {timeZone: 'Asia/Manila'})}`;
                const reason = document.createElement('p'); reason.textContent = revision.reason;
                section.append(heading, reason);
                for (const [key, label] of [['punched_at','Punch time'], ['device','Device'], ['record_type','Record type']]) {
                    if (revision.before[key] === revision.after[key]) continue;
                    const change = document.createElement('p'); change.className = 'small mb-1 text-break';
                    change.textContent = `${label}: ${revision.before[key] ?? '(empty)'} → ${revision.after[key] ?? '(empty)'}`;
                    section.append(change);
                }
                entries.append(section);
            }
            previous.disabled = page <= 1; next.disabled = page >= result.last_page;
        } catch (error) { if (currentRequest === requestNumber) status.textContent = error.message; }
    }
    previous.addEventListener('click', () => loadHistory(page - 1));
    next.addEventListener('click', () => loadHistory(page + 1));
    table.addEventListener('table:action', ({detail: {action, row}}) => {
        if (action === 'edit') {
            HRIS.fillForm(form, {...row, punched_at: null});
            form.elements.punched_at.value = row.punched_at.replace(' ', 'T');
            form.elements.reason.value = '';
            form.dataset.endpoint = `/hr/raw-attendance/${row.id}`;
            document.querySelector('#raw-punch-employee').textContent = `${row.employee_name} · ATMS ${row.atms_id}`;
            HRIS.openModal('raw-punch-modal');
        } else {
            selected = row;
            document.querySelector('#raw-history-context').textContent = `${row.employee_name} · Punch #${row.id}`;
            HRIS.openModal('raw-history-modal');
            loadHistory(1);
        }
    });
});
</script>
@endpush
