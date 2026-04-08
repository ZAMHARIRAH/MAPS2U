@extends('layouts.app', ['title' => 'Admin Dashboard'])
@section('content')
<div class="page-header"><div><h1>Admin Dashboard</h1><p>{{ $admin->roleLabel() }} is signed in.</p></div></div>
<div class="stats-grid four-up">
    <div class="stat-card"><span>Total Task</span><strong>{{ $totalTask }}</strong></div>
    <div class="stat-card"><span>Pending</span><strong>{{ $pendingCount }}</strong></div>
    <div class="stat-card"><span>Completed</span><strong>{{ $completedCount }}</strong></div>
    <div class="stat-card"><span>Complete (%)</span><strong>{{ $completionPercent }}%</strong></div>
</div>
<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Latest Incoming Requests</h3><a class="btn small accent" href="{{ route('admin.incoming-requests.index') }}">Open Request Inbox</a></div>
    <table class="table hierarchy-table">
        <thead><tr><th>Job ID</th><th>Client</th><th>Type</th><th>Urgency</th><th>Status</th><th>Technician</th><th>Location</th><th>Department</th><th>Action</th></tr></thead>
        <tbody>
            @forelse($recentRequests as $item)
                <tr class="{{ $item->is_related_child ? 'child-row' : '' }}">
                    <td>
                        <div class="job-code-shell {{ $item->is_related_child ? 'is-child' : '' }}">
                            @if($item->is_related_child)<span class="child-connector">↳</span>@endif
                            <div>
                                <strong>{{ $item->request_code }}</strong>
                                @if($item->relatedRequest)<div class="helper-text">Related to {{ $item->relatedRequest->request_code }}</div>@endif
                            </div>
                        </div>
                    </td>
                    <td>{{ $item->full_name }}</td>
                    <td>{{ $item->requestType->name }}</td>
                    <td><span class="badge {{ $item->urgencyBadgeClass() }}">{{ $item->urgencyLabel() }}</span></td>
                    <td><span class="badge {{ $item->adminWorkflowBadgeClass() }}">{{ $item->adminWorkflowLabel() }}</span></td>
                    <td>{{ $item->assignedTechnician?->name ?? '-' }}</td>
                    <td>{{ $item->location?->name ?? '-' }}</td>
                    <td>{{ $item->department?->name ?? '-' }}</td>
                    <td><a href="{{ route('admin.incoming-requests.show', $item) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="9">No requests submitted yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>

<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Finance Evidence Alerts</h3><a class="btn small ghost" href="{{ route('admin.finance.index') }}">Open Finance</a></div>
    <table class="table">
        <thead><tr><th>Job ID</th><th>Client</th><th>Technician</th><th>Evidence Status</th><th>Action</th></tr></thead>
        <tbody>
            @forelse($financeAlerts as $item)
                <tr>
                    <td>{{ $item->request_code }}</td>
                    <td>{{ $item->full_name }}</td>
                    <td>{{ $item->assignedTechnician?->name ?? '-' }}</td>
                    <td><span class="badge warning">Evidence Ready</span></td>
                    <td>
                        @if(auth()->user()->isViewer())
                            @if($item->finance_completed_at)
                                <a class="btn small accent" href="{{ route('admin.finance.show', $item) }}">View Finance Form</a>
                            @else
                                <span class="helper-text">Not uploaded yet</span>
                            @endif
                        @else
                            <a class="btn small accent" href="{{ route('admin.finance.show', $item) }}">Open Finance Form</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No finance item is waiting right now.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>
@endsection
