@extends('layouts.app', ['title' => 'Technician Job Request'])
@section('content')
@php
    $approved = $job->approvedQuotation();
    $review = $job->technician_review ?? [];
    $inspect = $job->inspect_data ?? [];
    $report = $job->customer_service_report ?? [];
    $activeTimer = $job->activeInspectionSession();
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
                        <input type="text" name="quotation_{{ $i }}_company_name" value="{{ $existingQuote['company_name'] ?? '' }}">
                        <label>Amount (RM)</label>
                        <input type="number" step="0.01" min="0" name="quotation_{{ $i }}_amount" value="{{ $existingQuote['amount'] ?? '' }}">
                        <label>Summary Report (required if amount > RM5000)</label>
                        <textarea name="quotation_{{ $i }}_summary_report">{{ $existingQuote['summary_report'] ?? '' }}</textarea>
                        <label>Summary Supporting Files</label>
                        <input type="file" name="quotation_{{ $i }}_summary_files[]" multiple>
                    </div>
                @endfor
            </div>
            <div class="action-row" style="margin-top:14px;"><button class="btn primary" type="submit">Submit</button></div>
        </form>
        @endunless
    </div>
</section>

<div class="content-grid tri" style="margin-top:20px; align-items:start;">
    <section class="panel shaded-panel">
        <div class="panel-head"><h3>Approved Quotation</h3></div>
        @if($approved)
            @include('components.file-preview', ['file' => $approved['file'] ?? [], 'label' => ($approved['company_name'] ?? 'Approved Quotation')])
            <div class="summary-stack" style="margin-top:14px;">
                <div class="summary-card compact-summary"><strong>Approved Slot</strong><span>Quotation {{ $job->approved_quotation_index }}</span></div>
                <div class="summary-card compact-summary"><strong>Amount</strong><span>RM {{ number_format((float) ($approved['amount'] ?? 0), 2) }}</span></div>
                <div class="summary-card compact-summary"><strong>Company</strong><span>{{ $approved['company_name'] ?? '-' }}</span></div>
            </div>

            @if($job->scheduled_date || $job->payment_type)
                <div class="summary-stack" style="margin-top:14px;">
                    <div class="summary-card compact-summary"><strong>Payment Type</strong><span>{{ $job->payment_type ? ucfirst(str_replace('_', ' ', $job->payment_type)) : '-' }}</span></div>
                    <div class="summary-card compact-summary"><strong>Schedule</strong><span>{{ optional($job->scheduled_date)->format('d M Y') ?: '-' }} {{ $job->scheduled_time ?: '' }}</span></div>
                </div>
                @if(!empty($job->payment_receipt_files))
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
            <div class="alert-card info">Approved quotation will appear here after admin selects one quotation.</div>
        @endif
    </section>

    <section class="panel shaded-panel">
        <div class="panel-head"><h3>Inspect Form</h3></div>
        <div class="inspection-timer-card">
            <div class="panel-head compact"><h4>Inspection Timer</h4></div>
            @if($activeTimer)
                <div class="alert-card info">Inspection timer is running since {{ $activeTimer['started_label'] ?? $activeTimer['started_at'] }}.</div>
                @unless($isLocked)
                <form method="POST" action="{{ route('technician.job-requests.inspection-timer', $job) }}">
                    @csrf
                    <input type="hidden" name="timer_action" value="stop">
                    <label>After stop</label>
                    <div class="radio-group">
                        <label><input type="radio" name="timer_decision" value="proceed" required> Proceed</label>
                        <label><input type="radio" name="timer_decision" value="amend" required> Amend</label>
                    </div>
                    <label>Remark (for amend)</label>
                    <input type="text" name="timer_remark" placeholder="Optional remark">
                    <div class="action-row" style="margin-top:14px;"><button class="btn danger" type="submit">Stop</button></div>
                </form>
                @endunless
            @else
                @unless($isLocked)
                <form method="POST" action="{{ route('technician.job-requests.inspection-timer', $job) }}">
                    @csrf
                    <input type="hidden" name="timer_action" value="start">
                    <button class="btn accent" type="submit">Start</button>
                </form>
                @endunless
            @endif
            @if(!empty($job->inspection_sessions))
                <div class="timeline-shell" style="margin-top:16px;">
                    @foreach($job->inspection_sessions as $session)
                        <div class="timeline-item muted-timeline">
                            <strong>{{ $session['started_label'] ?? ($session['started_at'] ?? '-') }}</strong>
                            <p>End: {{ $session['ended_label'] ?? 'Running' }}</p>
                            <p>Duration: {{ $job->formattedDuration($session['duration_seconds'] ?? null) }}</p>
                            <p class="helper-text">{{ ucfirst($session['remark'] ?? 'initial') }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @if(!empty($inspect))
            <div class="summary-stack" style="margin-top:16px;">
                <div class="summary-card compact-summary"><strong>Remark</strong><span>{{ data_get($inspect, 'inspection_remark') ?: '-' }}</span></div>
                <div class="summary-card compact-summary"><strong>Safety</strong><span>{{ data_get($inspect, 'safety_checked') ? 'Checked' : '-' }}</span></div>
                <div class="summary-card compact-summary"><strong>Quality Standard</strong><span>{{ data_get($inspect, 'quality_checked') ? 'Checked' : '-' }}</span></div>
                <div class="summary-card compact-summary"><strong>Customer Satisfaction</strong><span>{{ data_get($inspect, 'customer_satisfaction_checked') ? 'Checked' : '-' }}</span></div>
                <div class="summary-card compact-summary"><strong>Add Related Job</strong><span>{{ data_get($inspect, 'add_related_job') ? 'Yes' : 'No' }}</span></div>
            </div>
            <div class="preview-grid two-up" style="margin-top:14px;">
                @foreach(data_get($inspect, 'before_files', []) as $file)
                    @include('components.file-preview', ['file' => $file, 'label' => 'Before - ' . $file['original_name']])
                @endforeach
                @foreach(data_get($inspect, 'after_files', []) as $file)
                    @include('components.file-preview', ['file' => $file, 'label' => 'After - ' . $file['original_name']])
                @endforeach
            </div>
            @unless($isLocked)
                <div class="action-row" style="margin-top:14px;"><button class="btn ghost" type="button" onclick="toggleBlock('inspect-form')">Edit</button></div>
            @endunless
        @endif
        @unless($isLocked)
        <form method="POST" action="{{ route('technician.job-requests.inspect', $job) }}" enctype="multipart/form-data" id="inspect-form" class="{{ !empty($inspect) ? 'hidden-form-block' : '' }}">
            @csrf
            @method('PUT')
            <label>Before Files</label><input type="file" name="before_files[]" multiple>
            <label>After Files</label><input type="file" name="after_files[]" multiple>
            <label>Comment / Remark</label><textarea name="inspection_remark">{{ data_get($inspect, 'inspection_remark') }}</textarea>
            <label class="inline-check"><input type="checkbox" name="safety_checked" value="1" {{ data_get($inspect, 'safety_checked') ? 'checked' : '' }} required> Safety</label>
            <textarea name="safety_remark" placeholder="Safety remark (optional)">{{ data_get($inspect, 'safety_remark') }}</textarea>
            <label class="inline-check"><input type="checkbox" name="quality_checked" value="1" {{ data_get($inspect, 'quality_checked') ? 'checked' : '' }} required> Quality Standard</label>
            <textarea name="quality_remark" placeholder="Quality remark (optional)">{{ data_get($inspect, 'quality_remark') }}</textarea>
            <label class="inline-check"><input type="checkbox" name="customer_satisfaction_checked" value="1" {{ data_get($inspect, 'customer_satisfaction_checked') ? 'checked' : '' }} required> Customer Satisfaction</label>
            <textarea name="customer_satisfaction_remark" placeholder="Customer satisfaction remark (optional)">{{ data_get($inspect, 'customer_satisfaction_remark') }}</textarea>
            <label class="inline-check"><input type="checkbox" name="add_related_job" value="1" {{ data_get($inspect, 'add_related_job') ? 'checked' : '' }}> Add Job Related</label>
            <div class="action-row" style="margin-top:14px;"><button class="btn primary" type="submit">Submit</button></div>
        </form>
        @endunless
    </section>

    <section class="panel shaded-panel landscape-panel">
        <div class="panel-head"><h3>Invoice / Customer Service</h3></div>
        @if(!empty($job->invoice_files))
            <div class="preview-grid single-column">
                @foreach($job->invoice_files as $file)
                    @include('components.file-preview', ['file' => $file, 'label' => $file['original_name']])
                @endforeach
            </div>
        @endif
        @unless($isLocked)
        <form method="POST" action="{{ route('technician.job-requests.invoice', $job) }}" enctype="multipart/form-data" style="margin-top:14px;">
            @csrf
            <label>Upload Invoice</label>
            <input type="file" name="invoice_files[]" multiple required>
            <div class="action-row" style="margin-top:14px;"><button class="btn primary" type="submit">Submit</button></div>
        </form>
        @endunless

        @if(!empty($job->invoice_files))
            <hr style="margin:18px 0;">
            <div class="panel-head compact"><h4>Customer Service Report</h4></div>
            @if(empty($job->inspection_sessions))
                <div class="alert-card warning" style="margin-bottom:12px;">Start and stop the inspection timer first so the report can capture inspection date and duration history.</div>
            @endif

            @if(!empty($report))
                <div class="summary-stack">
                    <div class="summary-card compact-summary"><strong>Technician</strong><span>{{ data_get($report, 'technician_name') }}</span></div>
                    <div class="summary-card compact-summary"><strong>Date Inspection</strong><span>{{ data_get($report, 'date_inspection') ?: '-' }}</span></div>
                    <div class="summary-card compact-summary span-2"><strong>Suggestion</strong><span>{{ data_get($report, 'suggestion_recommendation') }}</span></div>
                </div>
                <div class="timeline-shell" style="margin-top:14px;">
                    @foreach(data_get($report, 'time_history', []) as $session)
                        <div class="timeline-item muted-timeline">
                            <strong>Start: {{ $session['started_label'] ?? ($session['started_at'] ?? '-') }}</strong>
                            <p>End: {{ $session['ended_label'] ?? '-' }}</p>
                            <p>Duration: {{ $job->formattedDuration($session['duration_seconds'] ?? null) }}</p>
                            <p class="helper-text">{{ ucfirst($session['remark'] ?? 'initial') }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="signature-display-grid" style="margin-top:14px;">
                    <div class="signature-image-card"><strong>Person in Charge</strong><img src="{{ data_get($report, 'person_in_charge_signature') }}" alt="Person in charge signature"></div>
                    <div class="signature-image-card"><strong>Verify By</strong><img src="{{ data_get($report, 'verify_by_signature') }}" alt="Verify by signature"></div>
                </div>
            @else
                <form method="POST" action="{{ route('technician.job-requests.customer-service', $job) }}" enctype="multipart/form-data" id="customer-service-form">
                    @csrf
                    <div class="summary-stack">
                        <div class="summary-card compact-summary"><strong>Nama Technician</strong><span>{{ $job->assignedTechnician?->name ?? '-' }}</span></div>
                        <div class="summary-card compact-summary"><strong>Job ID</strong><span>{{ $job->request_code }}</span></div>
                        <div class="summary-card compact-summary"><strong>Date Inspection</strong><span>{{ $job->inspectionDate() ?: '-' }}</span></div>
                    </div>
                    @if(!empty($job->inspection_sessions))
                        <div class="timeline-shell" style="margin-top:14px;">
                            @foreach($job->inspection_sessions as $session)
                                <div class="timeline-item muted-timeline">
                                    <strong>Start: {{ $session['started_label'] ?? ($session['started_at'] ?? '-') }}</strong>
                                    <p>End: {{ $session['ended_label'] ?? '-' }}</p>
                                    <p>Duration: {{ $job->formattedDuration($session['duration_seconds'] ?? null) }}</p>
                                    <p class="helper-text">{{ ucfirst($session['remark'] ?? 'initial') }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="two-col-inline" style="margin-top:14px; align-items:start;">
                        <div>
                            <label>Description of Work Done</label>
                            <textarea name="description_of_work">{{ data_get($report, 'description_of_work') }}</textarea>
                        </div>
                        <div>
                            <label>Suggestion / Recommendation</label>
                            <textarea name="suggestion_recommendation" required>{{ data_get($report, 'suggestion_recommendation') }}</textarea>
                        </div>
                    </div>
                    <label>Attachment</label>
                    <input type="file" name="attachments[]" multiple>
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
        @endif
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
</script>
@endsection
