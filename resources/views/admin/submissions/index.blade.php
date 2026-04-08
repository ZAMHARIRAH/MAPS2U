@extends('layouts.app', ['title' => 'Incoming Requests'])
@section('content')
@php
    $submissionCollection = collect($submissions->items());
    $pendingCount = $submissionCollection->filter(fn ($item) => !$item->finance_completed_at)->count();
    $completedCount = $submissionCollection->filter(fn ($item) => (bool) $item->finance_completed_at)->count();
    $highUrgencyCount = $submissionCollection->where('urgency_level', 3)->count();
@endphp
<div class="page-header"><div><h1>Incoming Requests</h1><p> </p></div></div>
<div class="stats-grid four-up admin-inbox-cards">
    <div class="stat-card glassy-card"><span>This Page</span><strong>{{ $submissions->count() }}</strong><small>Visible rows after filters.</small></div>
    <div class="stat-card glassy-card"><span>Pending Attention</span><strong>{{ $pendingCount }}</strong><small>Any job waiting for finance close-out.</small></div>
    <div class="stat-card glassy-card"><span>Completed</span><strong>{{ $completedCount }}</strong><small>Jobs closed after finance form completion.</small></div>
    <div class="stat-card glassy-card"><span>High Urgency</span><strong>{{ $highUrgencyCount }}</strong><small>Requests marked as urgent.</small></div>
</div>
<section class="panel inbox-panel power-filter-panel" style="margin-top:20px;">
    <div class="panel-head inbox-head power-filter-head">
        <div>
            <h3>Power Filters</h3>
            <p class="helper-text"> </p>
        </div>
        <span class="filter-badge">Compact View</span>
    </div>
    <form method="GET" class="filter-toolbar-grid compact-filter-grid">
        <div class="filter-search-wrap">
            <label class="helper-text">Quick Search</label>
            <input type="text" name="search" placeholder="Search job ID, client, phone, type, {{ $clientRole === \App\Models\User::CLIENT_HQ ? 'department' : 'branch' }}, technician" value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="filter-grid-two">
            <div><label class="helper-text">Status Job</label><select name="status"><option value="">All Status</option>@foreach($statusOptions as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
            <div><label class="helper-text">Approval</label><select name="admin_approval_status"><option value="">All Approval</option><option value="pending" @selected(($filters['admin_approval_status'] ?? '') === 'pending')>Pending Admin Approval</option><option value="approved" @selected(($filters['admin_approval_status'] ?? '') === 'approved')>Approved</option><option value="rejected" @selected(($filters['admin_approval_status'] ?? '') === 'rejected')>Rejected</option></select></div>
            <div><label class="helper-text">Technician</label><select name="assigned_technician_id"><option value="">All Technicians</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}" @selected((string)($filters['assigned_technician_id'] ?? '') === (string)$technician->id)>{{ $technician->name }}</option>@endforeach</select></div>
            <div><label class="helper-text">Urgency</label><select name="urgency_level"><option value="">All Urgency</option><option value="3" @selected(($filters['urgency_level'] ?? '') === '3')>High</option><option value="2" @selected(($filters['urgency_level'] ?? '') === '2')>Medium</option><option value="1" @selected(($filters['urgency_level'] ?? '') === '1')>Low</option></select></div>
            <div><label class="helper-text">Request Type</label><select name="request_type_id"><option value="">All Request Type</option>@foreach($requestTypes as $type)<option value="{{ $type->id }}" @selected((string)($filters['request_type_id'] ?? '') === (string)$type->id)>{{ $type->name }}</option>@endforeach</select></div>
            @php($isViewer = auth()->user()?->isViewer())
            @if($isViewer)
                <div><label class="helper-text">Location (HQ Staff)</label><select name="hq_location_id"><option value="">All Location</option>@foreach($hqLocations as $location)<option value="{{ $location->id }}" @selected((string)($filters['hq_location_id'] ?? '') === (string)$location->id)>{{ $location->name }}</option>@endforeach</select></div>
                <div><label class="helper-text">Branch (Kindergarten)</label><select name="branch_location_id"><option value="">All Branches</option>@foreach($branchLocations as $location)<option value="{{ $location->id }}" @selected((string)($filters['branch_location_id'] ?? '') === (string)$location->id)>{{ $location->name }}</option>@endforeach</select></div>
                <div><label class="helper-text">Department</label><select name="department_id"><option value="">All Department</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)($filters['department_id'] ?? '') === (string)$department->id)>{{ $department->name }}</option>@endforeach</select></div>
            @elseif($clientRole === \App\Models\User::CLIENT_HQ)
                <div><label class="helper-text">Department</label><select name="department_id"><option value="">All Department</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)($filters['department_id'] ?? '') === (string)$department->id)>{{ $department->name }}</option>@endforeach</select></div>
                <div><label class="helper-text">Location</label><select name="location_id"><option value="">All Location</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string)($filters['location_id'] ?? '') === (string)$location->id)>{{ $location->name }}</option>@endforeach</select></div>
            @else
                <div><label class="helper-text">Branch</label><select name="location_id"><option value="">All Branches</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string)($filters['location_id'] ?? '') === (string)$location->id)>{{ $location->name }}</option>@endforeach</select></div>
            @endif
            <div class="filter-grid-two nested-date-grid">
                <div><label class="helper-text">Created From</label><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
                <div><label class="helper-text">Created To</label><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
            </div>
        </div>
        <div class="action-row compact-filter-actions"><button class="btn primary" type="submit">Apply Filters</button>@if($submissions->total() > 0)<a class="btn accent" href="{{ route('admin.incoming-requests.print-filtered', request()->query()) }}" target="_blank">Print</a>@endif<a class="btn ghost" href="{{ route('admin.incoming-requests.index') }}">Reset</a></div>
    </form>
