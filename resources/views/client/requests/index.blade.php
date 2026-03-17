@extends('layouts.app', ['title' => 'Client Request'])
@section('content')
@php
    $fileUrl = function ($path) {
        return $path ? route('files.show', ['encodedPath' => rtrim(strtr(base64_encode($path), '+/', '-_'), '=')]) : null;
    };
@endphp
@php
    $isEditing = (bool) $editingRequest;
    $isRelatedMode = (bool) $relatedSourceRequest;
    $showFormPanel = $showFormPage ?? ($isEditing || $errors->any() || $isRelatedMode);
    $needsAttentionCount = $requests->filter(fn ($item) => in_array($item->status, [\App\Models\ClientRequest::STATUS_RETURNED, \App\Models\ClientRequest::STATUS_PENDING_CUSTOMER_REVIEW], true))->count();
    $completedCount = $requests->where('status', \App\Models\ClientRequest::STATUS_COMPLETED)->count();
    $activeCount = $requests->where('status', '!=', \App\Models\ClientRequest::STATUS_COMPLETED)->count();
    $scheduledCount = $requests->filter(fn ($item) => $item->scheduled_date && $item->status !== \App\Models\ClientRequest::STATUS_COMPLETED)->count();
    $taskTitle = null;
    if ($isRelatedMode) {
        $taskQuestion = collect($relatedSourceRequest->requestType?->questions ?? [])->firstWhere('question_type', \App\Models\RequestQuestion::TYPE_REMARK);
        $taskTitle = $taskQuestion ? data_get($relatedSourceRequest->answers, $taskQuestion->id) : null;
    }
@endphp

@if($errors->any())
    <div class="alert-card danger client-alert-banner">
        <strong>Please review the form.</strong>
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($requests->where('status', \App\Models\ClientRequest::STATUS_RETURNED)->count())
    <div class="alert-card warning client-alert-banner">
        <strong>Returned request detected.</strong>
        Technician requested resubmission for one or more jobs. Review the remark shown in the request list and resubmit the same form.
    </div>
@endif

