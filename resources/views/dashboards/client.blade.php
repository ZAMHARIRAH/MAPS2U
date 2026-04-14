@extends('layouts.app', ['title' => 'Client Dashboard'])
@section('content')
<div class="panel request-hero-panel client-dashboard-hero">
    <div class="request-hero-head">
        <div>
            <span class="hero-kicker">Client Command Center</span>
            <h1>Client Dashboard</h1>
            <p>Welcome back, {{ $user->name }}. Track every request, know which jobs need your attention, and jump straight into resubmission or feedback when needed.</p>
            <div class="hero-badge-row">
                <span class="badge accented">{{ $user->roleLabel() }}</span>
                <span class="badge neutral">Active Request Types: {{ $requestTypeCount }}</span>
                @if($needActionCount > 0)
                    <span class="badge danger">Action Needed: {{ $needActionCount }}</span>
                @endif
                @if($scheduledCount > 0)
                    <span class="badge warning">Scheduled Jobs: {{ $scheduledCount }}</span>
                @endif
            </div>
        </div>
        <div class="hero-actions">
            <a class="btn accent" href="{{ route('client.requests.index', ['tab' => 'new']) }}">Open New Request</a>
            <a class="btn ghost" href="{{ route('client.requests.index', ['tab' => 'related']) }}">Open Related Jobs</a>
        </div>
    </div>

    <div class="premium-meta-grid client-meta-grid">
        <div class="meta-tile">
            <span>Total Requests</span>
            <strong>{{ $myRequestCount }}</strong>
            <small>Every job request you have submitted so far.</small>
        </div>
        <div class="meta-tile">
            <span>Pending</span>
            <strong>{{ $pendingCount }}</strong>
            <small>All jobs that have not reached completed status yet.</small>
        </div>
        <div class="meta-tile">
            <span>Completed</span>
            <strong>{{ $completedCount }}</strong>
            <small>Jobs that already finished including customer review.</small>
        </div>
        <div class="meta-tile">
            <span>Need Your Action</span>
            <strong>{{ $needActionCount }}</strong>
            <small>Returned forms and customer feedback waiting from you.</small>
        </div>
    </div>
</div>

