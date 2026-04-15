@extends('layouts.app', ['title' => ($mode === 'create' ? 'Add' : 'Edit') . ' Announcement'])
@section('content')
<section class="panel form-shell small-form-shell">
    <div class="page-header">
        <div>
            <h1>{{ $mode === 'create' ? 'Add' : 'Edit' }} Announcement</h1>
            <p> </p>
        </div>
        <a class="btn ghost" href="{{ route('admin.announcements.index') }}">Back</a>
    </div>

    <form method="POST" action="{{ $mode === 'create' ? route('admin.announcements.store') : route('admin.announcements.update', $announcement) }}">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        <label>Title</label>
        <input type="text" name="title" value="{{ old('title', $announcement->title) }}" required>

        <label>Content</label>
        <textarea name="content" required>{{ old('content', $announcement->content) }}</textarea>

        <label>Priority</label>
        <select name="priority" required>
            <option value="high" {{ old('priority', $announcement->priority) === 'high' ? 'selected' : '' }}>High</option>
            <option value="medium" {{ old('priority', $announcement->priority) === 'medium' ? 'selected' : '' }}>Medium</option>
            <option value="low" {{ old('priority', $announcement->priority) === 'low' ? 'selected' : '' }}>Low</option>
        </select>

        <label class="inline-check" style="margin-top:12px;">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $announcement->exists ? $announcement->is_active : true) ? 'checked' : '' }}>
            Active
        </label>

        <div class="action-row" style="margin-top:16px;">
            <button class="btn primary" type="submit">Save Changes</button>
            <a class="btn ghost" href="{{ route('admin.announcements.index') }}">Discard</a>
        </div>
    </form>
</section>
@endsection
