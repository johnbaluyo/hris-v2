@extends('layouts.hris')
@section('title', 'Service record — '.$employee->name)
@section('actions')<button type="button" class="btn btn-primary" data-print>Print</button>@endsection
@section('content')
    <h1 class="h4">Service record</h1>
    <p>{{ $employee->name }}</p>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Effective date</th>
                    <th>Position</th>
                    <th>Previous position</th>
                    <th>Division</th>
                    <th>Employment type</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php($row = $record->after ?? [])
                    <tr>
                        <td>{{ $row['effective_date'] ?? '—' }}</td>
                        <td>{{ $row['position_title'] ?? '—' }}</td>
                        <td>{{ $row['previous_position_title'] ?? '—' }}</td>
                        <td>{{ $row['division'] ?? '—' }}</td>
                        <td>{{ $row['employee_type'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No service records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
@push('scripts')
    <script>
        window.addEventListener('load', () => window.print(), { once: true });
    </script>
@endpush
