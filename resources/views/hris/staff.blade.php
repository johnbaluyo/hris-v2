@extends('layouts.hris')
@section('title', 'Staff directory')
@section('subtitle', 'Manage employee details, organization assignments and personnel files.')
@section('actions')<button class="btn btn-primary" id="create-staff">
    Add employee</button>@endsection
@section('content')
    <x-hris.table id="staff-table" endpoint="/hr/staff/data" :search="true" :filters="true" :columns="[
        ['key' => 'name', 'label' => 'Employee', 'avatar' => true],
        ['key' => 'email', 'label' => 'Email'],
        ['key' => 'division.name', 'label' => 'Division'],
        ['key' => 'position.title', 'label' => 'Position'],
        ['key' => 'employee_type', 'label' => 'Employment'],
        ['key' => 'atms_id', 'label' => 'Attendance ID'],
        ['key' => 'is_archived', 'label' => 'Archived'],
    ]"
        :actions="[
            ['name' => 'edit', 'label' => 'Edit'],
            ['name' => 'profile', 'label' => 'PDS'],
            ['name' => 'attendance', 'label' => 'DTR'],
            ['name' => 'service-record', 'label' => 'Service record'],
            ['name' => 'files', 'label' => 'Files'],
            ['name' => 'archive', 'label' => 'Archive / reactivate'],
        ]">
        <div><label class="form-label small" for="division-filter">Division</label><select id="division-filter"
                name="division_id" data-table-filter class="form-select">
                <option value="">All divisions</option>
                @foreach ($divisions as $division)
                    <option value="{{ $division->id }}">{{ $division->name }}</option>
                @endforeach
            </select></div>
        <div><label class="form-label small" for="archive-filter">Status</label><select id="archive-filter" name="archived"
                data-table-filter class="form-select">
                <option value="">All employees</option>
                <option value="0">Active</option>
                <option value="1">Archived</option>
            </select></div>
        <div><label class="form-label small" for="staff-sort">Sort by</label><select id="staff-sort" name="sort"
                data-table-filter class="form-select">
                <option value="name">Name</option>
                <option value="email">Email</option>
                <option value="created_at">Created</option>
            </select></div>
    </x-hris.table>
    <x-hris.modal id="staff-modal" title="Employee details">
        <form id="staff-form" data-endpoint="/hr/staff">
            <div class="modal-body">
                <div class="alert alert-danger d-none" data-form-errors tabindex="-1" role="alert"></div>
                <div class="row g-3">
                    <x-hris.field name="first_name" required maxlength="255" /><x-hris.field name="last_name" required
                        maxlength="255" /><x-hris.field name="middle_name" /><x-hris.field name="extension" /><x-hris.field
                        name="nickname" />
                    <x-hris.field name="email" type="email" required />
                    <x-hris.field name="employee_type" label="Employment type" type="select" :options="array_combine(config('hris.employee_types'), config('hris.employee_types'))" required />
                    @can('accounts.manage')
                        <x-hris.field name="role" type="select" value="staff" required :options="array_combine(array_keys(config('hris.roles')), array_keys(config('hris.roles')))" />
                    @endcan
                    <x-hris.field name="division_id" label="Division" type="select" :options="$divisions->pluck('name', 'id')->all()" />
                    <x-hris.field name="position_id" label="Position" type="select" :options="$positions->pluck('title', 'id')->all()"
                        help="Each employee needs a dedicated position. Add a position before assigning a new employee." />
                    <div class="col-12"><a href="{{ url('/organization/positions') }}">Manage positions</a></div>
                    <x-hris.field name="atms_id" label="Attendance ID" inputmode="numeric" /><x-hris.field name="workweek"
                        type="select" :options="[4 => 'Four days / 10 hours', 5 => 'Five days / 8 hours']" />
                    <x-hris.field name="date_hired" type="date" :value="today('Asia/Manila')->toDateString()"
                        help="For rehiring, enter the new hire date after the previous termination date." /><x-hris.field name="termination_date" type="date"
                        help="Clear for rehiring. Earlier employment remains in the service record." />
                    <x-hris.field name="assume_date" label="Assignment / schedule effective date" type="date"
                        :value="today('Asia/Manila')->toDateString()" required />
                    <x-hris.field name="step_increment" type="number" min="1" max="8" />
                    <x-hris.field name="birthday" type="date" /><x-hris.field name="sex" /><x-hris.field
                        name="mobile_no" label="Mobile number" /><x-hris.field name="contact_person" /><x-hris.field
                        name="contact_person_no" label="Emergency contact number" />
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary"
                    data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save
                    employee</button></div>
        </form>
    </x-hris.modal>
    <x-hris.modal id="service-record-modal" title="Service record">
        <div class="modal-body"><x-hris.table id="service-record-table" :endpoint="'/hr/staff/' . auth()->id() . '/service-record'" :columns="[
            ['key' => 'effective_date', 'label' => 'Effective date'],
            ['key' => 'position_title', 'label' => 'Position'],
            ['key' => 'previous_position_title', 'label' => 'Previous position'],
            ['key' => 'division', 'label' => 'Division'],
            ['key' => 'employee_type', 'label' => 'Employment type'],
        ]" /></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            <a class="btn btn-primary" id="print-service-record" href="{{ url('/hr/staff/'.auth()->id().'/service-record/print') }}"
                target="_blank" rel="noopener">Print</a>
        </div>
    </x-hris.modal>
    <x-hris.modal id="files-modal" title="Personnel files">
        <div class="modal-body">
            <form id="personnel-file-form" enctype="multipart/form-data">
                <div data-form-errors class="alert alert-danger d-none" role="alert" tabindex="-1"></div>
                <div class="row g-3 mb-4"><x-hris.field name="purpose" type="select" :options="[
                    'personnel' => 'Personnel document',
                    'profile' => 'Profile photo',
                    'signature' => 'Signature image',
                ]"
                        required /><x-hris.field name="file" type="file" accept=".pdf,.jpg,.jpeg,.png,.gif"
                        required />
                    <div class="col-12"><button type="submit" class="btn btn-primary">Upload file</button></div>
                </div>
            </form><x-hris.table id="files-table" :endpoint="'/employees/' . auth()->id() . '/files'" :columns="[
                ['key' => 'original_name', 'label' => 'File'],
                ['key' => 'purpose', 'label' => 'Purpose'],
                ['key' => 'size', 'label' => 'Bytes'],
            ]" :actions="[['name' => 'download', 'label' => 'Download']]" />
        </div>
    </x-hris.modal>
    @can('integration.sync')
        <section class="mt-4">
            <h2 class="h5">Partner synchronization</h2>
            <form id="sync-form" data-endpoint="/integration/sync" class="d-flex gap-2 mb-3"><input type="hidden"
                    name="partner" value="ssi"><button type="submit" class="btn btn-outline-primary">Queue SSI user
                    sync</button></form><x-hris.table id="sync-table" endpoint="/integration/runs" :columns="[
                        ['key' => 'id', 'label' => 'Run'],
                        ['key' => 'partner', 'label' => 'Partner'],
                        ['key' => 'status', 'label' => 'Status'],
                        ['key' => 'message', 'label' => 'Result'],
                    ]" />
        </section>
    @endcan
