@extends('layouts.app', ['title' => ($mode === 'create' ? 'Create' : 'Edit') . ' Request Type'])

@section('content')
<div class="panel builder-shell">
    <div class="page-header builder-header">
        <div>
            <h1>{{ $mode === 'create' ? 'Create' : 'Edit' }} Request Type</h1>
            <p>Build request questions in a cleaner step-by-step layout for MAPS2U admins.</p>
        </div>
        <a class="btn ghost" href="{{ route('admin.request-types.index') }}">Back</a>
    </div>

    <form method="POST" action="{{ $mode === 'create' ? route('admin.request-types.store') : route('admin.request-types.update', $requestType) }}" id="request-type-form">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        <div class="builder-intro-grid">
            <div class="builder-intro-card">
                <span class="builder-step">Step 1</span>
                <h3>Request Overview</h3>
                <p>Set the request title and choose which client role can access this request type.</p>
            </div>
            <div class="builder-intro-card">
                <span class="builder-step">Step 2</span>
                <h3>Extra Controls</h3>
                <p>Turn on urgency or file upload requirements so the request form behaves the way you need.</p>
            </div>
            <div class="builder-intro-card">
                <span class="builder-step">Step 3</span>
                <h3>Questions Builder</h3>
                <p>Add as many questions as needed. Use paragraph text for long instructions or detailed prompts.</p>
            </div>
        </div>

        <div class="builder-config-grid">
            <section class="builder-section builder-main-section">
                <div class="builder-section-head">
                    <div>
                        <span class="builder-step">Request Setup</span>
                        <h3>Request Type Details</h3>
                    </div>
                </div>

                <div class="details-grid builder-details-grid">
                    <div class="full builder-field-card">
                        <span>Type of Request</span>
                        <input type="text" name="name" value="{{ old('name', $requestType->name) }}" placeholder="Example: Laptop Borrowing" required>
                    </div>

                    <div class="full builder-field-card">
                        <span>Open For</span>
                        <div class="builder-role-grid">
                            <label class="role-choice-card">
                                <input type="radio" name="role_scope" value="hq_staff" {{ old('role_scope', $requestType->role_scope ?: 'both') === 'hq_staff' ? 'checked' : '' }}>
                                <strong>HQ Staff</strong>
                                <small>Visible only to HQ Staff client accounts.</small>
                            </label>
                            <label class="role-choice-card">
                                <input type="radio" name="role_scope" value="teacher_principal" {{ old('role_scope', $requestType->role_scope) === 'teacher_principal' ? 'checked' : '' }}>
                                <strong>Teacher / Principal</strong>
                                <small>Visible only to teacher and principal client accounts.</small>
                            </label>
                            <label class="role-choice-card">
                                <input type="radio" name="role_scope" value="both" {{ old('role_scope', $requestType->role_scope ?: 'both') === 'both' ? 'checked' : '' }}>
                                <strong>Both</strong>
                                <small>Visible to all client roles.</small>
                            </label>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="builder-side-panel">
                <section class="builder-section builder-toggle-panel">
                    <div class="builder-section-head compact">
                        <div>
                            <span class="builder-step">Options</span>
                            <h3>Form Controls</h3>
                        </div>
                    </div>

                    <label class="builder-switch-card">
                        <span>
                            <strong>Enable Urgency Needed</strong>
                            <small>Client will choose urgency level 1, 2, or 3 when submitting this request.</small>
                        </span>
                        <input type="checkbox" name="urgency_enabled" value="1" {{ old('urgency_enabled', $requestType->urgency_enabled) ? 'checked' : '' }}>
                    </label>

                    <label class="builder-switch-card">
                        <span>
                            <strong>Require File Upload</strong>
                            <small>Client must upload one or more files. Any file type is allowed.</small>
                        </span>
                        <input type="checkbox" name="attachment_required" value="1" {{ old('attachment_required', $requestType->attachment_required) ? 'checked' : '' }}>
                    </label>

                    <label class="builder-switch-card">
                        <span>
                            <strong>Active</strong>
                            <small>Keep this request type available in the client dashboard.</small>
                        </span>
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $requestType->exists ? $requestType->is_active : true) ? 'checked' : '' }}>
                    </label>
                </section>
            </aside>
        </div>

        <section class="builder-section question-builder-panel">
            <div class="builder-section-head">
                <div>
                    <span class="builder-step">Question Builder</span>
                    <h3>Questions</h3>
                    <p class="helper-text">Create structured questions for the selected request type.</p>
                </div>
                <button class="btn secondary small" type="button" id="add-question-btn">Add Question</button>
            </div>
            <div id="question-builder"></div>
        </section>

        <div class="action-row" style="margin-top:18px;">
            <button class="btn primary" type="submit">Save Changes</button>
            <a class="btn ghost" href="{{ route('admin.request-types.index') }}">Discard</a>
        </div>
    </form>
