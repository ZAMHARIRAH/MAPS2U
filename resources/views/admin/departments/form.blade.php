@extends('layouts.app', ['title' => ($mode === 'create' ? 'Add' : 'Edit') . ' Department'])
@section('content')
<section class="panel form-shell small-form-shell">
    <div class="page-header"><div><h1>{{ $mode === 'create' ? 'Add' : 'Edit' }} Department</h1><p>This list will be shown to HQ Staff in the request form.</p></div><a class="btn ghost" href="{{ route('admin.departments.index') }}">Back</a></div>
    <form method="POST" action="{{ $mode === 'create' ? route('admin.departments.store') : route('admin.departments.update', $department) }}">
        @csrf
        @if($mode === 'edit') @method('PUT') @endif
        <label>Department Name</label>
        <input type="text" name="name" value="{{ old('name', $department->name) }}" required>
        <label class="inline-check" style="margin-top:12px;"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $department->exists ? $department->is_active : true) ? 'checked' : '' }}> Active</label>
        <div class="action-row" style="margin-top:16px;"><button class="btn primary" type="submit">Save Changes</button><a class="btn ghost" href="{{ route('admin.departments.index') }}">Discard</a></div>
    </form>
</section>
@endsection
