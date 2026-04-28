@extends('layouts.app', ['title' => 'Technician Job Request'])
@section('content')
@php
    $approved = $job->approvedQuotation();
    $review = $job->technician_review ?? [];
    $inspect = $job->inspect_data ?? [];
    $report = $job->customer_service_report ?? [];
    $isLocked = (bool) $job->technician_completed_at;
    $quoteLocked = $job->approved_quotation_index !== null;
@endphp

@if($isLocked)
    <div class="alert-card success" style="margin-bottom:16px;"><strong>Customer service report submitted.</strong> This job is completed on technician side and all containers are now read-only.</div>
@endif
@if($job->status === \App\Models\ClientRequest::STATUS_CLIENT_RETURNED)
    <div class="alert-card info" style="margin-bottom:16px; background:#e8f3ff; border-color:#bfdbfe; color:#1d4ed8;"><strong>Client has returned request.</strong> New details and/or files have been submitted by the client for your review.</div>
@endif

<div class="page-header premium-page-header">
    <div>
        <h1>{{ $job->request_code }}</h1>
        <p>{{ $job->requestType->name }} • {{ $job->full_name }} • {{ $job->location?->name ?? '-' }}</p>
    </div>
    <div class="actions-inline">
        <span class="badge {{ $job->urgencyBadgeClass() }}">{{ $job->urgencyLabel() }}</span>
        <span class="badge {{ $job->technicianStatusBadgeClass() }}">{{ $job->technicianStatusLabel() }}</span>
        <a class="btn ghost" href="{{ route('technician.job-requests.index') }}">Back</a>
    </div>
</div>

<div class="overview-chip-grid" style="margin-bottom:20px;">
    <div class="overview-chip"><span>Client</span><strong>{{ $job->full_name }}</strong><small>{{ $job->user->roleLabel() }}</small></div>
    <div class="overview-chip"><span>Phone</span><strong>{{ $job->phone_number }}</strong><small>Contact number</small></div>
    <div class="overview-chip"><span>Department</span><strong>{{ $job->department?->name ?? '-' }}</strong><small>HQ only</small></div>
    <div class="overview-chip"><span>Related Job</span><strong>{{ $job->relatedRequest?->request_code ?? '-' }}</strong><small>Parent reference</small></div>
</div>

