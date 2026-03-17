@extends('layouts.app', ['title' => 'Incoming Requests'])
@section('content')
@php
    $pendingCount = $submissions->filter(fn ($item) => !$item->finance_completed_at)->count();
    $completedCount = $submissions->filter(fn ($item) => (bool) $item->finance_completed_at)->count();
    $highUrgencyCount = $submissions->where('urgency_level', 3)->count();
@endphp
<div class="page-header">
    <div>
        <h1>Incoming Requests</h1>
        <p>Review new tasks, assign technicians, and monitor related jobs in one place.</p>
    </div>
</div>

<div class="stats-grid four-up admin-inbox-cards">
    <div class="stat-card glassy-card"><span>Total Incoming</span><strong>{{ $submissions->count() }}</strong><small>All jobs in your admin scope.</small></div>
    <div class="stat-card glassy-card"><span>Pending Attention</span><strong>{{ $pendingCount }}</strong><small>Any job waiting for finance close-out.</small></div>
    <div class="stat-card glassy-card"><span>Completed</span><strong>{{ $completedCount }}</strong><small>Jobs closed after finance form completion.</small></div>
    <div class="stat-card glassy-card"><span>High Urgency</span><strong>{{ $highUrgencyCount }}</strong><small>Requests marked as urgent.</small></div>
</div>

<section class="panel inbox-panel" style="margin-top:20px;">
    <div class="panel-head inbox-head">
        <div>
            <h3>Request Inbox</h3>
            <p class="helper-text">Child jobs are indented under their original request for easier tracking.</p>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table hierarchy-table inbox-table">
            <thead>
                <tr>
                    <th>Job ID</th>
                    <th>Client</th>
                    <th>Role</th>
                    <th>Type</th>
                    <th>Department</th>
                    <th>Urgency</th>
                    <th>Status</th>
                    <th>Approval</th>
                    <th>Assign Technician</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @php($technicians = \App\Models\User::where('role', \App\Models\User::ROLE_TECHNICIAN)->orderBy('name')->get())
                @forelse($submissions as $submission)
                    <tr class="{{ $submission->is_related_child ? 'child-row' : '' }}">
                        <td>
                            <div class="job-code-shell {{ $submission->is_related_child ? 'is-child' : '' }}">
                                @if($submission->is_related_child)
                                    <span class="child-connector">↳</span>
                                @endif
                                <div>
                                    <strong>{{ $submission->request_code }}</strong>
                                    @if($submission->relatedRequest)
                                        <div class="helper-text">Related to {{ $submission->relatedRequest->request_code }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="table-primary-cell">{{ $submission->full_name }}</div>
                            <div class="helper-text">{{ $submission->phone_number }}</div>
                        </td>
                        <td>{{ $submission->user->roleLabel() }}</td>
                        <td>{{ $submission->requestType->name }}</td>
                        <td>{{ $submission->department?->name ?? '-' }}</td>
                        <td><span class="badge {{ $submission->urgencyBadgeClass() }}">{{ $submission->urgencyLabel() }}</span></td>
                        <td><span class="badge {{ $submission->adminWorkflowBadgeClass() }}">{{ $submission->adminWorkflowLabel() }}</span></td>
                        <td><span class="badge {{ $submission->adminApprovalBadgeClass() }}">{{ $submission->adminApprovalLabel() }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('admin.incoming-requests.assign', $submission) }}" class="inline-assign-form premium-inline-form">
                                @csrf
                                <select name="assigned_technician_id" required {{ $submission->admin_approval_status !== "approved" ? "disabled" : "" }}>
                                    <option value="">Select</option>
                                    @foreach($technicians as $technician)
                                        <option value="{{ $technician->id }}" {{ $submission->assigned_technician_id == $technician->id ? 'selected' : '' }}>{{ $technician->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn small primary" type="submit" {{ $submission->admin_approval_status !== "approved" ? "disabled" : "" }}>Save</button>
                            </form>
                            @if($submission->assignedTechnician)
                                <div class="helper-text" style="margin-top:6px;">Current: {{ $submission->assignedTechnician->name }}</div>
                            @elseif($submission->admin_approval_status !== "approved")
                                <div class="helper-text" style="margin-top:6px;">Approve first before assigning technician.</div>
                            @endif
                        </td>
                        <td><a class="btn small ghost" href="{{ route('admin.incoming-requests.show', $submission) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10">No incoming request yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