</div>

@php
    $existingQuestionsData = old('questions');

    if ($existingQuestionsData === null) {
        if ($requestType->exists) {
            $existingQuestionsData = $requestType->questions->map(function ($q) {
                return [
                    'question_text' => $q->question_text,
                    'question_type' => $q->question_type,
                    'is_required' => (bool) $q->is_required,
                    'start_label' => $q->start_label,
                    'end_label' => $q->end_label,
                    'options' => $q->options->map(function ($o) {
                        return [
                            'option_text' => $o->option_text,
                            'allows_other_text' => (bool) $o->allows_other_text,
                        ];
                    })->values()->all(),
                ];
            })->values()->all();
        } else {
            $existingQuestionsData = [[
                'question_text' => '',
                'question_type' => 'remark',
                'is_required' => true,
                'start_label' => '',
                'end_label' => '',
                'options' => [],
            ]];
        }
    }
@endphp

<script>
const existingQuestions = @json($existingQuestionsData ?? []);

const builder = document.getElementById('question-builder');
const addBtn = document.getElementById('add-question-btn');

function optionHtml(qIndex, oIndex, option = { option_text: '', allows_other_text: false }, includeOthersToggle = true) {
    const othersToggle = includeOthersToggle
        ? `<label class="inline-check compact"><input type="checkbox" name="questions[${qIndex}][options][${oIndex}][allows_other_text]" value="1" ${option.allows_other_text ? 'checked' : ''}> Others</label>`
        : '';

    return `
        <div class="option-row builder-option-row">
            <input type="text" name="questions[${qIndex}][options][${oIndex}][option_text]" value="${option.option_text || ''}" placeholder="Option answer">
            ${othersToggle}
            <button class="btn small danger remove-option-btn" type="button">Remove</button>
        </div>
    `;
}

function renderExtras(card, type, index, question = {}) {
    const extra = card.querySelector('.question-extra');

    if (type === 'radio' || type === 'checkbox') {
        const options = (question.options && question.options.length)
            ? question.options
            : [{ option_text: '', allows_other_text: false }];

        const includeOthersToggle = type === 'radio';
        const helper = type === 'checkbox'
            ? '<p class="helper-text">Checkbox questions automatically include an Others option for clients to tick and fill in themselves.</p>'
            : '<p class="helper-text">For radio button questions, you can mark one option as Others.</p>';

        extra.innerHTML = `
            <div class="question-extra-card">
                ${helper}
                <div class="option-list">${options.map((item, idx) => optionHtml(index, idx, item, includeOthersToggle)).join('')}</div>
                <button class="btn small ghost add-option-btn" type="button">Add Option</button>
            </div>
        `;
    } else if (type === 'date_range') {
        extra.innerHTML = `
            <div class="question-extra-card">
                <p class="helper-text">Set custom labels that will appear above the date range calendars.</p>
                <div class="two-col-inline builder-date-grid">
                    <input type="text" name="questions[${index}][start_label]" value="${question.start_label || ''}" placeholder="Start calendar title">
                    <input type="text" name="questions[${index}][end_label]" value="${question.end_label || ''}" placeholder="End calendar title">
                </div>
            </div>
        `;
    } else {
        extra.innerHTML = `<div class="question-extra-card"><p class="helper-text">Client will type their own remark or description in a text box.</p></div>`;
    }
}

