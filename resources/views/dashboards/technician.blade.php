@extends('layouts.app', ['title' => 'Technician Dashboard'])

@section('content')
<div class="page-header premium-page-header">
    <div>
        <h1>Technician Dashboard</h1>
        <p>Welcome back, {{ $user->name }}. </p>
    </div>
    <a class="btn primary" href="{{ route('technician.job-requests.index') }}">Open All Job Requests</a>
</div>

<div class="stats-grid admin-inbox-cards">
    <div class="stat-card premium-stat-card"><span>Total Assigned Jobs</span><strong>{{ $jobCount }}</strong><small>All jobs currently assigned</small></div>
    <div class="stat-card premium-stat-card"><span>Under Review</span><strong>{{ $underReviewCount }}</strong><small>Still in checking stage</small></div>
    <div class="stat-card premium-stat-card"><span>Pending Approval</span><strong>{{ $pendingApprovalCount }}</strong><small>Waiting for admin approval</small></div>
    <div class="stat-card premium-stat-card"><span>Work In Progress</span><strong>{{ $workProgressCount }}</strong><small>Execution has started</small></div>
    <div class="stat-card premium-stat-card"><span>Completed</span><strong>{{ $completedByTechnicianCount ?? $jobs->filter(fn ($job) => (bool) $job->technician_completed_at)->count() }}</strong><small>Customer service report submitted</small></div>
</div>

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
                        <td>{{ $job->full_name }}</td>
                        <td>{{ $job->requestType->name }}</td>
                        <td><span class="badge {{ $job->urgencyBadgeClass() }}">{{ $job->urgencyLabel() }}</span></td>
                        <td><span class="badge {{ $job->technicianStatusBadgeClass() }}">{{ $job->technicianStatusLabel() }}</span></td>
                        <td>{{ $job->location?->name ?? '-' }}</td>
                        <td><a class="btn small ghost" href="{{ route('technician.job-requests.show', $job) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7">No job request assigned yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
