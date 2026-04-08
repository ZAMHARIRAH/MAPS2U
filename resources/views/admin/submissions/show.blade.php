@extends('layouts.app', ['title' => 'Incoming Request'])
@section('content')
@php
    $fileUrl = function ($path) {
        return $path ? route('files.show', ['encodedPath' => rtrim(strtr(base64_encode($path), '+/', '-_'), '=')]) : null;
    };
    $approved = $submission->approvedQuotation();
    $paymentHistory = collect($submission->payment_receipt_history ?? [])->values();
    if ($paymentHistory->isEmpty() && !empty($submission->payment_receipt_files)) {
        $paymentHistory = collect([[
            'payment_type' => $submission->payment_type,
            'uploaded_label' => optional($submission->updated_at)->format('d M Y H:i:s'),
            'files' => $submission->payment_receipt_files,
        ]]);
    }
    $paymentHistoryFiles = $paymentHistory
        ->flatMap(function ($history) {
            $history = (array) $history;
            return collect($history['files'] ?? [])->map(function ($file) use ($history) {
                $file = (array) $file;
                $file['history_payment_type'] = $history['payment_type'] ?? null;
                $file['history_uploaded_label'] = $history['uploaded_label'] ?? ($history['uploaded_at'] ?? null);
                return $file;
            });
        })
        ->filter(fn ($file) => !empty($file['path']))
        ->unique(fn ($file) => ($file['path'] ?? '') . '|' . ($file['original_name'] ?? ''))
        ->values();
    $dailyLogs = collect($submission->inspection_sessions ?? [])->map(function ($session) use ($submission) {
        $session = (array) $session;
        $session['resolved_duration'] = $submission->formattedDuration($submission->inspectionSessionDurationSeconds($session));
        return $session;
    })->values();
    $workflowSteps = [
        \App\Models\ClientRequest::STATUS_UNDER_REVIEW,
        \App\Models\ClientRequest::STATUS_PENDING_APPROVAL,
        \App\Models\ClientRequest::STATUS_APPROVED,
        \App\Models\ClientRequest::STATUS_WORK_IN_PROGRESS,
        \App\Models\ClientRequest::STATUS_PENDING_CUSTOMER_REVIEW,
        \App\Models\ClientRequest::STATUS_COMPLETED,
        \App\Models\ClientRequest::STATUS_REJECTED,
    ];
    $currentWorkflowIndex = array_search($submission->status, $workflowSteps, true);