@if(!$showFormPanel)
<div class="client-request-layout list-only-layout">
    @if($activeTab === 'new')
    <section class="panel premium-table-panel client-table-panel order-first-mobile full-width-panel">
        <div class="premium-section-head">
            <div>
                <h3>My Requests</h3>
                <p>Track current status, urgency, technician assignment, and jump straight into resubmission or customer review.</p>
            </div>
            <div class="table-head-badges">
                <a class="btn accent" href="{{ route('client.requests.index', ['tab' => 'new', 'form' => 1]) }}">Add Request</a>
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
                    @forelse($requests as $request)
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
                                            <span>{{ $request->location?->name ?? '-' }}</span>
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
                                        <small>Your updated details have been sent back to technician.</small>
                                    @elseif($request->status === \App\Models\ClientRequest::STATUS_PENDING_CUSTOMER_REVIEW)
                                        <small>Please complete the feedback form below.</small>
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
                                <div class="client-action-stack">
                                    @if($request->status === \App\Models\ClientRequest::STATUS_REJECTED)
                                        <div class="status-stack-cell"><span class="helper-text">{{ $request->admin_approval_remark ?: 'Request rejected by admin.' }}</span></div>
                                    @elseif($request->status === \App\Models\ClientRequest::STATUS_RETURNED)
                                        <a class="btn small accent" href="{{ route('client.requests.index', ['tab' => 'new', 'edit' => $request->id, 'form' => 1]) }}">Resubmit</a>
                                    @elseif($request->status === \App\Models\ClientRequest::STATUS_PENDING_CUSTOMER_REVIEW)
                                        <a class="btn small primary" href="#feedback-{{ $request->id }}">Open Review</a>
                                    @elseif(data_get($request->inspect_data, 'add_related_job') && !$request->childRequests()->where('user_id', $user->id)->exists())
                                        <a class="btn small ghost" href="{{ route('client.requests.index', ['tab' => 'related', 'related_source' => $request->id, 'form' => 1]) }}">Fill Related Job</a>
                                    @elseif($request->related_request_id)
                                        <span class="helper-text">Related request submitted</span>
                                    @else
                                        <span class="helper-text">No action</span>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        @if($request->status === \App\Models\ClientRequest::STATUS_PENDING_CUSTOMER_REVIEW)
                            <tr class="feedback-row">
                                <td colspan="7">
                                    <div class="client-feedback-shell" id="feedback-{{ $request->id }}">
                                        <div class="client-feedback-head">
                                            <div>
                                                <span class="hero-kicker">Customer Review</span>
                                                <h4>Feedback Form for {{ $request->request_code }}</h4>
                                                <p>Jazakumullah khayran for Your Feedback. Your rating helps the team review service quality and improve future support.</p>
                                            </div>
                                            <div class="hero-badge-row">
                                                <span class="badge {{ $request->statusBadgeClass() }}">{{ $request->status }}</span>
                                                <span class="badge neutral">Technician: {{ $request->assignedTechnician?->name ?? '-' }}</span>
                                            </div>
                                        </div>

                                        <form method="POST" action="{{ route('client.requests.feedback', $request) }}" class="client-feedback-form">
                                            @csrf
                                            @method('PUT')

                                            <div class="feedback-section-grid">
                                                @foreach($feedbackSections as $sectionKey => $section)
                                                    <details class="feedback-section-card" {{ $loop->first ? 'open' : '' }}>
                                                        <summary>
                                                            <div>
                                                                <strong>{{ $section['title'] }}</strong>
                                                                <small>{{ count($section['questions']) }} question(s)</small>
                                                            </div>
                                                            <span class="feedback-summary-toggle">Open</span>
                                                        </summary>
                                                        <div class="feedback-question-stack">
                                                            @foreach($section['questions'] as $questionKey => $questionText)
                                                                <div class="feedback-question-card">
                                                                    <p>{{ $questionText }}</p>
                                                                    <div class="rating-grid premium-rating-grid">
                                                                        @foreach([1 => 'Strongly Disagree', 2 => 'Disagree', 3 => 'Neutral', 4 => 'Agree', 5 => 'Strongly Agree'] as $score => $label)
                                                                            <label class="rate-pill premium-rate-pill">
                                                                                <input type="radio" name="ratings[{{ $sectionKey }}][{{ $questionKey }}]" value="{{ $score }}" required>
                                                                                <span>{{ $label }}</span>
                                                                            </label>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </details>
                                                @endforeach
                                            </div>

                                            <div class="feedback-comment-card">
                                                <label>Additional Comments / Suggestions</label>
                                                <textarea name="additional_comments" placeholder="Share any extra feedback that can help improve the service."></textarea>
                                            </div>

                                            <div class="action-row feedback-submit-row">
                                                <button class="btn accent" type="submit">Submit Feedback</button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state-card">
                                    <strong>No request submitted.</strong>
                                    <p class="helper-text">Use the Add Request button above to create your first request.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($activeTab === 'related')
    <section class="panel premium-table-panel client-table-panel related-source-panel full-width-panel">
        <div class="premium-section-head">
            <div>
                <h3>Related Job Request Forms</h3>
                <p>These are parent jobs where the technician requested a follow-up related job. Submit from here only when you want the new request to be linked.</p>
            </div>
            <div class="table-head-badges">
                <span class="header-chip">Related Queue</span>
                <span class="header-chip muted">Separated from new requests</span>
            </div>
        </div>
        <div class="table-scroll-shell">
            <table class="table admin-command-table client-command-table">
                <thead>
                    <tr>
                        <th>Parent Job ID</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Technician</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($relatedSourceRequests as $parentJob)
                        <tr>
                            <td><strong>{{ $parentJob->request_code }}</strong></td>
                            <td>{{ $parentJob->requestType?->name ?? '-' }}</td>
                            <td>{{ $parentJob->location?->name ?? '-' }}</td>
                            <td><span class="badge {{ $parentJob->statusBadgeClass() }}">{{ $parentJob->status }}</span></td>
                            <td>{{ $parentJob->assignedTechnician?->name ?? '-' }}</td>
                            <td>
                                @if($parentJob->latest_related_submission)
                                    <div class="status-stack-cell">
                                        <span class="badge {{ $parentJob->latest_related_submission->statusBadgeClass() }}">{{ $parentJob->latest_related_submission->status }}</span>
                                        <small>Submitted as {{ $parentJob->latest_related_submission->request_code }}</small>
                                    </div>
                                @else
                                    <a class="btn small accent" href="{{ route('client.requests.index', ['tab' => 'related', 'related_source' => $parentJob->id, 'form' => 1]) }}">Fill Related Request</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state-card">
                                    <strong>No related job form is waiting.</strong>
                                    <p class="helper-text">New requests created from the New Request submenu will stay independent and will not be linked automatically.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    @endif