<div class="content-grid tri" style="align-items:start;">
    <section class="panel span-2 premium-stack-panel">
        <div class="panel-head"><h3>Client Request Form</h3></div>
        <div class="answer-grid">
            @foreach($job->requestType->questions as $question)
                @php($answer = $job->answers[$question->id] ?? null)
                <article class="answer-card">
                    <div class="answer-question">{!! nl2br(e($question->question_text)) !!}</div>
                    <div class="answer-response">
                        <p>{!! nl2br(e($job->displayAnswerForQuestion($question))) !!}</p>
                    </div>
                </article>
            @endforeach
        </div>

        @if(!empty($job->attachments))
            <div class="board-section">
                <div class="panel-head compact"><h4>Client Files</h4></div>
                <div class="preview-grid two-up">
                    @foreach($job->attachments as $file)
                        @include('components.file-preview', ['file' => $file, 'label' => $file['original_name']])
                    @endforeach
                </div>
            </div>
        @endif

        <div class="board-section">
            <div class="panel-head compact"><h4>Return Form to Client</h4></div>
            @if($job->technician_return_remark)
                <div class="summary-card compact-summary"><strong>Latest Return Remark</strong><span>{{ $job->technician_return_remark }}</span></div>
            @elseif(!$isLocked)
                <div class="action-row" style="margin-top:10px;"><button class="btn danger" type="button" onclick="toggleBlock('return-client-form')">Return to Client</button></div>
                <form method="POST" action="{{ route('technician.job-requests.return', $job) }}" id="return-client-form" class="hidden-form-block">
                    @csrf
                    <textarea name="technician_return_remark" placeholder="Explain what needs to be updated by the client." required>{{ old('technician_return_remark') }}</textarea>
                    <div class="action-row" style="margin-top:14px;">
                        <button class="btn danger" type="submit" onclick="setTimeout(()=>alert('Successful send request'),150)">Submit Return Request</button>
                    </div>
                </form>
            @else
                <div class="alert-card info">Return to client is locked because this job has been completed.</div>
            @endif
        </div>
    </section>

    <aside class="control-stack">
        <section class="panel shaded-panel">
            <div class="panel-head"><h3>Review</h3></div>
            <div class="summary-card compact-summary" style="margin-bottom:14px;">
                <strong>Admin ↔ Technician Remarks</strong>
                <div style="display:grid;gap:10px;margin-top:10px;">
                    @forelse($job->adminTechnicianRemarkLines() as $log)
                        <div style="border:1px solid #dbe7f5;border-radius:12px;padding:10px 12px;background:#fff;">
                            <div class="helper-text" style="margin-bottom:6px;font-weight:600;">{{ $log['header'] }}</div>
                            <div>{!! nl2br(e($log['remark'])) !!}</div>
                        </div>
                    @empty
                        <div class="helper-text">No shared remark yet.</div>
                    @endforelse
                </div>
            </div>
            @unless($isLocked)
            <div class="action-row" style="margin-bottom:14px;"><button class="btn primary" type="button" onclick="toggleBlock('technician-review-remark-form')">Add Remark</button></div>
            <form method="POST" action="{{ route('technician.job-requests.review-remark', $job) }}" id="technician-review-remark-form" class="hidden-form-block" style="margin-bottom:14px;">
                @csrf
                <label>Add Your Remark</label>
                <textarea name="remark" placeholder="Type your update here. Previous messages stay locked."></textarea>
                <div class="action-row" style="margin-top:14px;"><button class="btn primary" type="submit">Send Remark</button></div>
            </form>
            @endunless
            @if($job->technician_review_updated_at)
                <div class="summary-stack">
                    <div class="summary-card compact-summary"><strong>Clarification</strong><span>{{ ucfirst(str_replace('_', ' ', data_get($review, 'clarification_level', '-'))) }}</span></div>
                    <div class="summary-card compact-summary"><strong>Repair Channel</strong><span>{{ ucfirst(str_replace('_', ' ', data_get($review, 'repair_channel', '-'))) }}</span></div>
                    <div class="summary-card compact-summary"><strong>Repair Scale</strong><span>{{ ucfirst(str_replace('_', ' ', data_get($review, 'repair_scale', '-'))) }}</span></div>
                    <div class="summary-card compact-summary"><strong>Processing</strong><span>{{ ucfirst(str_replace('_', ' ', data_get($review, 'processing_type', '-'))) }}</span></div>
                    <div class="summary-card compact-summary"><strong>Visit Site</strong><span>{{ ucfirst(data_get($review, 'visit_site', 'no')) }}</span></div>
                    @if(data_get($review, 'visit_site_remark'))
                        <div class="summary-card compact-summary"><strong>Visit Site Remark</strong><span>{{ data_get($review, 'visit_site_remark') }}</span></div>
                    @endif
                    @if(!empty(data_get($review, 'visit_site_files', [])))
                        <div class="summary-card compact-summary span-2"><strong>Visit Site Files</strong>
                            <div class="preview-grid customer-service-attachment-grid" style="margin-top:12px;">
                                @foreach(data_get($review, 'visit_site_files', []) as $file)
                                    @include('components.file-preview', ['file' => $file, 'label' => $file['original_name'] ?? 'Visit site file'])
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                @unless($isLocked)
                    <div class="action-row" style="margin-top:14px;"><button class="btn ghost" type="button" onclick="toggleBlock('tech-review-form')">Edit</button></div>
                @endunless
            @endif

            @unless($isLocked)
            <form method="POST" action="{{ route('technician.job-requests.review', $job) }}" enctype="multipart/form-data" id="tech-review-form" class="{{ $job->technician_review_updated_at ? 'hidden-form-block' : '' }}">
                @csrf
                @method('PUT')
                <label>Clarification</label>
                <select name="clarification_level" required>
                    <option value="critical" {{ data_get($review, 'clarification_level') === 'critical' ? 'selected' : '' }}>Critical</option>
                    <option value="urgent" {{ data_get($review, 'clarification_level') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                    <option value="normal" {{ data_get($review, 'clarification_level', 'normal') === 'normal' ? 'selected' : '' }}>Normal</option>
                </select>
                <label>Repair Channel</label>
                <select name="repair_channel" required>
                    <option value="in_house_repair" {{ data_get($review, 'repair_channel') === 'in_house_repair' ? 'selected' : '' }}>In-House Repair</option>
                    <option value="vendor_required" {{ data_get($review, 'repair_channel') === 'vendor_required' ? 'selected' : '' }}>Vendor Required</option>
                </select>
                <label>Repair Scale</label>
                <select name="repair_scale" required>
                    <option value="minor_repair" {{ data_get($review, 'repair_scale') === 'minor_repair' ? 'selected' : '' }}>Minor Repair</option>
                    <option value="major_repair" {{ data_get($review, 'repair_scale') === 'major_repair' ? 'selected' : '' }}>Major Repair</option>
                </select>
                <label>Processing</label>
                <select name="processing_type" required>
                    <option value="internal" {{ data_get($review, 'processing_type') === 'internal' ? 'selected' : '' }}>Internal</option>
                    <option value="outsource" {{ data_get($review, 'processing_type') === 'outsource' ? 'selected' : '' }}>Outsource</option>
                </select>
                <label>Visit Site</label>
                <select name="visit_site" id="visit-site-select">
                    <option value="no" {{ data_get($review, 'visit_site', 'no') === 'no' ? 'selected' : '' }}>No</option>
                    <option value="yes" {{ data_get($review, 'visit_site') === 'yes' ? 'selected' : '' }}>Yes</option>
                </select>
                <div id="visit-site-extra" class="hidden-form-block {{ data_get($review, 'visit_site') === 'yes' ? 'show-block' : '' }}">
                    <label>Visit Site Remark</label>
                    <textarea name="visit_site_remark">{{ data_get($review, 'visit_site_remark') }}</textarea>
                    <label>Visit Site Files</label>
                    <input type="file" name="visit_site_files[]" multiple>
                </div>
                <div class="action-row" style="margin-top:14px;"><button class="btn primary" type="submit">Submit</button></div>
            </form>
            @endunless
        </section>
    </aside>
</div>

<section class="panel shaded-panel landscape-panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Costing / Quotation</h3></div>
    <div class="tab-toggle-shell">
        <button class="btn small ghost active" type="button" data-tab-target="costing">Costing Form</button>
        <button class="btn small ghost" type="button" data-tab-target="quotation">Quotation Form</button>
    </div>

    <div class="tab-panel is-active" data-tab-panel="costing">
        @if(!empty($job->costing_entries))
            <div class="summary-stack">
                @foreach($job->costing_entries as $item)
                    <div class="summary-card compact-summary"><strong>{{ $item['equipment_type'] }}</strong><span>RM {{ number_format((float) $item['equipment_price'], 2) }}</span></div>
                @endforeach
            </div>
            @unless($isLocked)
                <div class="action-row" style="margin-top:14px;"><button class="btn ghost" type="button" onclick="toggleBlock('costing-form')">Edit</button></div>
            @endunless
        @endif
        @unless($isLocked)
        <form method="POST" action="{{ route('technician.job-requests.costing', $job) }}" enctype="multipart/form-data" id="costing-form" class="{{ !empty($job->costing_entries) ? 'hidden-form-block' : '' }}">
            @csrf
            <div id="costing-items-wrap">
                @php($costItems = !empty($job->costing_entries) ? $job->costing_entries : [['equipment_type' => '', 'equipment_price' => '']])
                @foreach($costItems as $index => $item)
                    <div class="two-col-inline costing-row">
                        <input type="text" name="costing_items[{{ $index }}][equipment_type]" value="{{ $item['equipment_type'] ?? '' }}" placeholder="Equipment type" required>
                        <input type="number" step="0.01" min="0" name="costing_items[{{ $index }}][equipment_price]" value="{{ $item['equipment_price'] ?? '' }}" placeholder="Price (RM)" required>
                    </div>
                @endforeach
            </div>
            <button class="btn small ghost" type="button" id="add-costing-item">Add Item</button>
            <label style="margin-top:14px; display:block;">Upload Receipt</label>
            <input type="file" name="costing_receipts[]" multiple>
            <div class="action-row" style="margin-top:14px;"><button class="btn primary" type="submit">Submit</button></div>
        </form>
        @endunless
    </div>

    <div class="tab-panel" data-tab-panel="quotation">
        @if($job->quotation_return_remark)
            <div class="alert-card warning" style="margin-bottom:12px;"><strong>Quotation returned by admin</strong><div>{{ $job->quotation_return_remark }}</div></div>
        @endif
        @if(!empty($job->quotation_entries))
            <div class="summary-stack">
                @foreach($job->quotation_entries as $quote)
                    <div class="summary-card compact-summary"><strong>Quotation {{ $quote['slot'] }}</strong><span>{{ $quote['company_name'] ?? '-' }} • RM {{ number_format((float) ($quote['amount'] ?? 0), 2) }}</span></div>
                @endforeach
            </div>
            @unless($quoteLocked || $isLocked)
                <div class="action-row" style="margin-top:14px;"><button class="btn ghost" type="button" onclick="toggleBlock('quotation-form')">Edit</button></div>
            @endunless
        @endif
        @if($quoteLocked)
            <div class="alert-card info" style="margin-bottom:12px;">Quotation has been approved by admin. Editing is locked. Upload history remains visible for evidence.</div>
        @endif
        @unless($quoteLocked || $isLocked)
        <form method="POST" action="{{ route('technician.job-requests.quotation', $job) }}" enctype="multipart/form-data" id="quotation-form" class="{{ !empty($job->quotation_entries) ? 'hidden-form-block' : '' }}">
            @csrf
            <div class="quote-landscape-grid">
                @for($i = 1; $i <= 3; $i++)
                    @php($existingQuote = collect($job->quotation_entries ?? [])->firstWhere('slot', $i) ?? [])
                    <div class="quote-slot-card">
                        <h4>Quotation {{ $i }} @if($i === 1)<span class="helper-text">(Required)</span>@endif</h4>
                        <label>Upload File</label>
                        <input type="file" name="quotation_{{ $i }}_file" {{ $i === 1 && empty($existingQuote['file']) ? 'required' : '' }}>
                        <label>Company Name</label>
                        <select name="quotation_{{ $i }}_vendor_id" class="vendor-select" data-slot="{{ $i }}">
                            <option value="">Select registered vendor</option>
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->id }}" {{ (string) ($existingQuote['vendor_id'] ?? '') === (string) $vendor->id ? 'selected' : '' }}>{{ $vendor->company_name }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="quotation_{{ $i }}_company_name" value="{{ $existingQuote['company_name'] ?? '' }}">
                        <label class="inline-check" style="margin-top:10px;"><input type="checkbox" name="quotation_{{ $i }}_subject_to_approval" value="1" class="subject-to-approval-toggle" data-slot="{{ $i }}" {{ !empty($existingQuote['subject_to_approval']) ? 'checked' : '' }}> Subject To Approval</label>
                        <div id="manual-company-wrap-{{ $i }}" class="{{ !empty($existingQuote['subject_to_approval']) ? 'show-block' : '' }}" style="display:{{ !empty($existingQuote['subject_to_approval']) ? 'block' : 'none' }};">
                            <label>Manual Company Name</label>
                            <input type="text" name="quotation_{{ $i }}_manual_company_name" value="{{ !empty($existingQuote['subject_to_approval']) ? ($existingQuote['company_name'] ?? '') : '' }}">
                        </div>
                        <label>Amount (RM)</label>
                        <input type="number" step="0.01" min="0" name="quotation_{{ $i }}_amount" value="{{ $existingQuote['amount'] ?? '' }}" class="quotation-amount-input" data-slot="{{ $i }}">
                        <div class="quotation-summary-extra {{ (float) ($existingQuote['amount'] ?? 0) > 5000 ? 'show-block' : '' }}" id="quotation-summary-extra-{{ $i }}">
                            <label>Summary Report (required if amount > RM5000)</label>
                            <textarea name="quotation_{{ $i }}_summary_report">{{ $existingQuote['summary_report'] ?? '' }}</textarea>
                            <label>Summary Supporting Files</label>
                            <input type="file" name="quotation_{{ $i }}_summary_files[]" multiple>
                        </div>
                    </div>
                @endfor
            </div>
            <div class="action-row" style="margin-top:14px;"><button class="btn primary" type="submit">Submit</button></div>
        </form>
        @endunless
    </div>