@endsection
@push('scripts')
    @php
        $positionChoices = $positions->map(fn ($position) => ['id' => $position->id, 'division_id' => $position->division_id, 'title' => ($position->code ?? '#'.$position->id).' — '.$position->title, 'employee_id' => $position->employee?->id, 'status' => $position->status])->values();
    @endphp
    <script>
        document.addEventListener('hris:ready', () => {
            const {
                request,
                feedback,
                fillForm,
                bindForm,
                openModal
            } = HRIS, table = document.querySelector('#staff-table').table, form = document.querySelector(
                '#staff-form');
            const positionChoices = @json($positionChoices);
            let currentEmployeeId = null;
            const updatePositions = (selected = form.elements.position_id.value) => {
                const division = Number(form.elements.division_id.value);
                const select = form.elements.position_id;
                select.replaceChildren(new Option('Choose a position', ''));
                positionChoices.filter(position => position.division_id === division && position.status !== 'archived'
                    && (position.employee_id === null || position.employee_id === currentEmployeeId))
                    .forEach(position => select.add(new Option(position.title, position.id)));
                select.value = selected ?? '';
            };
            const updateEmploymentFields = () => {
                const required = form.elements.employee_type.value !== 'Applicant';
                ['division_id', 'position_id', 'date_hired'].forEach(name => form.elements[name].required = required);
            };
            form.elements.division_id.addEventListener('change', () => updatePositions());
            form.elements.employee_type.addEventListener('change', updateEmploymentFields);
            document.querySelector('#create-staff').addEventListener('click', () => {
                currentEmployeeId = null;
                fillForm(form, {});
                updatePositions(null);
                updateEmploymentFields();
                form.dataset.endpoint = '/hr/staff';
                form.dataset.method = 'POST';
                openModal('staff-modal')
            });
            bindForm(form, {
                success: (result, values) => {
                    const employeeId = result.id ?? currentEmployeeId;
                    positionChoices.forEach(position => {
                        if (position.employee_id === employeeId) position.employee_id = null;
                        if (position.id === Number(values.position_id)) position.employee_id = employeeId;
                    });
                    table.load();
                }
            });
            document.querySelector('#staff-table').addEventListener('table:action', async ({
                detail: {
                    action,
                    row
                }
            }) => {
                try {
                    if (action === 'edit') {
                        const employee = await request(`/hr/staff/${row.id}`);
                        currentEmployeeId = row.id;
                        // Restore all choices before filling, then limit to the selected division and owner.
                        form.elements.position_id.replaceChildren(new Option('Choose a position', ''), ...positionChoices.map(position => new Option(position.title, position.id)));
                        fillForm(form, employee);
                        updatePositions(employee.position_id);
                        updateEmploymentFields();
                        form.dataset.endpoint = `/hr/staff/${row.id}`;
                        form.dataset.method = 'PUT';
                        openModal('staff-modal')
                    }
                    if (action === 'profile') location.href = `/profile/${row.id}`;
                    if (action === 'attendance') location.href = `/attendance/${row.id}`;
                    if (action === 'archive' && await HRIS.confirm(
                            `${row.is_archived?'Reactivate':'Archive'} ${row.name}?`)) {
                        feedback((await request(`/hr/staff/${row.id}/archive`, {
                            method: 'PATCH',
                            body: {
                                is_archived: !row.is_archived
                            }
                        })).message);
                        table.load()
                    }
                    if (action === 'service-record') {
                        document.querySelector('#service-record-modal-title').textContent = `Service record — ${row.name}`;
                        document.querySelector('#print-service-record').href = `/hr/staff/${row.id}/service-record/print`;
                        document.querySelector('#service-record-table').dataset.endpoint =
                            `/hr/staff/${row.id}/service-record`;
                        document.querySelector('#service-record-table').table.page = 1;
                        document.querySelector('#service-record-table').table.load();
                        openModal('service-record-modal')
                    }
                    if (action === 'files') {
                        document.querySelector('#files-table').dataset.endpoint =
                            `/employees/${row.id}/files`;
                        document.querySelector('#personnel-file-form').dataset.endpoint =
                            `/employees/${row.id}/files`;
                        document.querySelector('#files-table').table.page = 1;
                        document.querySelector('#files-table').table.load();
                        openModal('files-modal')
                    }
                } catch (error) {
                    feedback(error.message, true)
                }
            });
            bindForm(document.querySelector('#personnel-file-form'), {
                success: () => document.querySelector('#files-table').table.load()
            });
            document.querySelector('#files-table').addEventListener('table:action', ({
                detail: {
                    row
                }
            }) => {
                window.open(`/files/${row.id}`, '_blank', 'noopener')
            });
            const sync = document.querySelector('#sync-form');
            if (sync) bindForm(sync, {
                success: () => document.querySelector('#sync-table').table.load()
            });
        });
    </script>
@endpush
