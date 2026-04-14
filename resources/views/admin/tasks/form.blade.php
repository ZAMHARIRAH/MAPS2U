@extends('layouts.app', ['title' => ($mode === 'create' ? 'Create' : 'Edit') . ' Task'])
@section('content')
<div class="page-header"><div><h1>{{ $mode === 'create' ? 'Create' : 'Edit' }} Task</h1><p>Use this list for request forms and branch statistics reports.</p></div><a class="btn ghost" href="{{ route('admin.tasks.index') }}">Back</a></div>
<section class="panel" style="max-width:760px;">
    <form method="POST" action="{{ $mode === 'create' ? route('admin.tasks.store') : route('admin.tasks.update', $task) }}" class="details-grid">
        @csrf
        @if($mode === 'edit') @method('PUT') @endif
        <div class="full">
            <span>Task Title</span>
            <input type="text" name="title" value="{{ old('title', $task->title) }}" placeholder="Example: Aircond" required>
            @error('title')<small class="helper-text" style="color:#dc2626;">{{ $message }}</small>@enderror
        </div>
        <div class="full">
            <label class="inline-check"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $task->exists ? $task->is_active : true) ? 'checked' : '' }}> Active</label>
        </div>
        <div class="action-row full">
            <button class="btn primary" type="submit">Save</button>
            <a class="btn ghost" href="{{ route('admin.tasks.index') }}">Cancel</a>
        </div>
    </form>
</section>
@endsection
