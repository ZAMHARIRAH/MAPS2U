@extends('layouts.app', ['title' => 'Bulk Import'])
@section('content')
<div class="page-header"><div><h1>Bulk Import</h1><p>Import legacy MAPS2U WO/job requests from CSV. Existing WO No/request code rows will be updated.</p></div></div>
<section class="panel">
    <form method="POST" action="{{ route('admin.bulk-import.store') }}" enctype="multipart/form-data" class="form-grid">
        @csrf
        <div class="form-group full"><label>CSV File</label><input type="file" name="csv_file" accept=".csv,.txt" required></div>
        <label class="checkbox-line full"><input type="checkbox" name="dry_run" value="1" checked> Dry run first, validate only without saving</label>
        <div class="full actions-inline"><button class="btn primary" type="submit">Upload CSV</button></div>
    </form>
</section>
<section class="panel">
    <h3>Required CSV Headers</h3>
    <p class="helper-text">WO No, Timestamp, Branch, Type Of Request, Task Title, Issues, Urgency of Needed, Attachment, Location (HQ), Task Approval, PIC MAPS, End Date, Status WO, Remarks/ Notes, Email PIC, Latest Progress, Duration Complete, Duration Pending, Email Responder, Month, Payment Amount</p>
    <p class="helper-text">
        Branch filled means Kindergarten role and Branch will auto-create if missing. Location (HQ) filled means HQ Staff role and HQ location will auto-create if missing.<br>
        Email PIC maps to technician by email first. PIC MAPS maps to technician name if Email PIC is empty. Email Responder is used for client dashboard matching only; no client account is auto-created.<br>
        Task Title maps to question 1. Issues maps to question 2. Attachment stores Google Drive links as clickable legacy links. Payment Amount becomes approved quotation amount for reports.
    </p>
</section>
@if(session('import_results'))
<section class="panel">
    <h3>Import Result</h3>
    <table class="table"><thead><tr><th>Row</th><th>Status</th><th>Message</th></tr></thead><tbody>
        @foreach(session('import_results') as $result)
            <tr><td>{{ $result['line'] }}</td><td><span class="badge {{ $result['status'] === 'OK' ? 'success' : 'danger' }}">{{ $result['status'] }}</span></td><td>{{ $result['message'] }}</td></tr>
        @endforeach
    </tbody></table>
</section>
@endif
<section class="panel">
    <h3>Recent Bulk Imported Jobs</h3>
    <table class="table"><thead><tr><th>WO No</th><th>Client</th><th>Email Responder</th><th>Technician</th><th>Location</th><th>Role</th><th>Status</th><th>Created</th></tr></thead><tbody>
        @forelse($recentImports as $item)
            <tr><td><a href="{{ route('admin.incoming-requests.show', $item) }}">{{ $item->request_code }}</a></td><td>{{ $item->full_name }}</td><td>{{ $item->legacy_import_email ?? $item->user?->email ?? '-' }}</td><td>{{ $item->assignedTechnician?->name ?? '-' }}</td><td>{{ $item->location?->name ?? '-' }}</td><td>{{ $item->effectiveClientRole() ?? '-' }}</td><td>{{ $item->status }}</td><td>{{ $item->created_at?->format('d/m/Y H:i') }}</td></tr>
        @empty
            <tr><td colspan="8">No bulk import data yet.</td></tr>
        @endforelse
    </tbody></table>
</section>
@endsection