@if($announcements->isNotEmpty())
    <section class="announcement-banner-stack">
        <div class="announcement-banner-head">
            <div>
                <span class="hero-kicker">Latest Update</span>
                <h3>Announcements</h3>
            </div>
            <span class="badge info">{{ $announcements->count() }} Active</span>
        </div>
        <div class="announcement-banner-grid">
            @foreach($announcements as $announcement)
                <article class="announcement-banner-card priority-{{ $announcement->priority }}">
                    <div class="announcement-item-head">
                        <strong>{{ $announcement->title }}</strong>
                        <span class="badge {{ $announcement->priorityBadgeClass() }}">{{ $announcement->priorityLabel() }}</span>
                    </div>
                    <p>{{ $announcement->content }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endif

@if($needActionCount > 0)
    <div class="alert-card warning client-alert-banner">
        <strong>Action required.</strong>
        Some jobs need you to respond. Open the request menu to resubmit returned forms or complete your customer review.
    </div>
@endif


@if($upcomingSchedules->isNotEmpty())
    <div class="alert-card info client-alert-banner">
        <strong>Upcoming visit reminder.</strong>
        <ul class="mini-answer-list" style="margin-top:8px;">
            @foreach($upcomingSchedules as $scheduleRequest)
                <li>
                    {{ $scheduleRequest->request_code }} - {{ $scheduleRequest->scheduled_date->format('d M Y') }} {{ $scheduleRequest->scheduled_time ?: '' }}
                    @if($scheduleRequest->upcomingScheduleDays() === 0)
                        (Today)
                    @elseif($scheduleRequest->upcomingScheduleDays() === 1)
                        (Tomorrow)
                    @else
                        ({{ $scheduleRequest->upcomingScheduleDays() }} days left)
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif

<section class="panel premium-table-panel client-table-panel" style="margin-top:20px;">
    <div class="premium-section-head">
        <div>
            <h3>My Request Details</h3>
            <p>Latest job activity, technician assignment, urgency level, and scheduled date in one place.</p>
        </div>
        <div class="table-head-badges">
            <span class="header-chip">Live Job Tracking</span>
            <span class="header-chip muted">Latest 12 requests</span>
        </div>
    </div>

    <div class="table-scroll-shell">
        <table class="table admin-command-table client-command-table">
            <thead>
                <tr>
                    <th>Job ID</th>
                    <th>Type</th>
                    <th>Urgency</th>
                    <th>Status</th>
                    <th>Technician</th>
                    <th>Schedule</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($latestRequests as $request)
                    <tr class="{{ $request->relatedRequest ? 'premium-child-row' : 'parent-row' }}">
                        <td>
                            <div class="job-card-shell {{ $request->relatedRequest ? 'child-shell' : '' }}">
                                @if($request->relatedRequest)
                                    <span class="job-line-marker">↳</span>
                                @endif
                                <div class="job-main-stack">
                                    <div class="job-top-line">
                                        <strong class="job-code">{{ $request->request_code }}</strong>
                                        @if($request->relatedRequest)
                                            <span class="badge neutral">Related to {{ $request->relatedRequest->request_code }}</span>
                                        @endif
                                    </div>
                                    <div class="job-meta-line">
                                        <span>{{ $request->full_name }}</span>
                                        @if($request->department)
                                            <span class="meta-dot">•</span>
                                            <span>{{ $request->department->name }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $request->requestType->name }}</td>
                        <td><span class="badge {{ $request->urgencyBadgeClass() }}">{{ $request->urgencyLabel() }}</span></td>
                        <td>
                            <div class="status-stack-cell">
                                <span class="badge {{ $request->statusBadgeClass() }}">{{ $request->status }}</span>
                                @if($request->status === \App\Models\ClientRequest::STATUS_RETURNED && $request->technician_return_remark)
                                    <small>{{ $request->technician_return_remark }}</small>
                                @elseif($request->status === \App\Models\ClientRequest::STATUS_CLIENT_RETURNED)
                                    <small>Your updated details have been returned to technician for review.</small>
                                @endif
                            </div>
                        </td>
                        <td>{{ $request->assignedTechnician?->name ?? '-' }}</td>
                        <td>
                            @if($request->scheduled_date)
                                {{ $request->scheduled_date->format('d M Y') }}<br>
                                <small>{{ $request->scheduled_time ?: 'Time to be updated' }}</small>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if($request->status === \App\Models\ClientRequest::STATUS_REJECTED)
                                <span class="helper-text">{{ $request->admin_approval_remark ?: 'Request rejected by admin.' }}</span>
                            @elseif($request->status === \App\Models\ClientRequest::STATUS_RETURNED)
                                <div class="client-action-stack"><a class="btn small ghost" href="{{ route('client.requests.show', $request) }}">View</a><a class="btn small accent" href="{{ route('client.requests.index', ['tab' => 'new', 'edit' => $request->id, 'form' => 1]) }}">Resubmit</a></div>
                            @elseif($request->status === \App\Models\ClientRequest::STATUS_PENDING_CUSTOMER_REVIEW)
                                <div class="client-action-stack">
                                    <a class="btn small ghost" href="{{ route('client.requests.show', $request) }}">View</a>
                                    <a class="btn small primary" href="{{ route('client.requests.show', $request) }}#feedback-form">Open Review</a>
                                    <button class="btn small soft-success" type="button" data-agree-all-open="agree-all-modal-{{ $request->id }}">Agree All</button>

                                    <div id="agree-all-modal-{{ $request->id }}" data-agree-all-modal style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.65);z-index:80;padding:20px;overflow:auto;">
                                        <div class="panel shaded-panel" style="max-width:720px;margin:40px auto;background:#fff;">
                                            <div class="panel-head"><h3>Agree All Terms & Conditions</h3></div>
                                            <p class="helper-text" style="margin-bottom:12px;">Every feedback question will follow the same score that you select below. Please confirm the scale carefully before submitting.</p>
                                            <form method="POST" action="{{ route('client.requests.feedback', $request) }}">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="agree_all" value="1">
                                                <div class="feedback-question-card" style="margin-bottom:12px;">
                                                    <p>Select one scale for all feedback questions:</p>
                                                    <div class="rating-grid premium-rating-grid">
                                                        @foreach([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'] as $score => $label)
                                                            <label class="rate-pill premium-rate-pill"><input type="radio" name="agree_all_scale" value="{{ $score }}" required><span>{{ $label }}</span></label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                <div class="feedback-comment-card"><label>Additional Comments / Suggestions</label><textarea name="additional_comments"></textarea></div>
                                                <label class="remember-line" style="margin:10px 0 16px;display:flex;align-items:flex-start;gap:8px;"><input type="checkbox" name="agree_all_confirmed" value="1" required style="margin-top:4px;"><span>I agree that all feedback answers will be submitted based on the selected scale above.</span></label>
                                                <div class="action-row" style="justify-content:flex-end;gap:12px;">
                                                    <button class="btn ghost" type="button" data-agree-all-close="agree-all-modal-{{ $request->id }}">Cancel</button>
                                                    <button class="btn accent" type="submit">Submit</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @elseif(data_get($request->inspect_data, 'add_related_job') && !$request->childRequests()->where('user_id', $user->id)->exists())
                                <div class="client-action-stack"><a class="btn small ghost" href="{{ route('client.requests.show', $request) }}">View</a><a class="btn small ghost" href="{{ route('client.requests.index', ['tab' => 'related', 'related_source' => $request->id, 'form' => 1]) }}">Fill Related Job</a></div>
                            @elseif($request->related_request_id)
                                <span class="helper-text">Related request submitted</span>
                            @else
                                <a class="btn small ghost" href="{{ route('client.requests.show', $request) }}">View</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state-card">
                                <strong>No request submitted yet.</strong>
                                <p class="helper-text">Start by opening the request menu and create your first job request.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<script>
(() => {
    const openModal = (id) => {
        const modal = document.getElementById(id);
        if (modal) modal.style.display = 'block';
    };

    const closeModal = (id) => {
        const modal = document.getElementById(id);
        if (modal) modal.style.display = 'none';
    };

    document.querySelectorAll('[data-agree-all-open]').forEach((button) => {
        button.addEventListener('click', () => openModal(button.dataset.agreeAllOpen));
    });

    document.querySelectorAll('[data-agree-all-close]').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.dataset.agreeAllClose));
    });

    document.querySelectorAll('[data-agree-all-modal]').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
})();
</script>
@endsection