@endphp
<style>.daily-log-compact-grid{display:grid;gap:14px}.compact-log-card{padding:16px;border:1px solid rgba(148,163,184,.28);border-radius:18px;background:#fff;box-shadow:0 10px 26px rgba(15,23,42,.05)}.daily-log-card-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.daily-log-card-head strong{display:block;font-size:15px}.daily-log-card-head span{font-size:12px;color:#64748b}.preview-grid.single-column{grid-template-columns:1fr}</style>

<section class="panel request-hero-card">
    @if(auth()->user()->isViewer())
        <div class="alert-card info" style="margin-bottom:16px;"><strong>Monitoring mode</strong><div> </div></div>
    @endif
    <div class="page-header request-hero-head">
        <div>
            <h1>{{ $submission->request_code }}</h1>
            <p>{{ $submission->requestType->name }}</p>
        </div>
        <div class="actions-inline">
            <span class="badge {{ $submission->urgencyBadgeClass() }}">{{ $submission->urgencyLabel() }}</span>
            <span class="badge {{ $submission->adminWorkflowBadgeClass() }}">{{ $submission->adminWorkflowLabel() }}</span>
<a class="btn success" href="{{ route('admin.incoming-requests.print', ['clientRequest' => $submission, 'print' => 1]) }}" target="_blank">Print</a>@if($submission->hasFinancePending() && (!auth()->user()->isViewer() || $submission->finance_completed_at))<a class="btn accent" href="{{ route('admin.finance.show', $submission) }}">{{ auth()->user()->isViewer() ? 'View Finance' : 'Open Finance' }}</a>@endif<a class="btn ghost" href="{{ route('admin.incoming-requests.index') }}">Back</a>
        </div>
    </div>

    <div class="overview-chip-grid">
        <div class="overview-chip"><span>Client</span><strong>{{ $submission->full_name }}</strong><small>{{ $submission->user->roleLabel() }}</small></div>
        <div class="overview-chip"><span>Phone</span><strong>{{ $submission->phone_number }}</strong><small>Profile contact</small></div>
        <div class="overview-chip"><span>Location</span><strong>{{ $submission->location?->name ?? '-' }}</strong><small>Selected by client</small></div>
        <div class="overview-chip"><span>Department</span><strong>{{ $submission->department?->name ?? '-' }}</strong><small>HQ staff only</small></div>
        <div class="overview-chip"><span>Assigned Technician</span><strong>{{ $submission->assignedTechnician?->name ?? 'Not assigned' }}</strong><small>Can be changed anytime</small></div>
        <div class="overview-chip"><span>Related Job</span><strong>{{ $submission->relatedRequest?->request_code ?? '-' }}</strong><small>Parent reference</small></div>
        <div class="overview-chip"><span>Quotation State</span><strong>{{ $submission->quotation_entries ? count($submission->quotation_entries) . ' submitted' : 'Waiting' }}</strong><small>{{ $approved ? 'Quotation ' . $submission->approved_quotation_index . ' approved' : 'No approval yet' }}</small></div>
        <div class="overview-chip"><span>Customer Review</span><strong>{{ $submission->feedbackAverage() ? $submission->feedbackAverage() . ' / 5' : '-' }}</strong><small>{{ $submission->customer_review_submitted_at ? 'Feedback submitted' : 'Pending review' }}</small></div>
    </div>
</section>

<div class="content-grid tri admin-workspace-grid" style="margin-top:20px; align-items:start;">
    <section class="panel shaded-panel span-2 board-panel">
        <div class="panel-head"><h3>Client Request Form</h3></div>
        <div class="answer-grid">
            @foreach($submission->requestType->questions as $question)
                @php($answer = $submission->answers[$question->id] ?? null)
                <article class="answer-card">
                    <div class="answer-question">{!! nl2br(e($question->question_text)) !!}</div>
                    <div class="answer-response">
                        @if($question->question_type === 'remark')
                            <p>{{ $answer ?: '-' }}</p>
                        @elseif($question->question_type === 'radio')
                            <p>{{ data_get($answer, 'value', '-') }} @if(data_get($answer, 'other')) - {{ data_get($answer, 'other') }} @endif</p>
                        @elseif($question->question_type === 'date_range')
                            <p>{{ $question->start_label ?: 'Start Date' }}: {{ data_get($answer, 'start', '-') }}<br>{{ $question->end_label ?: 'End Date' }}: {{ data_get($answer, 'end', '-') }}</p>
                        @else
                            <ul class="mini-answer-list">
                                @forelse(($answer ?? []) as $selected)
                                    <li>{{ data_get($selected, 'value') }} @if(data_get($selected, 'other')) - {{ data_get($selected, 'other') }} @endif</li>
                                @empty
                                    <li>-</li>
                                @endforelse
                            </ul>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if(!empty($submission->attachments))
            <div class="board-section">
                <div class="panel-head compact"><h4>Client Files</h4></div>
                <ul class="attachment-list compact media-file-list">
                    @foreach($submission->attachments as $file)
                        <li><a href="{{ $fileUrl($file['path']) }}" target="_blank">{{ $file['original_name'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($submission->technician_return_remark)
            <div class="alert-card warning board-section">
                <strong>Latest return remark</strong>
                <div>{{ $submission->technician_return_remark }}</div>
            </div>
        @endif
    </section>

    <aside class="control-stack sticky-side">
        <section class="panel shaded-panel">
            <div class="panel-head"><h3>Admin Review Approval</h3></div>
            <div class="summary-stack">
                <div class="summary-card compact-summary"><strong>Approval Status</strong><span class="badge {{ $submission->adminApprovalBadgeClass() }}">{{ $submission->adminApprovalLabel() }}</span></div>
                @if($submission->admin_approval_remark)
                    <div class="summary-card compact-summary"><strong>Notes</strong><span>{{ $submission->admin_approval_remark }}</span></div>
                @endif
            </div>
            @if($submission->status === \App\Models\ClientRequest::STATUS_REJECTED || $submission->admin_approval_status === 'rejected')
                <div class="alert-card danger" style="margin-top:14px;">
                    <strong>Request Rejected</strong>
                    <div>{{ $submission->admin_approval_remark ?: 'No rejection remark provided.' }}</div>
                </div>
            @elseif($submission->admin_approval_status !== 'approved')
                <div class="action-row" style="margin-top:14px;">
                    <form method="POST" action="{{ route('admin.incoming-requests.decision', $submission) }}">
                        @csrf
                        <input type="hidden" name="decision" value="approved">
                        <button class="btn primary" type="submit">Approved</button>
                    </form>
                    <button class="btn danger" type="button" onclick="toggleBlock('admin-reject-form')">Reject</button>
                </div>
                <form method="POST" action="{{ route('admin.incoming-requests.decision', $submission) }}" id="admin-reject-form" class="hidden-form-block{{ old('decision') === 'rejected' ? ' show-block' : '' }}" style="margin-top:14px;">
                    @csrf
                    <input type="hidden" name="decision" value="rejected">
                    <label>Reject Remark</label>
                    <textarea name="admin_approval_remark" placeholder="Please explain why this request is rejected." required>{{ old('admin_approval_remark') }}</textarea>
                    <div class="action-row" style="margin-top:12px;"><button class="btn danger" type="submit">Submit</button></div>
                </form>
            @endif
        </section>

        @if($submission->status !== \App\Models\ClientRequest::STATUS_REJECTED)
        <section class="panel shaded-panel">
            <div class="panel-head"><h3>Assignment</h3></div>
            <div class="summary-card compact-summary">
                <strong>Current Technician</strong>
                <div class="inline-title-row">
                    <span>{{ $submission->assignedTechnician?->name ?? 'Not assigned yet' }}</span>
                    @if($submission->admin_approval_status === 'approved')
                        <span class="helper-text">Ready to update</span>
                    @endif
                </div>
            </div>
            @if($submission->admin_approval_status !== 'approved')
                <div class="alert-card info">Approve this request first. Then technician assignment will be enabled.</div>
            @else
            <form method="POST" action="{{ route('admin.incoming-requests.assign', $submission) }}" id="assign-tech-form" style="margin-top:14px;">
                @csrf
                <label>Select Technician</label>
                <div class="inline-action-row">
                    <select name="assigned_technician_id" required>
                        <option value="">Select technician</option>
                        @foreach($technicians as $technician)
                            <option value="{{ $technician->id }}" {{ $submission->assigned_technician_id == $technician->id ? 'selected' : '' }}>{{ $technician->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn primary" type="submit">Save</button>
                </div>
            </form>
            @endif
        </section>

        <section class="panel shaded-panel">
            <div class="panel-head"><h3>Technician Review</h3></div>
            @if($submission->technician_review_updated_at)
                <div class="summary-stack">
                    <div class="summary-card compact-summary"><strong>Clarification</strong><span>{{ ucfirst(str_replace('_', ' ', $submission->reviewValue('clarification_level'))) }}</span></div>
                    <div class="summary-card compact-summary"><strong>Repair Channel</strong><span>{{ ucfirst(str_replace('_', ' ', $submission->reviewValue('repair_channel'))) }}</span></div>
                    <div class="summary-card compact-summary"><strong>Repair Scale</strong><span>{{ ucfirst(str_replace('_', ' ', $submission->reviewValue('repair_scale'))) }}</span></div>
                    <div class="summary-card compact-summary"><strong>Processing</strong><span>{{ ucfirst(str_replace('_', ' ', $submission->reviewValue('processing_type'))) }}</span></div>
                    <div class="summary-card compact-summary"><strong>Visit Site</strong><span>{{ ucfirst($submission->reviewValue('visit_site', 'no')) }}</span></div>
                    @if(data_get($submission->technician_review, 'visit_site_remark'))
                        <div class="summary-card compact-summary"><strong>Visit Site Remark</strong><span>{{ data_get($submission->technician_review, 'visit_site_remark') }}</span></div>
                    @endif
                    @if(!empty(data_get($submission->technician_review, 'visit_site_files', [])))
                        <div class="summary-card compact-summary span-2"><strong>Visit Site Files</strong>
                            <div class="preview-grid two-up" style="margin-top:12px;">
                                @foreach(data_get($submission->technician_review, 'visit_site_files', []) as $file)
                                    @include('components.file-preview', ['file' => $file, 'label' => $file['original_name'] ?? 'Visit site file'])
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                <div class="action-row" style="margin-top:14px;"><button class="btn ghost" type="button" onclick="toggleBlock('admin-review-form')">Edit</button></div>
                <form method="POST" action="{{ route('admin.incoming-requests.review', $submission) }}" enctype="multipart/form-data" id="admin-review-form" class="hidden-form-block">
                    @csrf
                    @method('PUT')
                    <label>Clarification</label>
                    <select name="clarification_level" required>
                        <option value="critical" {{ $submission->reviewValue('clarification_level') === 'critical' ? 'selected' : '' }}>Critical</option>
                        <option value="urgent" {{ $submission->reviewValue('clarification_level') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                        <option value="normal" {{ $submission->reviewValue('clarification_level', 'normal') === 'normal' ? 'selected' : '' }}>Normal</option>
                    </select>
                    <label>Repair Channel</label>
                    <select name="repair_channel" required>
                        <option value="in_house_repair" {{ $submission->reviewValue('repair_channel') === 'in_house_repair' ? 'selected' : '' }}>In-house Repair</option>
                        <option value="vendor_required" {{ $submission->reviewValue('repair_channel') === 'vendor_required' ? 'selected' : '' }}>Vendor Required</option>
                    </select>
                    <label>Repair Scale</label>
                    <select name="repair_scale" required>
                        <option value="minor_repair" {{ $submission->reviewValue('repair_scale') === 'minor_repair' ? 'selected' : '' }}>Minor Repair</option>
                        <option value="major_repair" {{ $submission->reviewValue('repair_scale') === 'major_repair' ? 'selected' : '' }}>Major Repair</option>
                    </select>
                    <label>Processing</label>
                    <select name="processing_type" required>
                        <option value="internal" {{ $submission->reviewValue('processing_type') === 'internal' ? 'selected' : '' }}>Internal</option>
                        <option value="outsource" {{ $submission->reviewValue('processing_type') === 'outsource' ? 'selected' : '' }}>Outsource</option>
                    </select>
                    <label>Visit Site</label>
                    <select name="visit_site">
                        <option value="no" {{ $submission->reviewValue('visit_site', 'no') === 'no' ? 'selected' : '' }}>No</option>
                        <option value="yes" {{ $submission->reviewValue('visit_site') === 'yes' ? 'selected' : '' }}>Yes</option>
                    </select>
                    <label>Visit Site Remark</label>
                    <textarea name="visit_site_remark">{{ data_get($submission->technician_review, 'visit_site_remark') }}</textarea>
                    <label>Upload Visit Site Files</label>
                    <input type="file" name="visit_site_files[]" multiple>
                    <button class="btn primary" type="submit" style="margin-top:12px;">Save Changes</button>
                </form>
            @else
                <div class="alert-card info">
                    Technician review has not been submitted yet. Once the technician saves review details, they will appear here for admin editing.
                </div>
            @endif
        </section>
        @else
        <section class="panel shaded-panel">
            <div class="panel-head"><h3>Request Closed</h3></div>
            <div class="alert-card danger"><strong>This request has been rejected.</strong><div>{{ $submission->admin_approval_remark ?: 'No remark provided.' }}</div></div>
        </section>
        @endif

    </aside>
</div>

@if($submission->status !== \App\Models\ClientRequest::STATUS_REJECTED)
<div class="content-grid two-two admin-ops-grid" style="margin-top:20px; align-items:start;">
    <section class="panel shaded-panel">
        <div class="panel-head"><h3>Quotation Approval</h3></div>
        @if($submission->quotation_entries)
            @if($submission->quotation_return_remark)
                <div class="alert-card warning" style="margin-bottom:12px;"><strong>Returned to technician</strong><div>{{ $submission->quotation_return_remark }}</div></div>
            @endif
            <div class="quotation-grid-admin premium-quote-grid">
                @foreach($submission->quotation_entries as $index => $quote)
                    @php($quoteNumber = $index + 1)
                    <div class="quote-card {{ $submission->approved_quotation_index === $quoteNumber ? 'quote-card-approved' : ($submission->approved_quotation_index ? 'quote-card-muted' : '') }}">
                        <div class="quote-head"><strong>Quotation {{ $quoteNumber }}</strong><span>{{ $quote['company_name'] ?? '-' }}</span></div>
                        <div class="helper-text">Amount: RM {{ number_format((float) ($quote['amount'] ?? 0), 2) }}</div>
                        @if(!empty($quote['summary_report']))
                            <p class="helper-text" style="margin-top:8px;">{{ $quote['summary_report'] }}</p>
                        @endif
                        <div class="action-row" style="margin-top:10px; align-items:flex-start;">
                            @if(!empty($quote['file']['path']))
                                <a class="btn small ghost" href="{{ $fileUrl($quote['file']['path']) }}" target="_blank">View File</a>
                            @endif
                        </div>
                        @if(!empty($quote['summary_files']))
                            <div class="board-section" style="margin-top:12px;">
                                <div class="panel-head compact"><h4>Supporting Files</h4></div>
                                <div class="preview-grid two-up" style="margin-top:12px;">
                                    @foreach($quote['summary_files'] as $file)
                                        @include('components.file-preview', ['file' => $file, 'label' => $file['original_name'] ?? 'Supporting file'])
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <div style="margin-top:12px;">
                            <label>Approval Signature</label>
                            @if($submission->approved_quotation_index === $quoteNumber && !empty($quote['admin_signature']))
                                <div class="signature-image-card" style="margin-top:8px;"><img src="{{ $quote['admin_signature'] }}" alt="Approval signature"></div>
                                <div class="action-row" style="margin-top:8px;"><span class="badge success">Approved</span></div>
                            @elseif(!$submission->approved_quotation_index)
                                <form method="POST" action="{{ route('admin.incoming-requests.approve-quotation', $submission) }}">
                                    @csrf
                                    <input type="hidden" name="approved_quotation_index" value="{{ $quoteNumber }}">
                                    <canvas class="signature-pad admin-signature-pad" data-target="approval-signature-{{ $quoteNumber }}"></canvas>
                                    <input type="hidden" name="approval_signature" id="approval-signature-{{ $quoteNumber }}" required>
                                    <div class="action-row" style="margin-top:8px;">
                                        <button class="btn tiny ghost signature-clear" type="button">Clear</button>
                                        <button class="btn small primary" type="submit">Approve & Send</button>
                                    </div>
                                </form>
                            @else
                                <div class="action-row" style="margin-top:8px;"><span class="badge neutral">Not selected</span></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @if(!$submission->approved_quotation_index)
                <form method="POST" action="{{ route('admin.incoming-requests.return-quotation', $submission) }}" style="margin-top:14px;">
                    @csrf
                    <label>Return to Technician</label>
                    <textarea name="quotation_return_remark" placeholder="Explain what needs to be reworked in the quotation submission." required>{{ old('quotation_return_remark', $submission->quotation_return_remark) }}</textarea>
                    <div class="action-row" style="margin-top:12px;"><button class="btn danger" type="submit">Return to Technician</button></div>
                </form>
            @endif
        @else
            <div class="alert-card info">Quotation form has not been submitted by the technician yet.</div>
        @endif
    </section>

        <section class="panel shaded-panel">
        <div class="panel-head"><h3>Execution Snapshot</h3></div>
        <div class="board-section field-pair-grid">
            <div class="summary-card compact-summary"><strong>Payment Type</strong><span>{{ $submission->payment_type ? ucfirst(str_replace('_', ' ', $submission->payment_type)) : '-' }}</span></div>
            <div class="summary-card compact-summary"><strong>Schedule</strong><span>{{ optional($submission->scheduled_date)->format('d M Y') ?: '-' }} {{ $submission->scheduled_time ?: '' }}</span></div>
        </div>

        @if($paymentHistoryFiles->isNotEmpty())
            <div class="board-section">
                <div class="panel-head compact"><h4>Payment Receipt History Log</h4></div>
                <ul class="attachment-list compact media-file-list" style="margin-top:12px;">
                    @foreach($paymentHistoryFiles as $file)
                        <li>
                            <a href="{{ $fileUrl($file['path']) }}" target="_blank">{{ $file['original_name'] ?? 'Receipt' }}</a>
                            <span class="helper-text">- {{ $file['history_payment_type'] ? ucfirst(str_replace('_', ' ', $file['history_payment_type'])) : 'Receipt Upload' }} @if(!empty($file['history_uploaded_label'])) | {{ $file['history_uploaded_label'] }} @endif</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
</div>



<section class="panel shaded-panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Technician Daily Log</h3></div>

    @if($dailyLogs->isNotEmpty())
        <div class="daily-log-compact-grid">
            @foreach($dailyLogs as $session)
                <article class="daily-log-card compact-log-card">
                    <div class="daily-log-card-head">
                        <div>
                            <strong>{{ $session['date_label'] ?? '-' }}</strong>
                            <span>{{ $session['time_start'] ?? '-' }} - {{ $session['time_end'] ?? '-' }}</span>
                        </div>
                        <span class="badge neutral">{{ $session['resolved_duration'] ?? '-' }}</span>
                    </div>
                    <p class="helper-text" style="margin:8px 0 0;">{{ $session['remark'] ?? '-' }}</p>
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

<section class="panel shaded-panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Inspection Snapshot</h3></div>
    @if($submission->inspect_data)
        <div class="field-pair-grid">
            <div class="summary-card compact-summary"><strong>Inspection Remark</strong><span>{{ data_get($submission->inspect_data, 'inspection_remark') ?: '-' }}</span></div>
            <div class="summary-card compact-summary"><strong>Add Related Job</strong><span>{{ data_get($submission->inspect_data, 'add_related_job') ? 'Yes' : 'No' }}</span></div>
            <div class="summary-card compact-summary"><strong>Safety</strong><span>{{ data_get($submission->inspect_data, 'safety_checked') ? 'Checked' : '-' }}</span></div>
            <div class="summary-card compact-summary"><strong>Quality Standard</strong><span>{{ data_get($submission->inspect_data, 'quality_checked') ? 'Checked' : '-' }}</span></div>
            <div class="summary-card compact-summary"><strong>Customer Satisfaction</strong><span>{{ data_get($submission->inspect_data, 'customer_satisfaction_checked') ? 'Checked' : '-' }}</span></div>
            <div class="summary-card compact-summary"><strong>Feedback Average</strong><span>{{ $submission->feedbackAverage() ? $submission->feedbackAverage() . ' / 5' : '-' }}</span></div>
        </div>

        @if(!empty(data_get($submission->inspect_data, 'before_files', [])) || !empty(data_get($submission->inspect_data, 'after_files', [])))
            <div class="content-grid two-two" style="margin-top:16px;">
                <div class="summary-card compact-summary">
                    <strong>Before Files</strong>
                    <ul class="attachment-list compact media-file-list">
                        @forelse(data_get($submission->inspect_data, 'before_files', []) as $file)
                            <li><a href="{{ $fileUrl($file['path']) }}" target="_blank">{{ $file['original_name'] }}</a></li>
                        @empty
                            <li>-</li>
                        @endforelse
                    </ul>
                </div>
                <div class="summary-card compact-summary">
                    <strong>After Files</strong>
                    <ul class="attachment-list compact media-file-list">
                        @forelse(data_get($submission->inspect_data, 'after_files', []) as $file)
                            <li><a href="{{ $fileUrl($file['path']) }}" target="_blank">{{ $file['original_name'] }}</a></li>
                        @empty
                            <li>-</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @endif
    @else
        <div class="alert-card info">Inspection form has not been submitted by the technician yet.</div>
    @endif
</section>

@if($submission->customer_service_report)
<section class="panel shaded-panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Customer Service Report</h3></div>
    <div class="field-pair-grid">
        <div class="summary-card compact-summary"><strong>Technician</strong><span>{{ data_get($submission->customer_service_report, 'technician_name') ?: '-' }}</span></div>
        <div class="summary-card compact-summary"><strong>Date Inspection</strong><span>{{ data_get($submission->customer_service_report, 'date_inspection') ?: '-' }}</span></div>
        <div class="summary-card compact-summary"><strong>Duration Of Work</strong><span>{{ data_get($submission->customer_service_report, 'duration_of_work') ?: '-' }}</span></div>
        <div class="summary-card compact-summary"><strong>Submitted At</strong><span>{{ data_get($submission->customer_service_report, 'submitted_at') ?: '-' }}</span></div>
        <div class="summary-card compact-summary span-2"><strong>Description Of Work</strong><span>{!! nl2br(e(data_get($submission->customer_service_report, 'description_of_work') ?: '-')) !!}</span></div>
        <div class="summary-card compact-summary span-2"><strong>Suggestion / Recommendation</strong><span>{{ data_get($submission->customer_service_report, 'suggestion_recommendation') ?: '-' }}</span></div>
    </div>
    @if(!empty(data_get($submission->customer_service_report, 'attachments', [])))
        <div class="preview-grid two-up" style="margin-top:12px;">
            @foreach(data_get($submission->customer_service_report, 'attachments', []) as $file)
                @include('components.file-preview', ['file' => $file, 'label' => $file['original_name'] ?? 'CSR attachment'])
            @endforeach
        </div>
    @endif
    @if(data_get($submission->customer_service_report, 'person_in_charge_signature') || data_get($submission->customer_service_report, 'verify_by_signature'))
        <div class="signature-display-grid" style="margin-top:14px;">
            @if(data_get($submission->customer_service_report, 'person_in_charge_signature'))<div class="signature-image-card"><strong>Person in Charge</strong><img src="{{ data_get($submission->customer_service_report, 'person_in_charge_signature') }}" alt="PIC signature"></div>@endif
            @if(data_get($submission->customer_service_report, 'verify_by_signature'))<div class="signature-image-card"><strong>Verify By</strong><img src="{{ data_get($submission->customer_service_report, 'verify_by_signature') }}" alt="Verify signature"></div>@endif
        </div>
    @endif
</section>
@endif
@endif

<script>
function toggleBlock(id){const el=document.getElementById(id);if(el){el.classList.toggle('show-block');}}
</script>
<script>
function bindSignaturePad(canvas) {
  if (!canvas || canvas.dataset.bound === '1') return;
  canvas.dataset.bound = '1';
  const target = document.getElementById(canvas.dataset.target);
  const ctx = canvas.getContext('2d');
  let drawing = false;
  const ratio = window.devicePixelRatio || 1;
  const resize = () => { const rect = canvas.getBoundingClientRect(); ctx.setTransform(1,0,0,1,0,0); canvas.width = rect.width * ratio; canvas.height = rect.height * ratio; ctx.scale(ratio, ratio); ctx.lineWidth = 2; ctx.lineCap = 'round'; };
  resize();
  const point = (e) => { const rect = canvas.getBoundingClientRect(); const source = e.touches ? e.touches[0] : e; return { x: source.clientX - rect.left, y: source.clientY - rect.top }; };
  const start = (e) => { drawing = true; const p = point(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); };
  const move = (e) => { if (!drawing) return; const p = point(e); ctx.lineTo(p.x, p.y); ctx.stroke(); target.value = canvas.toDataURL('image/png'); e.preventDefault(); };
  const stop = () => { if (!drawing) return; drawing = false; target.value = canvas.toDataURL('image/png'); };
  canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move); window.addEventListener('mouseup', stop);
  canvas.addEventListener('touchstart', start, { passive: false }); canvas.addEventListener('touchmove', move, { passive: false }); window.addEventListener('touchend', stop);
  canvas.parentElement.querySelector('.signature-clear')?.addEventListener('click', () => { ctx.clearRect(0,0,canvas.width,canvas.height); target.value = ''; });
}
document.querySelectorAll('.admin-signature-pad').forEach(bindSignaturePad);
</script>
@endsection
