@extends("layouts.hris")
@section("title", "Official business")
@section("subtitle", "File, review and track approved work away from the office.")
@section("actions")<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ob-modal">File official
 business</button>@endsection
@section("content")
 <x-hris.table id="ob-table" endpoint="/official-business/data" search filters :columns="[
     ['key' => 'employee.name', 'label' => 'Employee'],
     ['key' => 'business_date', 'label' => 'OB date'],
     ['key' => 'type_label', 'label' => 'Type'],
     ['key' => 'destination', 'label' => 'Destination'],
     ['key' => 'current_status', 'label' => 'Status'],
 ]" :actions="[
     ['name' => 'details', 'label' => 'Details'],
     ['name' => 'print', 'label' => 'Print'],
     ['name' => 'review', 'label' => 'Review / cancel'],
     ['name' => 'evidence', 'label' => 'Evidence', 'editable' => true],
 ]">
  <div><label for="ob-status" class="form-label">Status</label><select id="ob-status" name="status" class="form-select"
    data-table-filter>
    <option value="">All</option>
    <option>For DC Approval</option>
    <option>For ED Approval</option>
    <option>Approved</option>
    <option>Cancelled</option>
   </select></div>
 </x-hris.table>
 <x-hris.modal id="ob-modal" title="File official business">
  <form id="ob-form" data-endpoint="/official-business">
   <div class="modal-body">
    <div data-form-errors class="alert alert-danger d-none" tabindex="-1"></div>
    <div class="row g-3"><x-hris.field name="business_date" type="date" required /><x-hris.field name="type"
      type="select" :options="[1 => 'Work-related', 2 => 'Medical', 3 => 'Bereavement']" required /><x-hris.field name="departure_time" type="time" /><x-hris.field
      name="return_time" type="time" /><x-hris.field name="origin" /><x-hris.field name="destination"
      required /><x-hris.field name="purpose" type="textarea" required class="col-12" />
     <div class="col-md-6 form-check ms-2"><input type="checkbox" class="form-check-input" id="go-directly"
       name="go_directly"><label class="form-check-label" for="go-directly">Go directly to destination</label></div>
     <div class="col-md-6 form-check ms-2"><input type="checkbox" class="form-check-input" id="return-office"
       name="return_to_office" checked><label class="form-check-label" for="return-office">Return to office</label></div>
    </div>
    <p class="small text-body-secondary mt-3 mb-0">Evidence can be uploaded from the request table after approval.</p>
   </div>
   <div class="modal-footer"><button class="btn btn-primary" type="submit">File request</button></div>
  </form>
 </x-hris.modal>
 <x-hris.modal id="request-action" title="Review request">
  <form id="action-form">
   <div class="modal-body">
    <div data-form-errors class="alert alert-danger d-none" tabindex="-1"></div><x-hris.field name="action"
     type="select" :options="[
         'approve_dc' => 'Approve as division chief',
         'reject_dc' => 'Disapprove as division chief',
         'approve_ed' => 'Approve as ED/OIC',
         'reject_ed' => 'Disapprove as ED/OIC',
         'cancel' => 'Cancel my request',
     ]" required class="col-12" /><x-hris.field name="reason" type="textarea"
     class="col-12" />
   </div>
   <div class="modal-footer"><button class="btn btn-primary">Submit decision</button></div>
  </form>
 </x-hris.modal>
 <x-hris.modal id="ob-evidence" title="Official business evidence">
  <form id="evidence-form" enctype="multipart/form-data">
   <div class="modal-body">
    <div data-form-errors class="alert alert-danger d-none" tabindex="-1"></div>
    <p class="small text-body-secondary">Upload a PDF, JPG or PNG after approval. Uploading a replacement preserves the
     earlier private file record for audit.</p><x-hris.field name="attachment" label="Evidence file" type="file"
     accept=".pdf,.jpg,.jpeg,.png" required class="col-12" /><a id="current-evidence"
     class="btn btn-sm btn-outline-secondary mt-3 d-none" target="_blank" rel="noopener">Download current evidence</a>
   </div>
   <div class="modal-footer"><button class="btn btn-primary">Upload evidence</button></div>
  </form>
 </x-hris.modal>
@endsection
@push("scripts")
 <script>
  document.addEventListener('hris:ready', () => {
   const table = document.querySelector('#ob-table'),
    form = document.querySelector('#ob-form'),
    action = document.querySelector('#action-form'),
    evidence = document.querySelector('#evidence-form');
   HRIS.bindForm(form, {
    success: () => table.table.load()
   });
   HRIS.bindForm(action, {
    success: () => table.table.load()
   });
   HRIS.bindForm(evidence, {
    success: () => table.table.load()
   });
   table.addEventListener('table:action', ({
    detail: {
     action: kind,
     row
    }
   }) => {
    if (kind === 'print') window.open(`/official-business/${row.id}/print`, '_blank', 'noopener');
    else if (kind === 'details') HRIS.showDetails('Official business', row);
    else if (kind === 'review') {
     HRIS.fillForm(action, {});
     action.dataset.endpoint = `/official-business/${row.id}/transition`;
     HRIS.openModal('request-action')
    } else {
     evidence.reset();
     evidence.dataset.endpoint = `/official-business/${row.id}/evidence`;
     const link = document.querySelector('#current-evidence');
     link.classList.toggle('d-none', !row.attachment_url);
     if (row.attachment_url) link.href = row.attachment_url;
     HRIS.openModal('ob-evidence')
    }
   })
  });
 </script>
@endpush
