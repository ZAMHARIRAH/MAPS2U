@extends('layouts.app', ['title' => 'Incoming Request'])
@section('content')
@php
    $fileUrl = function ($path) {
        return $path ? route('files.show', ['encodedPath' => rtrim(strtr(base64_encode($path), '+/', '-_'), '=')]) : null;
    };
    $approved = $submission->approvedQuotation();
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

<section class="panel request-hero-card">
    <div class="page-header request-hero-head">
        <div>
            <h1>{{ $submission->request_code }}</h1>
            <p>{{ $submission->requestType->name }}</p>
        </div>
        <div class="actions-inline">
            <span class="badge {{ $submission->urgencyBadgeClass() }}">{{ $submission->urgencyLabel() }}</span>
            <span class="badge {{ $submission->adminWorkflowBadgeClass() }}">{{ $submission->adminWorkflowLabel() }}</span>
            @if(!empty($submission->invoice_files))<a class="btn accent" href="{{ route('admin.finance.show', $submission) }}">Open Finance</a>@endif<a class="btn ghost" href="{{ route('admin.incoming-requests.index') }}">Back</a>
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
                    <div class="summary-card compact-summary"><strong>Remark</strong><span>{{ $submission->admin_approval_remark }}</span></div>
                @endif
            </div>
            @if($submission->status === \App\Models\ClientRequest::STATUS_REJECTED || $submission->admin_approval_status === 'rejected')
                <div class="alert-card danger" style="margin-top:14px;">
                    <strong>Request Rejected</strong>
                    <div>{{ $submission->admin_approval_remark ?: 'No rejection remark provided.' }}</div>
                </div>
            @else
                <form method="POST" action="{{ route('admin.incoming-requests.decision', $submission) }}" style="margin-top:14px;">
                    @csrf
                    <label>Admin Remark</label>
                    <textarea name="admin_approval_remark" placeholder="Required when rejecting the request.">{{ old('admin_approval_remark', $submission->admin_approval_remark) }}</textarea>
                    <div class="action-row" style="margin-top:12px;">
                        <button class="btn primary" type="submit" name="decision" value="approved">Approve</button>
                        <button class="btn danger" type="submit" name="decision" value="rejected">Reject</button>
                    </div>
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
                        <button class="btn tiny ghost" type="button" onclick="toggleBlock('assign-tech-form')">Edit</button>
                    @endif
                </div>
            </div>
            @if($submission->admin_approval_status !== 'approved')
                <div class="alert-card info">Approve this request first. Then technician assignment will be enabled.</div>
            @else
            <form method="POST" action="{{ route('admin.incoming-requests.assign', $submission) }}" id="assign-tech-form" class="hidden-form-block">
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
                        <div class="summary-card compact-summary"><strong>Visit Site Files</strong>
                            <ul class="attachment-list compact media-file-list">
                                @foreach(data_get($submission->technician_review, 'visit_site_files', []) as $file)
                                    <li><a href="{{ $fileUrl($file['path']) }}" target="_blank">{{ $file['original_name'] }}</a></li>
                                @endforeach
                            </ul>
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

        <section class="panel shaded-panel">
            <div class="panel-head"><h3>Workflow Tracker</h3></div>
            <div class="workflow-timeline">
                @foreach($workflowSteps as $step)
                    <div class="timeline-item {{ $submission->status === $step ? 'active' : '' }} {{ $currentWorkflowIndex !== false && array_search($step, $workflowSteps, true) < $currentWorkflowIndex ? 'done' : '' }}">
                        <span class="timeline-dot"></span>
                        <div>
                            <strong>{{ $step }}</strong>
                            <small>{{ $submission->status === $step ? 'Current stage' : 'Workflow step' }}</small>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
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
                        <div class="action-row" style="margin-top:10px;">
                            @if(!empty($quote['file']['path']))
                                <a class="btn small ghost" href="{{ $fileUrl($quote['file']['path']) }}" target="_blank">View File</a>
                            @endif
                            <form method="POST" action="{{ route('admin.incoming-requests.approve-quotation', $submission) }}">
                                @csrf
                                <input type="hidden" name="approved_quotation_index" value="{{ $quoteNumber }}">
                                <button class="btn small {{ $submission->approved_quotation_index === $quoteNumber ? 'accent' : 'primary' }}" type="submit">{{ $submission->approved_quotation_index === $quoteNumber ? 'Approved' : 'Approve' }}</button>
                            </form>
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
        @if($approved)
            <div class="summary-stack">
                <div class="summary-card compact-summary"><strong>Approved Quotation</strong><span>Quotation {{ $submission->approved_quotation_index }}</span></div>
                <div class="summary-card compact-summary"><strong>Company</strong><span>{{ $approved['company_name'] ?? '-' }}</span></div>
                <div class="summary-card compact-summary"><strong>Amount</strong><span>RM {{ number_format((float) ($approved['amount'] ?? 0), 2) }}</span></div>
            </div>
            @if(!empty($approved['file']['path']))
                @php($mime = $approved['file']['mime_type'] ?? '')
                <div class="board-section">
                    @if(str_contains($mime, 'image'))
                        <img src="{{ $fileUrl($approved['file']['path']) }}" alt="Approved quotation" class="preview-image">
                    @elseif(str_contains($mime, 'pdf'))
                        <object data="{{ $fileUrl($approved['file']['path']) }}" type="application/pdf" class="file-embed"></object>
                        <a href="{{ $fileUrl($approved['file']['path']) }}" target="_blank">Open PDF</a>
                    @else
                        <a href="{{ $fileUrl($approved['file']['path']) }}" target="_blank">{{ $approved['file']['original_name'] }}</a>
                    @endif
                </div>
            @endif
        @else
            <div class="alert-card info">Approved quotation will appear here after admin selects one quotation.</div>
        @endif

        <div class="board-section field-pair-grid">
            <div class="summary-card compact-summary"><strong>Payment Type</strong><span>{{ $submission->payment_type ? ucfirst(str_replace('_', ' ', $submission->payment_type)) : '-' }}</span></div>
            <div class="summary-card compact-summary"><strong>Schedule</strong><span>{{ optional($submission->scheduled_date)->format('d M Y') ?: '-' }} {{ $submission->scheduled_time ?: '' }}</span></div>
        </div>

        @if(!empty($submission->payment_receipt_files))
            <div class="board-section">
                <div class="panel-head compact"><h4>Payment Receipts</h4></div>
                <ul class="attachment-list compact media-file-list">
                    @foreach($submission->payment_receipt_files as $file)
                        <li><a href="{{ $fileUrl($file['path']) }}" target="_blank">{{ $file['original_name'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(!empty($submission->invoice_files))
            <div class="board-section">
                <div class="panel-head compact"><h4>Invoices Uploaded</h4><a class="btn tiny accent" href="{{ route('admin.finance.show', $submission) }}">Finance Form</a></div>
                <div class="preview-grid two-up">
                    @foreach($submission->invoice_files as $file)
                        @include('components.file-preview', ['file' => $file, 'label' => $file['original_name']])
                    @endforeach
                </div>
            </div>
        @endif

        @if($submission->customer_service_report)
            <div class="board-section">
                <div class="panel-head compact"><h4>Customer Service Report</h4></div>
                <div class="field-pair-grid">
                    <div class="summary-card compact-summary"><strong>Technician</strong><span>{{ data_get($submission->customer_service_report, 'technician_name') ?: '-' }}</span></div>
                    <div class="summary-card compact-summary"><strong>Date Inspection</strong><span>{{ data_get($submission->customer_service_report, 'date_inspection') ?: '-' }}</span></div>
                    <div class="summary-card compact-summary span-2"><strong>Suggestion / Recommendation</strong><span>{{ data_get($submission->customer_service_report, 'suggestion_recommendation') ?: '-' }}</span></div>
                </div>
            </div>
        @endif
    </section>
</div>

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
@endif

<script>
function toggleBlock(id){const el=document.getElementById(id);if(el){el.classList.toggle('show-block');}}
</script>
@endsection