function createQuestionCard(index, question = {}) {
    const card = document.createElement('div');
    card.className = 'question-card builder-question-card';
    card.dataset.index = index;
    card.innerHTML = `
        <div class="builder-question-top">
            <div>
                <span class="builder-question-number">Question ${index + 1}</span>
                <h4>Request Question</h4>
            </div>
            <button class="btn small danger remove-question-btn" type="button">Remove</button>
        </div>

        <div class="builder-question-grid">
            <div class="builder-field-wrap full">
                <label>Question text</label>
                <textarea name="questions[${index}][question_text]" rows="4" required placeholder="Type the question here. Press Enter for a new line.">${question.question_text || ''}</textarea>
            </div>

            <div class="builder-field-wrap">
                <label>Answer type</label>
                <select name="questions[${index}][question_type]" class="question-type">
                    <option value="remark">Remark</option>
                    <option value="radio">Radio Button</option>
                    <option value="date_range">Date Range</option>
                    <option value="checkbox">Checkbox</option>
                </select>
            </div>

            <div class="builder-field-wrap builder-required-wrap">
                <label>Requirement</label>
                <label class="inline-check compact builder-required-pill">
                    <input type="checkbox" name="questions[${index}][is_required]" value="1" ${question.is_required !== false ? 'checked' : ''}>
                    Required
                </label>
            </div>
        </div>

        <div class="question-extra"></div>
    `;

    const typeSelect = card.querySelector('.question-type');
    typeSelect.value = question.question_type || 'remark';

    typeSelect.addEventListener('change', function (event) {
        renderExtras(card, event.target.value, index);
    });

    card.addEventListener('click', function (event) {
        if (event.target.classList.contains('remove-question-btn')) {
            card.remove();
            rebuildQuestions();
        }

        if (event.target.classList.contains('add-option-btn')) {
            const wrap = card.querySelector('.option-list');
            const optionIndex = wrap.children.length;
            const includeOthersToggle = (card.querySelector('.question-type')?.value || 'remark') === 'radio';
            wrap.insertAdjacentHTML('beforeend', optionHtml(index, optionIndex, { option_text: '', allows_other_text: false }, includeOthersToggle));
        }

        if (event.target.classList.contains('remove-option-btn')) {
            event.target.closest('.option-row').remove();
        }
    });

    renderExtras(card, question.question_type || 'remark', index, question);
    return card;
}

function captureQuestions() {
    return [...builder.querySelectorAll('.question-card')].map((card) => ({
        question_text: card.querySelector('textarea[name*="[question_text]"]')?.value || '',
        question_type: card.querySelector('.question-type')?.value || 'remark',
        is_required: card.querySelector('input[name*="[is_required]"]')?.checked || false,
        start_label: card.querySelector('input[name*="[start_label]"]')?.value || '',
        end_label: card.querySelector('input[name*="[end_label]"]')?.value || '',
        options: [...card.querySelectorAll('.option-row')].map((row) => ({
            option_text: row.querySelector('input[type="text"]')?.value || '',
            allows_other_text: row.querySelector('input[type="checkbox"]')?.checked || false,
        })),
    }));
}

function rebuildQuestions() {
    const snapshot = captureQuestions();
    builder.innerHTML = '';
    snapshot.forEach((question, index) => builder.appendChild(createQuestionCard(index, question)));
}

function addQuestion(question = { question_text: '', question_type: 'remark', is_required: true, options: [] }) {
    const index = builder.querySelectorAll('.question-card').length;
    builder.appendChild(createQuestionCard(index, question));
}

if (addBtn) {
    addBtn.addEventListener('click', function () {
        addQuestion();
    });
}

if (existingQuestions.length) {
    existingQuestions.forEach((question) => addQuestion(question));
} else {
    addQuestion();
}
</script>
@endsection
