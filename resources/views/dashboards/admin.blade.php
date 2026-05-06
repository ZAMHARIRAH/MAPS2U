@extends('layouts.app', ['title' => 'Admin Dashboard'])
@section('content')
<div class="page-header"><div><h1>{{ $dashboardTitle ?? 'Admin Dashboard' }}</h1><p>{{ $dashboardIntro ?? ($admin->roleLabel() . ' is signed in.') }}</p></div></div>
<div class="stats-grid four-up">
    <div class="stat-card"><span>Total Task</span><strong>{{ $totalTask }}</strong></div>
    <div class="stat-card"><span>Pending</span><strong>{{ $pendingCount }}</strong></div>
    <div class="stat-card"><span>Completed</span><strong>{{ $completedCount }}</strong></div>
    <div class="stat-card"><span>Complete (%)</span><strong>{{ $completionPercent }}%</strong></div>
</div>

<section class="panel" style="margin-top:16px;">
    <div class="panel-head">
        <div>
            <h3>Filter by Urgency</h3>
            <p>Click a level to show only active jobs with that urgency.</p>
        </div>
        @if(!empty($urgencyFilter))
            <a class="btn small ghost" href="{{ request()->url() }}">Reset Urgency</a>
        @endif
    </div>
    <div class="action-row" style="gap:10px; flex-wrap:wrap;">
        <a class="btn small {{ ($urgencyFilter ?? '') === 'low' ? 'primary' : 'ghost' }}" style="background:#16a34a;color:#fff;border-color:#16a34a;" href="{{ request()->url() . '?urgency=low' }}">Low ({{ $urgencyCounts['low'] ?? 0 }})</a>
        <a class="btn small {{ ($urgencyFilter ?? '') === 'medium' ? 'primary' : 'ghost' }}" style="background:#facc15;color:#111827;border-color:#facc15;" href="{{ request()->url() . '?urgency=medium' }}">Medium ({{ $urgencyCounts['medium'] ?? 0 }})</a>
        <a class="btn small {{ ($urgencyFilter ?? '') === 'high' ? 'primary' : 'ghost' }}" style="background:#dc2626;color:#fff;border-color:#dc2626;" href="{{ request()->url() . '?urgency=high' }}">High ({{ $urgencyCounts['high'] ?? 0 }})</a>
    </div>
</section>

<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Latest Incoming Requests</h3>@if(empty($mapsScope))<a class="btn small accent" href="{{ route('admin.incoming-requests.index') }}">Open Request Inbox</a>@else<a class="btn small accent" href="{{ route('admin.maps.finance.index') }}">Open Finance MAPS</a>@endif</div>
    <table class="table hierarchy-table">
        <thead><tr><th>Job ID</th><th>Date Request</th><th>Client</th><th>Type</th><th>Urgency</th><th>Status</th><th>Technician</th><th>Location</th><th>Department</th><th>Action</th></tr></thead>
        <tbody>
            @forelse($recentRequests as $item)
                <tr class="{{ ($item->is_related_child ?? false) ? 'child-row' : '' }}">
                    <td>
                        <div class="job-code-shell {{ ($item->is_related_child ?? false) ? 'is-child' : '' }}">
                            @if($item->is_related_child ?? false)<span class="child-connector">↳</span>@endif
                            <div>
                                <strong>{{ $item->request_code }}</strong>
                                @if($item->relatedRequest)<div class="helper-text">Related to {{ $item->relatedRequest->request_code }}</div>@endif
                            </div>
                        </div>
                    </td>
                    <td>{{ $item->created_at?->timezone('Asia/Kuala_Lumpur')->format('d M Y h:i A') ?? '-' }}</td>
                    <td>{{ $item->full_name }}</td>
                    <td>{{ $item->requestType->name }}</td>
                    <td><span class="badge {{ $item->urgencyBadgeClass() }}">{{ $item->urgencyLabel() }}</span></td>
                    <td><span class="badge {{ $item->adminWorkflowBadgeClass() }}">{{ $item->adminWorkflowLabel() }}</span></td>
                    <td>{{ $item->assignedTechnician?->name ?? '-' }}</td>
                    <td>{{ $item->location?->name ?? '-' }}</td>
                    <td>{{ $item->department?->name ?? '-' }}</td>
                    <td>
                        @if(!empty($mapsScope))
                            @if($item->hasFinancePending() || $item->finance_completed_at)
                                <a href="{{ route('admin.maps.finance.show', $item) }}">Open Finance</a>
                            @else
                                <a href="{{ route('admin.incoming-requests.show', $item) }}">View</a>
                            @endif
                        @else
                            <a href="{{ route('admin.incoming-requests.show', $item) }}">View</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10">No requests submitted yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="pagination-wrap">{{ $recentRequests->links() }}</div>
</section>

<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Finance Evidence Alerts</h3><a class="btn small ghost" href="{{ !empty($mapsScope) ? route('admin.maps.finance.index') : route('admin.finance.index') }}">Open Finance</a></div>
    <table class="table">
        <thead><tr><th>Job ID</th><th>Date Request</th><th>Client</th><th>Technician</th><th>Evidence Status</th><th>Action</th></tr></thead>
        <tbody>
            @forelse($financeAlerts as $item)
                <tr>
                    <td>{{ $item->request_code }}</td>
                    <td>{{ $item->created_at?->timezone('Asia/Kuala_Lumpur')->format('d M Y h:i A') ?? '-' }}</td>
                    <td>{{ $item->full_name }}</td>
                    <td>{{ $item->assignedTechnician?->name ?? '-' }}</td>
                    <td><span class="badge warning">Evidence Ready</span></td>
                    <td>
                        @if(auth()->user()->isViewer())
                            @if($item->finance_completed_at)
                                <a class="btn small accent" href="{{ !empty($mapsScope) ? route('admin.maps.finance.show', $item) : route('admin.finance.show', $item) }}">View Finance Form</a>
                            @else
                                <span class="helper-text">Not uploaded yet</span>
                            @endif
                        @else
                            <a class="btn small accent" href="{{ !empty($mapsScope) ? route('admin.maps.finance.show', $item) : route('admin.finance.show', $item) }}">Open Finance Form</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No finance item is waiting right now.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>
@endsection
