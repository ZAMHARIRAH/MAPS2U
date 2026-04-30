@extends('layouts.app', ['title' => 'Bulk Import'])
@section('content')
<div class="page-header"><div><h1>Bulk Import</h1><p> </p></div></div>
<section class="panel">
    <form method="POST" action="{{ route('admin.bulk-import.store') }}" enctype="multipart/form-data" class="form-grid">
        @csrf
        <div class="form-group full"><label>CSV File</label><input type="file" name="csv_file" accept=".csv,.txt" required></div>
        <label class="checkbox-line full"><input type="checkbox" name="dry_run" value="1" checked> Dry run first, validate only without saving</label>
        <div class="full actions-inline"><button class="btn primary" type="submit">Upload CSV</button></div>
    </form>
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
