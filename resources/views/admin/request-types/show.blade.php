@extends('layouts.app', ['title' => 'View Request Type'])
@section('content')
<section class="panel">
    <div class="page-header">
        <div>
            <h1>{{ $requestType->name }}</h1>
            <p>{{ $requestType->roleScopeLabel() }} · Urgency {{ $requestType->urgency_enabled ? 'Enabled' : 'Disabled' }} · File Upload {{ $requestType->attachment_required ? 'Required' : 'Optional' }}</p>
        </div>
        <a class="btn ghost" href="{{ route('admin.request-types.index') }}">Back</a>
    </div>

    <div class="notice-list">
        @foreach($requestType->questions as $question)
            <div class="notice-item">
                <strong>{!! nl2br(e($loop->iteration . '. ' . $question->question_text)) !!}</strong>
                <div class="helper-text">{{ $question->typeLabel() }}{{ $question->is_required ? ' · Required' : '' }}</div>
                @if($question->question_type === 'date_range')
                    <div>{{ $question->start_label ?: 'Start Date' }} / {{ $question->end_label ?: 'End Date' }}</div>
                @endif
                @if($question->options->count())
                    <ul>
                        @foreach($question->options as $option)
                            <li>{{ $option->option_text }}{{ $option->allows_other_text ? ' (Others enabled)' : '' }}</li>
                        @endforeach
                        @if($question->question_type === 'checkbox')
                            <li>Others (auto included for client)</li>
                        @endif
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
</section>
@endsection