</section>

<div class="content-grid tri tech-bottom-grid" style="margin-top:20px; align-items:start;">
    <section class="panel shaded-panel">
        <div class="panel-head"><h3>Approved Quotation</h3></div>
        @if($approved)
            @if(!empty(data_get($approved, 'file.path')))
                @include('components.file-preview', ['file' => $approved['file'] ?? [], 'label' => ($approved['company_name'] ?? 'Approved Quotation')])
            @elseif(($approved['type'] ?? null) === 'costing')
                <div class="alert-card success">Approved costing form is ready. Costing receipts and item details are shown below.</div>
            @endif
            <div class="summary-stack" style="margin-top:14px;">
                <div class="summary-card compact-summary"><strong>Approved Type</strong><span>{{ ($approved['type'] ?? null) === 'costing' ? 'Costing Form' : 'Quotation ' . $job->approved_quotation_index }}</span></div>
                <div class="summary-card compact-summary"><strong>{{ ($approved['type'] ?? null) === 'costing' ? 'Approved Cost' : 'Amount' }}</strong><span>RM {{ number_format((float) ($approved['amount'] ?? 0), 2) }}</span></div>
                <div class="summary-card compact-summary"><strong>Company / Source</strong><span>{{ $approved['company_name'] ?? '-' }}</span></div>
                @if(!empty($approved['subject_to_approval']))<div class="summary-card compact-summary"><strong>Mode</strong><span>Subject To Approval</span></div>@endif
            </div>
            @if(($approved['type'] ?? null) === 'costing' && !empty($approved['items']))
                <div class="summary-stack" style="margin-top:12px;">
                    @foreach($approved['items'] as $costItem)
                        <div class="summary-card compact-summary">
                            <strong>{{ $costItem['equipment_type'] ?? '-' }}</strong>
                            <span>RM {{ number_format((float) ($costItem['equipment_price'] ?? 0), 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
            @if(!empty($approved['summary_files']))
                <div class="board-section" style="margin-top:12px;">
                    <div class="panel-head compact"><h4>Supporting Files</h4></div>
                    <div class="preview-grid two-up" style="margin-top:12px;">
                        @foreach($approved['summary_files'] as $file)
                            @include('components.file-preview', ['file' => $file, 'label' => $file['original_name'] ?? 'Supporting file'])
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            <div class="alert-card info">Approved quotation will appear here after admin selects one quotation.</div>
        @endif
    </section>

    <section class="panel shaded-panel">
        <div class="panel-head"><h3>Payment / Receipt / Schedule</h3></div>
        @if($approved)
            @if($job->payment_type || !empty($job->payment_receipt_history) || !empty($job->payment_receipt_files) || $job->scheduled_date)
                <div class="summary-stack">
                    <div class="summary-card compact-summary"><strong>Payment Type</strong><span>{{ $job->payment_type ? ucfirst(str_replace('_', ' ', $job->payment_type)) : '-' }}</span></div>
                    <div class="summary-card compact-summary"><strong>Schedule</strong><span>{{ optional($job->scheduled_date)->format('d M Y') ?: '-' }} {{ $job->scheduled_time ?: '' }}</span></div>
                </div>
                @if(!empty($job->payment_receipt_history))
                    <div class="summary-stack" style="margin-top:12px;">
                        @foreach($job->payment_receipt_history as $history)
                            <div class="summary-card compact-summary span-2">
                                <strong>{{ ucfirst(str_replace('_', ' ', $history['payment_type'] ?? '-')) }}</strong>
                                <span>{{ $history['uploaded_label'] ?? ($history['uploaded_at'] ?? '-') }}</span>
                                @if(!empty($history['files']))
                                    <div class="preview-grid single-column" style="margin-top:12px;">
                                        @foreach($history['files'] as $file)
                                            @include('components.file-preview', ['file' => $file, 'label' => ($history['uploaded_label'] ?? 'Receipt Upload') . ' - ' . ($file['original_name'] ?? 'Receipt')])
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @elseif(!empty($job->payment_receipt_files))
                    <div class="preview-grid single-column" style="margin-top:12px;">
                        @foreach($job->payment_receipt_files as $file)
                            @include('components.file-preview', ['file' => $file, 'label' => $file['original_name']])
                        @endforeach
                    </div>
                @endif
                @unless($isLocked)
                    <div class="action-row" style="margin-top:14px;"><button class="btn ghost" type="button" onclick="toggleBlock('work-form')">Edit</button></div>
                @endunless
            @endif

            @unless($isLocked)
            <form method="POST" action="{{ route('technician.job-requests.work', $job) }}" enctype="multipart/form-data" id="work-form" class="{{ ($job->scheduled_date || $job->payment_type) ? 'hidden-form-block' : '' }}">
                @csrf
                @method('PUT')
                <label>Upload Receipt</label>
                <input type="file" name="payment_receipt_files[]" multiple>
                <label>Payment Type</label>
                <div class="radio-group">
                    <label><input type="radio" name="payment_type" value="balance" {{ $job->payment_type === 'balance' ? 'checked' : '' }} required> Balance</label>
                    <label><input type="radio" name="payment_type" value="downpayment" {{ $job->payment_type === 'downpayment' ? 'checked' : '' }} required> Downpayment</label>
                    <label><input type="radio" name="payment_type" value="full_payment" {{ $job->payment_type === 'full_payment' ? 'checked' : '' }} required> Full Payment</label>
                </div>
                <div class="two-col-inline">
                    <div><label>Date</label><input type="date" name="scheduled_date" value="{{ optional($job->scheduled_date)->toDateString() }}" required></div>
                    <div><label>Time</label><input type="time" name="scheduled_time" value="{{ $job->scheduled_time }}" required></div>
                </div>
                <div class="action-row" style="margin-top:14px;"><button class="btn primary" type="submit">Submit</button></div>
            </form>
            @endunless
        @else
            <div class="alert-card info">Payment and schedule entry will be available after admin approves the quotation.</div>
        @endif
    </section>

    <section class="panel shaded-panel landscape-panel">
        <div class="panel-head"><h3>Inspection Form</h3></div>

        <div class="board-section inspection-clean-panel">
            <div class="panel-head compact"><h4>Inspection Summary</h4></div>
            @if(!empty($inspect))
                <div class="field-pair-grid">
                    <div class="summary-card compact-summary"><strong>Inspection Remark</strong><span>{{ data_get($inspect, 'inspection_remark') ?: '-' }}</span></div>
                    <div class="summary-card compact-summary"><strong>Add Related Job</strong><span>{{ data_get($inspect, 'add_related_job') ? 'Yes' : 'No' }}</span></div>
                    <div class="summary-card compact-summary"><strong>Safety</strong><span>{{ data_get($inspect, 'safety_checked') ? 'Checked' : '-' }}</span></div>
                    <div class="summary-card compact-summary"><strong>Safety Remark</strong><span>{{ data_get($inspect, 'safety_remark') ?: '-' }}</span></div>
                    <div class="summary-card compact-summary"><strong>Quality Standard</strong><span>{{ data_get($inspect, 'quality_checked') ? 'Checked' : '-' }}</span></div>
                    <div class="summary-card compact-summary"><strong>Quality Remark</strong><span>{{ data_get($inspect, 'quality_remark') ?: '-' }}</span></div>
                    <div class="summary-card compact-summary"><strong>Customer Satisfaction</strong><span>{{ data_get($inspect, 'customer_satisfaction_checked') ? 'Checked' : '-' }}</span></div>
                    <div class="summary-card compact-summary"><strong>Customer Satisfaction Remark</strong><span>{{ data_get($inspect, 'customer_satisfaction_remark') ?: '-' }}</span></div>
                </div>
                @if(!empty(data_get($inspect, 'before_files', [])) || !empty(data_get($inspect, 'after_files', [])))
                    <div class="content-grid two-two" style="margin-top:16px;">
                        <div class="summary-card compact-summary">
                            <strong>Before Files</strong>
                            <div class="preview-grid single-column" style="margin-top:12px;">
                                @forelse(data_get($inspect, 'before_files', []) as $file)
                                    @include('components.file-preview', ['file' => $file, 'label' => $file['original_name'] ?? 'Before file'])
                                @empty
                                    <span class="helper-text">-</span>
                                @endforelse
                            </div>
                        </div>
                        <div class="summary-card compact-summary">
                            <strong>After Files</strong>
                            <div class="preview-grid single-column" style="margin-top:12px;">
                                @forelse(data_get($inspect, 'after_files', []) as $file)
                                    @include('components.file-preview', ['file' => $file, 'label' => $file['original_name'] ?? 'After file'])
                                @empty
                                    <span class="helper-text">-</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif

                @unless($isLocked || !empty($report))
                    <div class="action-row" style="margin-top:14px;"><button class="btn ghost" type="button" onclick="toggleBlock('inspection-form-edit')">Edit Inspection Form</button></div>
                @endunless
            @elseif($isLocked)
                <div class="alert-card warning">Inspection form was not available on this completed job.</div>
            @else
                <div class="alert-card info" style="margin-bottom:12px;">Technician must submit the inspection form here first. Customer service report and client feedback stay locked until this is submitted.</div>
            @endif

            @unless($isLocked || !empty($report))
                <form method="POST" action="{{ route('technician.job-requests.inspect', $job) }}" enctype="multipart/form-data" id="inspection-form-edit" class="{{ !empty($inspect) ? 'hidden-form-block' : 'show-block' }}" style="margin-top:16px;">
                    @csrf
                    @method('PUT')
                    <div class="content-grid two-two">
                        <div>
                            <label>Before Files</label>
                            <input type="file" name="before_files[]" multiple>
                        </div>
                        <div>
                            <label>After Files</label>
                            <input type="file" name="after_files[]" multiple>
                        </div>
                    </div>
                    <div style="margin-top:14px;">
                        <label>Inspection Remark</label>
                        <textarea name="inspection_remark">{{ old('inspection_remark', data_get($inspect, 'inspection_remark')) }}</textarea>
                    </div>
                    <div class="content-grid two-two" style="margin-top:14px;">
                        <div class="summary-card compact-summary">
                            <label style="display:flex;gap:10px;align-items:flex-start;"><input type="checkbox" name="safety_checked" value="1" required {{ old('safety_checked', data_get($inspect, 'safety_checked')) ? 'checked' : '' }}><span><strong>Safety Checked</strong><small style="display:block;">Confirm work area safety has been checked.</small></span></label>
                            <textarea name="safety_remark" placeholder="Safety remark" style="margin-top:10px;">{{ old('safety_remark', data_get($inspect, 'safety_remark')) }}</textarea>
                        </div>
                        <div class="summary-card compact-summary">
                            <label style="display:flex;gap:10px;align-items:flex-start;"><input type="checkbox" name="quality_checked" value="1" required {{ old('quality_checked', data_get($inspect, 'quality_checked')) ? 'checked' : '' }}><span><strong>Quality Standard Checked</strong><small style="display:block;">Confirm repair quality meets the required standard.</small></span></label>
                            <textarea name="quality_remark" placeholder="Quality remark" style="margin-top:10px;">{{ old('quality_remark', data_get($inspect, 'quality_remark')) }}</textarea>
                        </div>
                        <div class="summary-card compact-summary">
                            <label style="display:flex;gap:10px;align-items:flex-start;"><input type="checkbox" name="customer_satisfaction_checked" value="1" required {{ old('customer_satisfaction_checked', data_get($inspect, 'customer_satisfaction_checked')) ? 'checked' : '' }}><span><strong>Customer Satisfaction Checked</strong><small style="display:block;">Confirm technician has reviewed satisfaction readiness.</small></span></label>
                            <textarea name="customer_satisfaction_remark" placeholder="Customer satisfaction remark" style="margin-top:10px;">{{ old('customer_satisfaction_remark', data_get($inspect, 'customer_satisfaction_remark')) }}</textarea>
                        </div>
                        <div class="summary-card compact-summary">
                            <label style="display:flex;gap:10px;align-items:center;"><input type="checkbox" name="add_related_job" value="1" {{ old('add_related_job', data_get($inspect, 'add_related_job')) ? 'checked' : '' }}><span><strong>Add Related Job</strong></span></label>
                            <small style="display:block;margin-top:10px;">Tick this if a related follow-up job needs to be created.</small>
                        </div>
                    </div>
                    <div class="action-row" style="margin-top:14px;"><button class="btn primary" type="submit">{{ !empty($inspect) ? 'Update Inspection Form' : 'Submit Inspection Form' }}</button></div>
                </form>
            @endunless
        </div>
    </section>

    <section class="panel shaded-panel" style="margin-top:20px;">
        <div class="panel-head"><h3>Management Viewer Remark Summary</h3></div>
        <div class="summary-card compact-summary">
            <strong>History Log</strong>
            <div style="display:grid;gap:10px;margin-top:10px;">
                @forelse($job->viewerSummaryHistoryLines() as $log)
                    <div style="border:1px solid #dbe7f5;border-radius:12px;padding:10px 12px;background:#fff;">
                        <div class="helper-text" style="margin-bottom:6px;font-weight:600;">{{ $log['header'] }}</div>
                        <div>{!! nl2br(e($log['remark'])) !!}</div>
                        @if($log['signature'])
                            <div class="signature-image-card" style="margin-top:10px;max-width:220px;"><img src="{{ $log['signature'] }}" alt="Viewer history signature"></div>
                        @endif
                    </div>
                @empty
                    <div class="helper-text">No viewer history yet.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="panel shaded-panel landscape-panel customer-service-panel-wide" style="margin-top:20px;">
        <div class="panel-head"><h3>Customer Service Report</h3></div>
        <div class="board-section">
            <div class="panel-head compact"><h4>Service Summary</h4></div>
            @if(!$job->inspect_data)
                <div class="alert-card warning">Submit inspection form first before filling the customer service report.</div>
            @elseif(empty($job->inspection_sessions))
                <div class="alert-card warning">Please create technician daily log records from the assigned job request list first.</div>
            @elseif(!empty($report))
                <div class="summary-stack customer-service-landscape-grid customer-service-summary-wide">
                    <div class="summary-card compact-summary"><strong>Technician</strong><span>{{ data_get($report, 'technician_name') }}</span></div>
                    <div class="summary-card compact-summary"><strong>Date</strong><span>{{ data_get($report, 'date_inspection') ?: '-' }}</span></div>
                    <div class="summary-card compact-summary"><strong>Duration of Work</strong><span>{{ data_get($report, 'duration_of_work') ?: '-' }}</span></div>
                    <div class="summary-card compact-summary"><strong>Submitted At</strong><span>{{ data_get($report, 'submitted_at') ?: '-' }}</span></div>
                    <div class="summary-card compact-summary span-2"><strong>Description of Work</strong><span>{!! nl2br(e(data_get($report, 'description_of_work'))) !!}</span></div>
        @if(!empty(data_get($report, 'description_entries', [])))
            <div class="summary-card compact-summary span-2"><strong>Description Remark Evidence</strong><div style="display:grid;gap:12px;margin-top:10px;">@foreach(data_get($report, 'description_entries', []) as $entry)<div style="border:1px solid #dbe7f5;border-radius:12px;padding:10px 12px;background:#fff;display:grid;gap:8px;"><div><strong>{{ $entry['date_label'] ?? '-' }}</strong> • {{ $entry['time_range'] ?? '-' }} • {{ $entry['duration_label'] ?? '-' }}</div><div>{{ $entry['remark'] ?? '-' }}</div><div class="helper-text">Verify signed: {{ $entry['verify_by_signed_at_label'] ?? '-' }}</div>@if(!empty($entry['verify_by_signature']))<div class="signature-image-card" style="max-width:220px;"><img src="{{ $entry['verify_by_signature'] }}" alt="CSR verify signature"></div>@endif</div>@endforeach</div></div>
        @endif
                    <div class="summary-card compact-summary span-2"><strong>Suggestion</strong><span>{{ data_get($report, 'suggestion_recommendation') ?: '-' }}</span></div>
                </div>
                @if(!empty(data_get($report, 'attachments', [])))
                    <div class="preview-grid two-up" style="margin-top:12px;">
                        @foreach(data_get($report, 'attachments', []) as $file)
                            @include('components.file-preview', ['file' => $file, 'label' => $file['original_name'] ?? 'CSR attachment'])
                        @endforeach
                    </div>
                @endif
                <div class="signature-display-grid" style="margin-top:14px;">
                    @if(data_get($report, 'person_in_charge_signature'))<div class="signature-image-card"><strong>Person in Charge</strong><img src="{{ data_get($report, 'person_in_charge_signature') }}" alt="Person in charge signature"></div>@endif
                    @if(data_get($report, 'verify_by_signature'))<div class="signature-image-card"><strong>Verify By</strong><img src="{{ data_get($report, 'verify_by_signature') }}" alt="Verify by signature"></div>@endif
                </div>
            @else
                <form method="POST" action="{{ route('technician.job-requests.customer-service', $job) }}" enctype="multipart/form-data" id="customer-service-form">
                    @csrf
                    <div class="summary-stack customer-service-landscape-grid customer-service-summary-wide">
                        <div class="summary-card compact-summary"><strong>Nama Technician</strong><span>{{ $job->assignedTechnician?->name ?? '-' }}</span></div>
                        <div class="summary-card compact-summary"><strong>Job ID</strong><span>{{ $job->request_code }}</span></div>
                        <div class="summary-card compact-summary"><strong>Date</strong><span>{{ now('Asia/Kuala_Lumpur')->format('d M Y') }}</span></div>
                        <div class="summary-card compact-summary"><strong>Duration of Work</strong><span>{{ $job->formattedDuration($job->totalInspectionDurationSeconds()) }}</span></div>
                    </div>
                    <div style="margin-top:14px;">
                        <label>Description of Work</label>
                        <textarea name="description_of_work" readonly>{{ $job->compiledDailyLogDescription() }}</textarea>
                    </div>
                    <div style="margin-top:14px;">
                        <label>Suggestion / Recommendation</label>
                        <textarea name="suggestion_recommendation">{{ data_get($report, 'suggestion_recommendation') }}</textarea>
                    </div>
                    <label>Attachment (required)</label>
                    <input type="file" name="attachments[]" multiple required>
                    @if(!empty($job->inspection_sessions))
                        <details style="margin-top:12px;">
                            <summary class="btn tiny ghost" style="display:inline-flex;">View Daily Log Attachments</summary>
                            <div class="preview-grid two-up" style="margin-top:12px;">
                                @foreach($job->inspection_sessions as $session)
                                    @foreach(($session['attachments'] ?? []) as $file)
                                        @include('components.file-preview', ['file' => $file, 'label' => ($session['date_label'] ?? '-') . ' - ' . ($file['original_name'] ?? 'Attachment')])
                                    @endforeach
                                @endforeach
                            </div>
                        </details>
                    @endif
                    <div class="signature-pad-shell">
                        <div>
                            <label>Person in Charge</label>
                            <canvas class="signature-pad" data-target="person-in-charge-signature"></canvas>
                            <input type="hidden" name="person_in_charge_signature" id="person-in-charge-signature" value="{{ data_get($report, 'person_in_charge_signature') }}" required>
                            <button class="btn tiny ghost signature-clear" type="button">Clear</button>
                        </div>
                        <div>
                            <label>Verify By</label>
                            <canvas class="signature-pad" data-target="verify-by-signature"></canvas>
                            <input type="hidden" name="verify_by_signature" id="verify-by-signature" value="{{ data_get($report, 'verify_by_signature') }}" required>
                            <button class="btn tiny ghost signature-clear" type="button">Clear</button>
                        </div>
                    </div>
                    <div class="action-row" style="margin-top:14px;"><button class="btn primary" type="submit">Submit</button></div>
                </form>
            @endif
        </div>
    </section>
</div>

<script>
function toggleBlock(id){const el=document.getElementById(id);if(el){el.classList.toggle('show-block');}}
const addCostingBtn = document.getElementById('add-costing-item');
if (addCostingBtn) {
  addCostingBtn.addEventListener('click', function () {
    const wrap = document.getElementById('costing-items-wrap');
    const index = wrap.querySelectorAll('.costing-row').length;
    const row = document.createElement('div');
    row.className = 'two-col-inline costing-row';
    row.innerHTML = `<input type="text" name="costing_items[${index}][equipment_type]" placeholder="Equipment type" required><input type="number" step="0.01" min="0" name="costing_items[${index}][equipment_price]" placeholder="Price (RM)" required>`;
    wrap.appendChild(row);
  });
}
document.querySelectorAll('[data-tab-target]').forEach(button => {
  button.addEventListener('click', () => {
    const target = button.dataset.tabTarget;
    document.querySelectorAll('[data-tab-target]').forEach(btn => btn.classList.remove('is-active'));
    document.querySelectorAll('[data-tab-panel]').forEach(panel => panel.classList.remove('is-active'));
    button.classList.add('is-active');
    document.querySelector(`[data-tab-panel="${target}"]`)?.classList.add('is-active');
  });
});
const visitSelect = document.getElementById('visit-site-select');
if (visitSelect) {
  visitSelect.addEventListener('change', function () {
    document.getElementById('visit-site-extra')?.classList.toggle('show-block', this.value === 'yes');
  });
}
function bindSignaturePad(canvas) {
  if (!canvas) return;
  const target = document.getElementById(canvas.dataset.target);
  const ctx = canvas.getContext('2d');
  let drawing = false;
  const ratio = window.devicePixelRatio || 1;
  const resize = () => {
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * ratio;
    canvas.height = rect.height * ratio;
    ctx.scale(ratio, ratio);
    ctx.lineWidth = 2; ctx.lineCap = 'round';
  };
  resize();
  const point = (e) => { const rect = canvas.getBoundingClientRect(); const source = e.touches ? e.touches[0] : e; return { x: source.clientX - rect.left, y: source.clientY - rect.top }; };
  const start = (e) => { drawing = true; const p = point(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); };
  const move = (e) => { if (!drawing) return; const p = point(e); ctx.lineTo(p.x, p.y); ctx.stroke(); target.value = canvas.toDataURL('image/png'); e.preventDefault(); };
  const stop = () => { drawing = false; target.value = canvas.toDataURL('image/png'); };
  canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move); window.addEventListener('mouseup', stop);
  canvas.addEventListener('touchstart', start, { passive: false }); canvas.addEventListener('touchmove', move, { passive: false }); window.addEventListener('touchend', stop);
  canvas.parentElement.querySelector('.signature-clear')?.addEventListener('click', () => { ctx.clearRect(0, 0, canvas.width, canvas.height); target.value=''; });
}
document.querySelectorAll('.signature-pad').forEach(bindSignaturePad);
document.querySelectorAll('.subject-to-approval-toggle').forEach((toggle) => {
    toggle.addEventListener('change', function(){
        const slot = this.dataset.slot;
        const wrap = document.getElementById(`manual-company-wrap-${slot}`);
        if (wrap) { wrap.style.display = this.checked ? 'block' : 'none'; }
    });
});

document.querySelectorAll('.quotation-amount-input').forEach((input) => {
  const sync = () => {
    const slot = input.dataset.slot;
    const target = document.getElementById(`quotation-summary-extra-${slot}`);
    if (!target) return;
    target.classList.toggle('show-block', Number(input.value || 0) > 5000);
  };
  input.addEventListener('input', sync);
  sync();
});
</script>
@endsection
