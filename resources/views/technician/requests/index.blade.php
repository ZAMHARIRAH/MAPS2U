@extends('layouts.app', ['title' => 'Job Request'])

@section('content')
@php
    $pendingJobs = $jobs->filter(fn ($job) => !$job->technician_completed_at)->count();
    $relatedJobs = $jobs->filter(fn ($job) => !empty($job->related_request_id))->count();
@endphp

<div class="page-header premium-page-header">
    <div>
        <h1>Job Request</h1>
        <p>All assigned jobs from admin are listed here with the latest status and related job reference.</p>
    </div>
</div>

<div class="stats-grid admin-inbox-cards">
    <div class="stat-card premium-stat-card"><span>Total Assigned</span><strong>{{ $jobs->count() }}</strong><small>All jobs assigned to this technician</small></div>
    <div class="stat-card premium-stat-card"><span>Pending / Active</span><strong>{{ $pendingJobs }}</strong><small>Jobs not yet completed</small></div>
    <div class="stat-card premium-stat-card"><span>Completed</span><strong>{{ $jobs->filter(fn ($job) => (bool) $job->technician_completed_at)->count() }}</strong><small>Customer service report submitted</small></div>
    <div class="stat-card premium-stat-card"><span>Related Jobs</span><strong>{{ $relatedJobs }}</strong><small>Child jobs linked to another request</small></div>
</div>

<section class="panel premium-table-panel inbox-panel" style="margin-top:20px;">
    <div class="panel-head premium-table-head inbox-head">
        <div>
            <h3>Assigned Job Requests</h3>
            <p>Use this list to jump into the technician workspace for each job.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table inbox-table hierarchy-table">
            <thead>
                <tr>
                    <th>Job ID</th>
                    <th>Client</th>
                    <th>Type</th>
                    <th>Urgency</th>
                    <th>Status</th>
                    <th>Related Job</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($jobs as $job)
                    <tr class="{{ $job->related_request_id ? 'child-row premium-child-row' : '' }}">
                        <td class="table-primary-cell">
                            @if($job->related_request_id)
                                <span class="child-indent">↳ {{ $job->request_code }}</span>
                            @else
                                {{ $job->request_code }}
                            @endif
                        </td>
                        <td>{{ $job->full_name }}</td>
                        <td>{{ $job->requestType->name }}</td>
                        <td><span class="badge {{ $job->urgencyBadgeClass() }}">{{ $job->urgencyLabel() }}</span></td>
                        <td><span class="badge {{ $job->technicianStatusBadgeClass() }}">{{ $job->technicianStatusLabel() }}</span></td>
                        <td>{{ $job->relatedRequest?->request_code ?? '-' }}</td>
                        <td><a class="btn small ghost" href="{{ route('technician.job-requests.show', $job) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7">No assigned job request yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
