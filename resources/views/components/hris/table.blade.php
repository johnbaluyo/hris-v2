@props(['id', 'endpoint', 'columns', 'actions' => [], 'search' => false, 'filters' => false])
<section id="{{ $id }}" class="card hris-table" data-table data-endpoint="{{ $endpoint }}" data-columns="{{ json_encode($columns) }}" data-actions="{{ json_encode($actions) }}" aria-label="{{ $attributes->get('aria-label', 'Records') }}">
    @if($search || $filters)<div class="card-header bg-body d-flex flex-wrap gap-3 align-items-end">
        @if($search)<div class="flex-grow-1"><label for="{{ $id }}-search" class="form-label small">Search records</label><input id="{{ $id }}-search" type="search" name="search" class="form-control" placeholder="Start typing to search…" data-table-filter autocomplete="off"></div>@endif
        {{ $slot }}
    </div>@endif
    <div class="table-feedback alert alert-danger m-3 d-none" role="alert"></div>
    <div class="table-loading px-3 py-2 text-body-secondary d-none" role="status"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading records…</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr>@foreach($columns as $column)<th scope="col">{{ $column['label'] }}</th>@endforeach @if(count($actions))<th scope="col">Actions</th>@endif</tr></thead><tbody></tbody></table></div>
    <div class="card-footer bg-body d-flex flex-wrap justify-content-between align-items-center gap-2"><span class="table-count small text-body-secondary" aria-live="polite"></span><nav aria-label="Record pages" class="d-flex gap-2 align-items-center"><button type="button" class="btn btn-sm btn-outline-secondary" data-page="previous">Previous</button><span class="table-page small"></span><button type="button" class="btn btn-sm btn-outline-secondary" data-page="next">Next</button><button type="button" class="btn btn-sm btn-outline-secondary d-none" data-retry>Retry</button></nav></div>
</section>
