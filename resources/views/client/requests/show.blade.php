@extends('layouts.app', ['title' => 'Request Detail'])
@section('content')
@php
    $monitorOnly = $monitorOnly ?? false;
    $printMode = request()->boolean('print');
    $fileUrl = function ($path) {
        return $path ? route('files.show', ['encodedPath' => rtrim(strtr(base64_encode($path), '+/', '-_'), '=')]) : null;
    };
    $workflowSteps = [
        \App\Models\ClientRequest::STATUS_UNDER_REVIEW,
        \App\Models\ClientRequest::STATUS_PENDING_APPROVAL,
        \App\Models\ClientRequest::STATUS_APPROVED,
        \App\Models\ClientRequest::STATUS_WORK_IN_PROGRESS,
        \App\Models\ClientRequest::STATUS_PENDING_CUSTOMER_REVIEW,
        \App\Models\ClientRequest::STATUS_COMPLETED,
        \App\Models\ClientRequest::STATUS_REJECTED,
    ];
    $currentWorkflowIndex = array_search($requestItem->status, $workflowSteps, true);
    $report = $requestItem->customer_service_report ?? [];
    $dailyLogs = collect($requestItem->inspection_sessions ?? [])->map(function ($session) use ($requestItem) {
        $session = (array) $session;
        $session['resolved_duration'] = $requestItem->formattedDuration($requestItem->inspectionSessionDurationSeconds($session));
        return $session;
    })->values();