@endif

@if($showFormPanel)
<section class="panel request-form-panel client-form-premium full-width-panel show-panel" id="request-form-panel">
        <div class="client-form-head">
            <div>
                <span class="hero-kicker">{{ $isEditing ? 'Resubmit Existing Request' : ($isRelatedMode ? 'Create Related Request' : 'Create New Request') }}</span>
                <h3>{{ $isEditing ? 'Resubmit Request Form' : ($isRelatedMode ? 'Create Related Job Form' : 'Create New Request') }}</h3>
                <p>{{ $isRelatedMode ? 'Main setup details below are copied from the parent job. You only need to update urgency, new remark details, and new supporting files.' : 'Form fields below will change automatically based on the request type you choose.' }}</p>
            </div>
            <a class="btn small ghost" href="{{ route('client.requests.index', ['tab' => $activeTab]) }}">Back to List</a>
        </div>

        <form method="POST" action="{{ $isEditing ? route('client.requests.update', $editingRequest) : route('client.requests.store') }}" enctype="multipart/form-data" class="client-request-form">
            @csrf
            @if($isEditing)
                @method('PUT')
            @endif

            <div class="form-section-card">
                <div class="section-mini-head">
                    <div>
                        <h4>Requester Information</h4>
                        <span>Auto-filled from your profile.</span>
                    </div>
                </div>
                <div class="client-identity-grid">
                    <div class="meta-tile">
                        <span>Full Name</span>
                        <strong>{{ $user->name }}</strong>
                        <small>Managed from profile settings.</small>
                    </div>
                    <div class="meta-tile">
                        <span>Phone Number</span>
                        <strong>{{ $user->phone_number }}</strong>
                        <small>Update profile if you need to change it.</small>
                    </div>
                </div>
            </div>

            <div class="form-section-card">
                <div class="section-mini-head">
                    <div>
                        <h4>Request Setup</h4>
                        <span>Select where and what kind of support you need.</span>
                    </div>
                </div>
                @if($isRelatedMode)
                    <div class="premium-meta-grid client-meta-grid inherited-grid">
                        <div class="meta-tile"><span>Parent Job</span><strong>{{ $relatedSourceRequest->request_code }}</strong><small>This submission will be linked to the earlier job.</small></div>
                        <div class="meta-tile"><span>Request Type</span><strong>{{ $relatedSourceRequest->requestType?->name ?? '-' }}</strong><small>Copied from parent job.</small></div>
                        <div class="meta-tile"><span>Location</span><strong>{{ $relatedSourceRequest->location?->name ?? '-' }}</strong><small>Copied from parent job.</small></div>
                        @if($user->sub_role === \App\Models\User::CLIENT_HQ)
                            <div class="meta-tile"><span>Department</span><strong>{{ $relatedSourceRequest->department?->name ?? '-' }}</strong><small>Copied from parent job.</small></div>
                        @endif
                        <div class="meta-tile"><span>Task Title</span><strong>{{ $taskTitle ?: '-' }}</strong><small>Reference title from the earlier request.</small></div>
                    </div>
                    <input type="hidden" name="related_source_id" value="{{ $relatedSourceRequest->id }}">
                    <input type="hidden" name="related_request_id" value="{{ $relatedSourceRequest->id }}">
                    <input type="hidden" name="request_type_id" id="request-type-select" value="{{ $relatedSourceRequest->request_type_id }}">
                    <input type="hidden" name="location_id" value="{{ $relatedSourceRequest->location_id }}">
                    @if($user->sub_role === \App\Models\User::CLIENT_HQ)
                        <input type="hidden" name="department_id" value="{{ $relatedSourceRequest->department_id }}">
                    @endif
                @else
                    <div class="form-grid-two">
                        @if($user->sub_role === \App\Models\User::CLIENT_HQ)
                            <div>
                                <label>Department</label>
                                <select name="department_id" required>
                                    <option value="">Select department</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}" {{ old('department_id', $editingRequest?->department_id) == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div>
                            <label>Location</label>
                            <select name="location_id" required>
                                <option value="">Select location</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" {{ old('location_id', $editingRequest?->location_id) == $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="{{ $user->sub_role === \App\Models\User::CLIENT_HQ ? '' : 'full-span' }}">
                            <label>Type of Request</label>
                            <select name="request_type_id" id="request-type-select" required>
                                <option value="">Select request type</option>
                                @foreach($requestTypes as $type)
                                    <option value="{{ $type->id }}" {{ old('request_type_id', $editingRequest?->request_type_id) == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif
            </div>

            <div id="urgency-wrap" class="form-section-card" style="display:none;">
                <div class="section-mini-head">
                    <div>
                        <h4>Urgency Needed</h4>
                        <span>Select the urgency level that best matches your current request.</span>
                    </div>
                </div>
                <div class="urgency-grid">
                    <label class="urgency-card urgency-low">
                        <input type="radio" name="urgency_level" value="1" {{ old('urgency_level', $editingRequest?->urgency_level) == '1' ? 'checked' : '' }}>
                        <span class="urgency-title">1 - Low</span>
                        <small>Normal request timeline.</small>
                    </label>
                    <label class="urgency-card urgency-medium">
                        <input type="radio" name="urgency_level" value="2" {{ old('urgency_level', $editingRequest?->urgency_level) == '2' ? 'checked' : '' }}>
                        <span class="urgency-title">2 - Medium</span>
                        <small>Need faster attention.</small>
                    </label>
                    <label class="urgency-card urgency-high">
                        <input type="radio" name="urgency_level" value="3" {{ old('urgency_level', $editingRequest?->urgency_level) == '3' ? 'checked' : '' }}>
                        <span class="urgency-title">3 - High</span>
                        <small>Urgent action required.</small>
                    </label>
                </div>
            </div>

            <div id="attachment-wrap" class="form-section-card" style="display:none;">
                <div class="section-mini-head">
                    <div>
                        <h4>Supporting Files</h4>
                        <span>Upload any file type needed to explain the issue clearly.</span>
                    </div>
                </div>
                <input type="file" name="attachments[]" multiple>
                <p class="helper-text">Any file type is allowed, maximum 10MB per file.</p>
                @if($isEditing && !empty($editingRequest->attachments))
                    <ul class="attachment-list compact">
                        @foreach($editingRequest->attachments as $file)
                            <li><a href="{{ $fileUrl($file['path']) }}" target="_blank">{{ $file['original_name'] }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="form-section-card dynamic-question-shell">
                <div class="section-mini-head">
                    <div>
                        <h4>Request Questions</h4>
                        <span>Questions below will appear after you select the request type.</span>
                    </div>
                </div>
                <div id="dynamic-questions"></div>
            </div>

            <button class="btn primary block" type="submit">{{ $isEditing ? 'Resubmit Request' : ($isRelatedMode ? 'Submit Related Request' : 'Submit Request') }}</button>
        </form>
    </section>
@endif

<script>
const requestTypes = @json($requestTypes);
const selectedOldType = '{{ old('request_type_id', $editingRequest?->request_type_id ?? $relatedSourceRequest?->request_type_id) }}';
const oldAnswers = @json(old('answers', $editingRequest?->answers ?? []));
const relatedMode = @json($isRelatedMode);
const sourceAnswers = @json($relatedSourceRequest?->answers ?? []);
const select = document.getElementById('request-type-select');
const questionWrap = document.getElementById('dynamic-questions');
const urgencyWrap = document.getElementById('urgency-wrap');
const attachmentWrap = document.getElementById('attachment-wrap');
const formPanel = document.getElementById('request-form-panel');
const openBtn = document.getElementById('toggle-request-form');
const closeBtn = document.getElementById('close-request-form');
const requestLayout = document.querySelector('.client-request-layout');

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function nl2brSafe(value) {
    return escapeHtml(value).replace(/\r?\n/g, '<br>');
}

function renderClientQuestions() {
    const type = requestTypes.find(item => String(item.id) === select.value);
    questionWrap.innerHTML = '';

    if (!type) {
        urgencyWrap.style.display = 'none';
        attachmentWrap.style.display = 'none';
        return;
    }

    urgencyWrap.style.display = type.urgency_enabled ? 'block' : 'none';
    attachmentWrap.style.display = type.attachment_required ? 'block' : 'none';

    if (!type.urgency_enabled) {
        document.querySelectorAll('input[name="urgency_level"]').forEach(input => input.checked = false);
    }

    type.questions.forEach(question => {
        const box = document.createElement('div');
        box.className = 'question-card client-dynamic-question';

        let html = `<div class="client-question-title">${nl2brSafe(question.question_text)}${question.is_required ? ' *' : ''}</div>`;

        if (question.question_type === 'remark') {
            html += `<textarea name="answers[${question.id}]">${escapeHtml(oldAnswers[question.id] || '')}</textarea>`;
        } else if (relatedMode) {
            const sourceValue = sourceAnswers?.[question.id];
            if (question.question_type === 'radio') {
                html += `<div class="readonly-answer-box">${escapeHtml(sourceValue?.value || '-')}</div>`;
                if (sourceValue?.value) {
                    html += `<input type="hidden" name="answers[${question.id}][value]" value="${escapeHtml(sourceValue.value)}">`;
                }
                if (sourceValue?.other) {
                    html += `<input type="hidden" name="answers[${question.id}][other]" value="${escapeHtml(sourceValue.other)}">`;
                }
            } else if (question.question_type === 'date_range') {
                html += `<div class="readonly-answer-box">${escapeHtml(sourceValue?.start || '-')} → ${escapeHtml(sourceValue?.end || '-')}</div>`;
                if (sourceValue?.start) {
                    html += `<input type="hidden" name="answers[${question.id}][start]" value="${escapeHtml(sourceValue.start)}">`;
                }
                if (sourceValue?.end) {
                    html += `<input type="hidden" name="answers[${question.id}][end]" value="${escapeHtml(sourceValue.end)}">`;
                }
            } else {
                const collection = sourceValue || [];
                html += `<div class="readonly-answer-box">${collection.length ? collection.map(item => escapeHtml(item.value + (item.other ? ' - ' + item.other : ''))).join(', ') : '-'}</div>`;
                collection.forEach((item, index) => {
                    html += `<input type="hidden" name="answers[${question.id}][${index}][value]" value="${escapeHtml(item.value || '')}">`;
                    if (item.other) {
                        html += `<input type="hidden" name="answers[${question.id}][${index}][other]" value="${escapeHtml(item.other)}">`;
                    }
                });
            }
        } else if (question.question_type === 'radio') {
            html += '<div class="stack-list">';
            question.options.forEach(option => {
                const isOtherOption = !!option.allows_other_text;
                const checked = oldAnswers?.[question.id]?.value === option.option_text ? 'checked' : '';
                html += `<label class="option-block ${isOtherOption ? 'option-block-other' : ''}"><span class="option-choice"><input type="radio" name="answers[${question.id}][value]" value="${option.option_text}" ${checked}> <span>${option.option_text}</span></span>${isOtherOption ? `<input type="text" class="other-input" name="answers[${question.id}][other]" value="${escapeHtml(oldAnswers?.[question.id]?.other || '')}" placeholder="Please specify" ${checked ? '' : 'style="display:none;"'}>` : ''}</label>`;
            });
            html += '</div>';
        } else if (question.question_type === 'date_range') {
            html += `<div class="two-col-inline"><div><span>${question.start_label || 'Start Date'}</span><input type="date" name="answers[${question.id}][start]" value="${oldAnswers?.[question.id]?.start || ''}"></div><div><span>${question.end_label || 'End Date'}</span><input type="date" name="answers[${question.id}][end]" value="${oldAnswers?.[question.id]?.end || ''}"></div></div>`;
        } else {
            html += '<div class="stack-list">';
            const collection = oldAnswers?.[question.id] || [];
            question.options.forEach((option, index) => {
                const isChecked = collection.some(item => item.value === option.option_text) ? 'checked' : '';
                html += `<label class="option-block"><span class="option-choice"><input type="checkbox" name="answers[${question.id}][${index}][value]" value="${option.option_text}" ${isChecked}> <span>${option.option_text}</span></span></label>`;
            });
            const otherIndex = question.options.length;
            const otherRecord = collection.find(item => item.value === 'Others') || {};
            const otherChecked = otherRecord.value === 'Others' ? 'checked' : '';
            html += `<label class="option-block option-block-other"><span class="option-choice"><input type="checkbox" class="others-toggle" name="answers[${question.id}][${otherIndex}][value]" value="Others" ${otherChecked}> <span>Others</span></span><input type="text" class="other-input" name="answers[${question.id}][${otherIndex}][other]" value="${escapeHtml(otherRecord.other || '')}" placeholder="Please specify" ${otherChecked ? '' : 'style="display:none;"'}></label>`;
            html += '</div>';
        }

        box.innerHTML = html;
        questionWrap.appendChild(box);
    });

    bindOtherInputs();
}

function bindOtherInputs() {
    questionWrap.querySelectorAll('.option-block-other').forEach(block => {
        const toggle = block.querySelector('input[type="checkbox"], input[type="radio"]');
        const input = block.querySelector('.other-input');
        if (!toggle || !input) {
            return;
        }

        const update = () => {
            if (toggle.type === 'radio') {
                const group = questionWrap.querySelectorAll(`input[type="radio"][name="${toggle.name}"]`);
                let active = false;
                group.forEach(radio => {
                    if (radio === toggle && radio.checked) {
                        active = true;
                    }
                });
                input.style.display = active ? 'block' : 'none';
                if (!active) {
                    input.value = '';
                }
            } else {
                input.style.display = toggle.checked ? 'block' : 'block';
                if (!toggle.checked) {
                    input.style.display = 'none';
                    input.value = '';
                }
            }
        };

        if (toggle.type === 'radio') {
            questionWrap.querySelectorAll(`input[type="radio"][name="${toggle.name}"]`).forEach(radio => radio.addEventListener('change', update));
        } else {
            toggle.addEventListener('change', update);
        }

        update();
    });
}

function openFormPanel() {
    formPanel.classList.add('show-panel');
    if (requestLayout) requestLayout.classList.add('show-form');
    formPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function closeFormPanel() {
    formPanel.classList.remove('show-panel');
    if (requestLayout) requestLayout.classList.remove('show-form');
}

select.addEventListener('change', renderClientQuestions);
if (selectedOldType) {
    select.value = selectedOldType;
}
renderClientQuestions();

if (openBtn) {
    openBtn.addEventListener('click', openFormPanel);
}
if (closeBtn) {
    closeBtn.addEventListener('click', closeFormPanel);
}
if (formPanel.classList.contains('show-panel')) {
    if (requestLayout) requestLayout.classList.add('show-form');
}
if (relatedMode) {
    openFormPanel();
}
</script>
@endsection