</section>
<section class="panel inbox-panel" style="margin-top:20px;">
    <div class="panel-head inbox-head"><div><h3>Request Inbox</h3><p class="helper-text">Showing {{ $submissions->firstItem() ?? 0 }} - {{ $submissions->lastItem() ?? 0 }} of {{ $submissions->total() }} record(s).</p></div></div>
    @php($showBothLocations = auth()->user()?->isViewer())
    @php($columnCount = $showBothLocations ? 12 : 11)
    <div class="table-responsive"><table class="table hierarchy-table inbox-table"><thead><tr><th>Job ID</th><th>Client</th><th>Role</th><th>Type</th>@if($showBothLocations)<th>Location (HQ Staff)</th><th>Branches (Kindergarten)</th>@elseif(($clientRole ?? null) === \App\Models\User::CLIENT_HQ)<th>Location</th>@else<th>Branches</th>@endif<th>Department</th><th>Urgency</th><th>Status</th><th>Approval</th><th>Assign Technician</th><th>Action</th></tr></thead><tbody>
        @forelse($submissions as $submission)
            <tr><td><strong>{{ $submission->request_code }}</strong>@if($submission->relatedRequest)<div class="helper-text">Related to {{ $submission->relatedRequest->request_code }}</div>@endif</td><td><div class="table-primary-cell">{{ $submission->full_name }}</div><div class="helper-text">{{ $submission->phone_number }}</div></td><td>{{ $submission->user->roleLabel() }}</td><td>{{ $submission->requestType->name }}</td>@if($showBothLocations)<td>{{ $submission->user->sub_role === \App\Models\User::CLIENT_HQ ? ($submission->location?->name ?? '-') : '-' }}</td><td>{{ $submission->user->sub_role === \App\Models\User::CLIENT_KINDERGARTEN ? ($submission->location?->name ?? '-') : '-' }}</td>@elseif(($clientRole ?? null) === \App\Models\User::CLIENT_HQ)<td>{{ $submission->location?->name ?? '-' }}</td>@else<td>{{ $submission->location?->name ?? '-' }}</td>@endif<td>{{ $submission->department?->name ?? '-' }}</td><td><span class="badge {{ $submission->urgencyBadgeClass() }}">{{ $submission->urgencyLabel() }}</span></td><td><span class="badge {{ $submission->adminWorkflowBadgeClass() }}">{{ $submission->adminWorkflowLabel() }}</span></td><td><span class="badge {{ $submission->adminApprovalBadgeClass() }}">{{ $submission->adminApprovalLabel() }}</span></td><td>@if($submission->assignedTechnician)<div class="table-primary-cell">{{ $submission->assignedTechnician->name }}</div><div class="helper-text" style="margin-top:6px;">Assigned from request detail form.</div>@elseif($submission->admin_approval_status !== 'approved')<div class="helper-text">Approve first before assigning technician.</div>@else<div class="helper-text">Not assigned yet.</div>@endif</td><td><a class="btn small ghost" href="{{ route('admin.incoming-requests.show', $submission) }}">View</a></td></tr>
        @empty<tr><td colspan="{{ $columnCount }}">No incoming request found for the selected filter.</td></tr>@endforelse
    </tbody></table></div>
    <div style="margin-top:16px;">{{ $submissions->links() }}</div>
</section>
@endsection
