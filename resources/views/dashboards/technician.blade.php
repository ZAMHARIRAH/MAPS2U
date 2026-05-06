@extends('layouts.app', ['title' => 'Technician Dashboard'])

@section('content')
<div class="page-header premium-page-header">
    <div>
        <h1>Technician Dashboard</h1>
        <p>Welcome back, {{ $user->name }}. Here is the latest overview of your assigned job requests.</p>
    </div>
    <a class="btn primary" href="{{ route('technician.job-requests.index') }}">Open All Job Requests</a>
</div>

<div class="stats-grid admin-inbox-cards">
    <div class="stat-card premium-stat-card"><span>Total Assigned Jobs</span><strong>{{ $jobCount }}</strong><small>All jobs currently assigned</small></div>
    <div class="stat-card premium-stat-card"><span>Pending</span><strong>{{ $pendingCount ?? 0 }}</strong><small>Jobs not completed yet</small></div>
    <div class="stat-card premium-stat-card"><span>Under Review</span><strong>{{ $underReviewCount }}</strong><small>Still in checking stage</small></div>
    <div class="stat-card premium-stat-card"><span>Pending Approval</span><strong>{{ $pendingApprovalCount }}</strong><small>Waiting for admin approval</small></div>
    <div class="stat-card premium-stat-card"><span>Work In Progress</span><strong>{{ $workProgressCount }}</strong><small>Execution has started</small></div>
    <div class="stat-card premium-stat-card"><span>Completed</span><strong>{{ $completedByTechnicianCount ?? 0 }}</strong><small>Customer service report submitted</small></div>
    <div class="stat-card premium-stat-card"><span>Complete (%)</span><strong>{{ $completionPercent ?? 0 }}%</strong><small> </small></div>
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

<section class="panel premium-table-panel inbox-panel" style="margin-top:20px;">
    <div class="panel-head premium-table-head inbox-head">
        <div>
            <h3>Quick Access</h3>
            <p>Open the latest jobs directly from here.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table inbox-table">
            <thead>
                <tr>
                    <th>Job ID</th>
                    <th>Date Request</th>
                    <th>Client</th>
                    <th>Type</th>
                    <th>Urgency</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($quickJobs as $job)
                    <tr>
                        <td class="table-primary-cell">{{ $job->request_code }}</td>
                        <td>{{ $job->created_at?->timezone('Asia/Kuala_Lumpur')->format('d M Y h:i A') ?? '-' }}</td>
                        <td>{{ $job->full_name }}</td>
                        <td>{{ $job->requestType->name }}</td>
                        <td><span class="badge {{ $job->urgencyBadgeClass() }}">{{ $job->urgencyLabel() }}</span></td>
                        <td><span class="badge {{ $job->technicianStatusBadgeClass() }}">{{ $job->technicianStatusLabel() }}</span></td>
                        <td>{{ $job->location?->name ?? '-' }}</td>
                        <td><a class="btn small ghost" href="{{ route('technician.job-requests.show', $job) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8">No job request assigned yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap">{{ $quickJobs->links() }}</div>
</section>
@endsection
