@extends('layouts.app', ['title' => 'SSU Report'])
@section('content')
<div class="page-header">
    <div>
        <h1>SSU Report</h1>
        <p>Monitor request activity in your assigned branches, including branch totals, task totals, status, and job request details.</p>
    </div>
</div>

<section class="panel">
    <form method="GET" class="report-filter-grid no-print">
        <div class="field-block"><span>Branch</span><select name="location_id"><option value="">All Branches</option>@foreach($locations as $location)<option value="{{ $location->id }}" {{ ($filters['location_id'] ?? '') == $location->id ? 'selected' : '' }}>{{ $location->name }}</option>@endforeach</select></div>
        <div class="field-block"><span>Status</span><select name="status"><option value="">All Status</option>@foreach($statusOptions as $status)<option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ $status }}</option>@endforeach</select></div>
        <div class="field-block"><span>Date From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
        <div class="field-block"><span>Date To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
        <div class="action-row"><button class="btn primary" type="submit">Filter</button><a class="btn ghost" href="{{ route('client.reports.index') }}">Reset</a></div>
    </form>
</section>

<section class="panel" style="margin-top:20px;">
    <div class="page-header"><div><h3>Job Request Details</h3></div></div>
    <p class="print-filter-note"><strong>Status:</strong> {{ $filters['status'] ?? 'All Status' }} &nbsp; | &nbsp; <strong>Date Range:</strong> {{ ($filters['date_from'] ?? '-') . " to " . ($filters['date_to'] ?? '-') }} &nbsp; | &nbsp; <strong>Branch:</strong> {{ optional($locations->firstWhere('id', $filters['location_id'] ?? null))->name ?? 'All Branches' }}</p>
    <div class="table-responsive"><table class="table"><thead><tr><th>Job ID</th><th>Client</th><th>Role</th><th>Type</th><th>Branch</th><th>Department</th><th>Urgency</th><th>Status</th><th>Assigned Technician</th></tr></thead><tbody>@forelse($items as $item)<tr><td>{{ $item->request_code }}</td><td>{{ $item->full_name }}</td><td>{{ $item->user?->roleLabel() }}</td><td>{{ $item->requestType?->name ?? '-' }}</td><td>{{ $item->location?->name ?? '-' }}</td><td>{{ $item->department?->name ?? '-' }}</td><td>{{ $item->urgencyLabel() }}</td><td>{{ $item->adminWorkflowLabel() }}</td><td>{{ $item->assignedTechnician?->name ?? '-' }}</td></tr>@empty<tr><td colspan="9">No records found.</td></tr>@endforelse</tbody></table></div><div class="pagination-wrap">{{ $items->links() }}</div>
</section>
@endsection