@endphp
<style>@media print{.no-print,.footer-bar,.topbar,.sidebar{display:none !important}.content-shell{padding:0 !important}.panel{box-shadow:none !important;border:none !important}body{background:#fff !important}}.daily-log-compact-grid{display:grid;gap:14px}.compact-log-card{padding:16px;border:1px solid rgba(148,163,184,.24);border-radius:18px;background:#fff;box-shadow:0 10px 26px rgba(15,23,42,.05)}.compact-log-card .meta-row{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.compact-log-card .meta-row strong{display:block;font-size:15px}.compact-log-card .meta-row span{font-size:12px;color:#64748b}</style>

<div class="page-header no-print" style="margin-bottom:16px;"><div><h1 style="margin:0;">Request Detail</h1><p class="helper-text" style="margin:6px 0 0;"> </p></div><div class="action-row"><a class="btn ghost" href="{{ ($monitorOnly ?? false) ? route('client.dashboard-list.index') : route('client.requests.index') }}">Back</a>@if($monitorOnly ?? false)<a class="btn ghost" href="{{ route('client.dashboard-list.show', ['clientRequest' => $requestItem, 'print' => 1]) }}" target="_blank">Print Full Report</a>@endif</div></div>
<section class="panel request-hero-card">
    <div class="page-header request-hero-head">
        <div>
            <h1>{{ $requestItem->request_code }}</h1>
            <p>{{ $requestItem->requestType->name }}</p>
        </div>
        <div class="actions-inline">
            <span class="badge {{ $requestItem->urgencyBadgeClass() }}">{{ $requestItem->urgencyLabel() }}</span>
            <span class="badge {{ $requestItem->statusBadgeClass() }}">{{ $requestItem->status }}</span>
            <a class="btn ghost" href="{{ ($monitorOnly ?? false) ? route('client.dashboard-list.index') : route('client.dashboard') }}">Back</a>
        </div>
    </div>
    <div class="overview-chip-grid">
        <div class="overview-chip"><span>Technician</span><strong>{{ $requestItem->assignedTechnician?->name ?? 'Not assigned' }}</strong><small>Assigned person in charge</small></div>
        <div class="overview-chip"><span>Location</span><strong>{{ $requestItem->location?->name ?? '-' }}</strong><small>Request location</small></div>
        <div class="overview-chip"><span>Department</span><strong>{{ $requestItem->department?->name ?? '-' }}</strong><small>Selected department</small></div>
        <div class="overview-chip"><span>Schedule</span><strong>{{ optional($requestItem->scheduled_date)->format('d M Y') ?: '-' }}</strong><small>{{ $requestItem->scheduled_time ?: 'Time to be updated' }}</small></div>
    </div>
</section>

<section class="panel shaded-panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Work Order - Submitted Request Details</h3></div>
    <div class="summary-stack">
        <div class="summary-card compact-summary"><strong>Requester Name</strong><span>{{ $requestItem->full_name ?: ($requestItem->user?->name ?? '-') }}</span></div>
        <div class="summary-card compact-summary"><strong>Phone Number</strong><span>{{ $requestItem->phone_number ?: ($requestItem->user?->phone_number ?? '-') }}</span></div>
        <div class="summary-card compact-summary"><strong>Request Type</strong><span>{{ $requestItem->requestType?->name ?? '-' }}</span></div>
        <div class="summary-card compact-summary"><strong>Urgency</strong><span>{{ $requestItem->urgencyLabel() }}</span></div>
        <div class="summary-card compact-summary"><strong>Location</strong><span>{{ $requestItem->location?->name ?? '-' }}</span></div>
        <div class="summary-card compact-summary"><strong>Department</strong><span>{{ $requestItem->department?->name ?? '-' }}</span></div>
    </div>

    @if($requestItem->requestType && $requestItem->requestType->questions->isNotEmpty())
        <div class="answer-grid" style="margin-top:16px;">
            @foreach($requestItem->requestType->questions as $question)
                <article class="answer-card">
                    <div class="answer-question">{!! nl2br(e($question->question_text)) !!}</div>
                    <div class="answer-response"><p>{!! nl2br(e($requestItem->displayAnswerForQuestion($question))) !!}</p></div>
                </article>
            @endforeach
        </div>
    @else
        <div class="alert-card info" style="margin-top:16px;">Request form question setup is not available for this migrated request. Basic job details are still shown above.</div>
    @endif

    @if(!empty($requestItem->attachments))
        <div class="board-section" style="margin-top:16px;">
            <div class="panel-head compact"><h4>Submitted Attachments</h4></div>
            <div class="preview-grid two-up" style="margin-top:12px;">
                @foreach($requestItem->attachments as $file)
                    @include('components.file-preview', ['file' => $file, 'label' => $file['original_name'] ?? 'Submitted file'])
                @endforeach
            </div>
        </div>
    @endif
</section>

<div class="content-grid two-two" style="margin-top:20px;align-items:start;">
    <section class="panel shaded-panel">
        <div class="panel-head"><h3>Workflow Tracker</h3></div>
        <div class="workflow-timeline">
            @foreach($workflowSteps as $step)
                <div class="timeline-item {{ $requestItem->status === $step ? 'active' : '' }} {{ $currentWorkflowIndex !== false && array_search($step, $workflowSteps, true) < $currentWorkflowIndex ? 'done' : '' }}">
                    <span class="timeline-dot"></span>
                    <div><strong>{{ $step }}</strong><small>{{ $requestItem->status === $step ? 'Current stage' : 'Workflow step' }}</small></div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="panel shaded-panel">
        <div class="panel-head"><h3>Technician Review</h3></div>
        <div class="summary-stack">
            <div class="summary-card compact-summary"><strong>Clarification</strong><span>{{ ucfirst(str_replace('_', ' ', $requestItem->reviewValue('clarification_level'))) }}</span></div>
            <div class="summary-card compact-summary"><strong>Repair Channel</strong><span>{{ ucfirst(str_replace('_', ' ', $requestItem->reviewValue('repair_channel'))) }}</span></div>
            <div class="summary-card compact-summary"><strong>Repair Scale</strong><span>{{ ucfirst(str_replace('_', ' ', $requestItem->reviewValue('repair_scale'))) }}</span></div>
            <div class="summary-card compact-summary"><strong>Processing</strong><span>{{ ucfirst(str_replace('_', ' ', $requestItem->reviewValue('processing_type'))) }}</span></div>
            <div class="summary-card compact-summary"><strong>Visit Site</strong><span>{{ ucfirst($requestItem->reviewValue('visit_site', 'no')) }}</span></div>
            <div class="summary-card compact-summary span-2"><strong>Visit Site Remark</strong><span>{{ data_get($requestItem->technician_review, 'visit_site_remark') ?: '-' }}</span></div>
            @if(!empty(data_get($requestItem->technician_review, 'visit_site_files', [])))
                <div class="summary-card compact-summary span-2"><strong>Visit Site Files</strong>
                    <div class="preview-grid two-up" style="margin-top:12px;">
                        @foreach(data_get($requestItem->technician_review, 'visit_site_files', []) as $file)
                            @include('components.file-preview', ['file' => $file, 'label' => $file['original_name'] ?? 'Visit site file'])
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
</div>

<section class="panel shaded-panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Inspection Form Snapshot</h3></div>
    @if($requestItem->inspect_data)
        <div class="field-pair-grid">
            <div class="summary-card compact-summary"><strong>Inspection Remark</strong><span>{{ data_get($requestItem->inspect_data, 'inspection_remark') ?: '-' }}</span></div>
            <div class="summary-card compact-summary"><strong>Safety</strong><span>{{ data_get($requestItem->inspect_data, 'safety_checked') ? 'Checked' : '-' }}</span></div>
            <div class="summary-card compact-summary"><strong>Quality</strong><span>{{ data_get($requestItem->inspect_data, 'quality_checked') ? 'Checked' : '-' }}</span></div>
            <div class="summary-card compact-summary"><strong>Customer Satisfaction</strong><span>{{ data_get($requestItem->inspect_data, 'customer_satisfaction_checked') ? 'Checked' : '-' }}</span></div>
            <div class="summary-card compact-summary"><strong>Add Related Job</strong><span>{{ data_get($requestItem->inspect_data, 'add_related_job') ? 'Yes' : 'No' }}</span></div>
        </div>

        <div class="content-grid two-two" style="margin-top:16px;">
            <div class="summary-card compact-summary"><strong>Before Files</strong><ul class="attachment-list compact media-file-list">@forelse(data_get($requestItem->inspect_data, 'before_files', []) as $file)<li><a href="{{ $fileUrl($file['path']) }}" target="_blank">{{ $file['original_name'] }}</a></li>@empty<li>-</li>@endforelse</ul></div>
            <div class="summary-card compact-summary"><strong>After Files</strong><ul class="attachment-list compact media-file-list">@forelse(data_get($requestItem->inspect_data, 'after_files', []) as $file)<li><a href="{{ $fileUrl($file['path']) }}" target="_blank">{{ $file['original_name'] }}</a></li>@empty<li>-</li>@endforelse</ul></div>
        </div>
    @else
        <div class="alert-card info">Inspection form has not been submitted yet.</div>
    @endif
</section>

<section class="panel shaded-panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Technician Daily Log</h3></div>
    @if($dailyLogs->isNotEmpty())
        <div class="daily-log-compact-grid">
            @foreach($dailyLogs as $session)
                <article class="compact-log-card">
                    <div class="meta-row">
                        <div>
                            <strong>{{ $session['date_label'] ?? '-' }}</strong>
                            <span>{{ $session['time_start'] ?? '-' }} - {{ $session['time_end'] ?? '-' }}</span>
                        </div>
                        <span class="badge neutral">{{ $session['resolved_duration'] ?? '-' }}</span>
                    </div>
                    <p class="helper-text" style="margin:8px 0 0;">{{ $session['remark'] ?? '-' }}</p>
                    @if(!empty($session['verify_by_signed_at_label']))<div class="helper-text" style="margin-top:8px;"><strong>Verify signed:</strong> {{ $session['verify_by_signed_at_label'] }}</div>@endif
                    @if(!empty($session['verify_by']))<div class="signature-image-card" style="margin-top:10px;max-width:220px;"><img src="{{ $session['verify_by'] }}" alt="Daily log verify signature"></div>@endif
                    @php($sessionFiles = collect($session['attachments'] ?? [])->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values())
                    @if($sessionFiles->isNotEmpty())
                        <div class="preview-grid two-up" style="margin-top:12px;">
                            @foreach($sessionFiles as $file)
                                @include('components.file-preview', ['file' => $file, 'label' => $file['original_name'] ?? 'Daily log attachment'])
                            @endforeach
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @else
        <div class="alert-card info">No technician daily log has been recorded yet.</div>
    @endif
</section>

@if($report)
<section class="panel shaded-panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Customer Service Report</h3></div>
    <div class="summary-stack">
        <div class="summary-card compact-summary"><strong>Technician</strong><span>{{ data_get($report, 'technician_name') ?: '-' }}</span></div>
        <div class="summary-card compact-summary"><strong>Date</strong><span>{{ data_get($report, 'date_inspection') ?: '-' }}</span></div>
        <div class="summary-card compact-summary"><strong>Duration</strong><span>{{ data_get($report, 'duration_of_work') ?: '-' }}</span></div>
        <div class="summary-card compact-summary"><strong>Submitted At</strong><span>{{ data_get($report, 'submitted_at') ?: '-' }}</span></div>
        <div class="summary-card compact-summary span-2"><strong>Description of Work</strong><span>{!! nl2br(e(data_get($report, 'description_of_work'))) !!}</span></div>
        @if(!empty(data_get($report, 'description_entries', [])))
            <div class="summary-card compact-summary span-2"><strong>Description Remark Evidence</strong><div style="display:grid;gap:12px;margin-top:10px;">@foreach(data_get($report, 'description_entries', []) as $entry)<div style="border:1px solid #dbe7f5;border-radius:12px;padding:10px 12px;background:#fff;display:grid;gap:8px;"><div><strong>{{ $entry['date_label'] ?? '-' }}</strong> • {{ $entry['time_range'] ?? '-' }} • {{ $entry['duration_label'] ?? '-' }}</div><div>{{ $entry['remark'] ?? '-' }}</div><div class="helper-text">Verify signed: {{ $entry['verify_by_signed_at_label'] ?? '-' }}</div>@if(!empty($entry['verify_by_signature']))<div class="signature-image-card" style="max-width:220px;"><img src="{{ $entry['verify_by_signature'] }}" alt="CSR verify signature"></div>@endif</div>@endforeach</div></div>
        @endif
        <div class="summary-card compact-summary span-2"><strong>Suggestion / Recommendation</strong><span>{{ data_get($report, 'suggestion_recommendation') ?: '-' }}</span></div>
    </div>
    @if(!empty(data_get($report, 'attachments', [])))
        <div class="preview-grid two-up" style="margin-top:12px;">
            @foreach(data_get($report, 'attachments', []) as $file)
                @include('components.file-preview', ['file' => $file, 'label' => $file['original_name'] ?? 'CSR attachment'])
            @endforeach
        </div>
    @endif
    @if(data_get($report, 'person_in_charge_signature') || data_get($report, 'verify_by_signature'))
        <div class="signature-display-grid" style="margin-top:14px;">
            @if(data_get($report, 'person_in_charge_signature'))<div class="signature-image-card"><strong>Person in Charge</strong><img src="{{ data_get($report, 'person_in_charge_signature') }}" alt="PIC signature"></div>@endif
            @if(data_get($report, 'verify_by_signature'))<div class="signature-image-card"><strong>Verify By</strong><img src="{{ data_get($report, 'verify_by_signature') }}" alt="Verify signature"></div>@endif
        </div>
    @endif
</section>
@endif

@if(!($monitorOnly ?? false) && $requestItem->status === \App\Models\ClientRequest::STATUS_PENDING_CUSTOMER_REVIEW)
<section class="panel shaded-panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Feedback Form</h3></div>
    <form method="POST" action="{{ route('client.requests.feedback', $requestItem) }}" class="client-feedback-form" id="feedback-form">
        @csrf
        @method('PUT')
        <div class="feedback-section-grid">
            @foreach($feedbackSections as $sectionKey => $section)
                <details class="feedback-section-card" {{ $loop->first ? 'open' : '' }}>
                    <summary><div><strong>{{ $section['title'] }}</strong><small>{{ count($section['questions']) }} question(s)</small></div><span class="feedback-summary-toggle">Open</span></summary>
                    <div class="feedback-question-stack">
                        @foreach($section['questions'] as $questionKey => $questionText)
                            <div class="feedback-question-card">
                                <p>{{ $questionText }}</p>
                                <div class="rating-grid premium-rating-grid">
                                    @foreach([1 => 'Strongly Disagree', 2 => 'Disagree', 3 => 'Neutral', 4 => 'Agree', 5 => 'Strongly Agree'] as $score => $label)
                                        <label class="rate-pill premium-rate-pill"><input type="radio" name="ratings[{{ $sectionKey }}][{{ $questionKey }}]" value="{{ $score }}" required><span>{{ $label }}</span></label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
        <div class="feedback-comment-card"><label>Additional Comments / Suggestions</label><textarea name="additional_comments"></textarea></div>
        <div class="feedback-comment-card" style="border-style:dashed;">
            <label>Agree All Shortcut</label>
            <p class="helper-text" style="margin-bottom:10px;">Use this if you want all feedback answers to follow one selected scale only.</p>
            <button class="btn ghost" type="button" id="open-agree-all-modal">Agree All</button>
        </div>
        <div class="action-row feedback-submit-row" style="justify-content:flex-end;gap:12px;flex-wrap:wrap;"><button class="btn accent" type="submit">Submit Feedback</button></div>
    </form>

    <div id="agree-all-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.65);z-index:80;padding:20px;overflow:auto;">
        <div class="panel shaded-panel" style="max-width:720px;margin:40px auto;background:#fff;">
            <div class="panel-head"><h3>Agree All Terms & Conditions</h3></div>
            <p class="helper-text" style="margin-bottom:12px;">Every feedback question will follow the same score that you select below. Please confirm the scale carefully before submitting.</p>
            <form method="POST" action="{{ route('client.requests.feedback', $requestItem) }}">
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
                    <button class="btn ghost" type="button" id="close-agree-all-modal">Cancel</button>
                    <button class="btn accent" type="submit">Submit</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        (() => {
            const openBtn = document.getElementById('open-agree-all-modal');
            const closeBtn = document.getElementById('close-agree-all-modal');
            const modal = document.getElementById('agree-all-modal');
            if (!openBtn || !closeBtn || !modal) return;
            openBtn.addEventListener('click', () => modal.style.display = 'block');
            closeBtn.addEventListener('click', () => modal.style.display = 'none');
            modal.addEventListener('click', (event) => {
                if (event.target === modal) modal.style.display = 'none';
            });
        })();
    </script>
</section>
@endif
@endsection

@if($printMode)
<script>window.addEventListener('load',()=>window.print());</script>
@endif
