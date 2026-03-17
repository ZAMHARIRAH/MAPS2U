@extends('layouts.app', ['title' => ($mode === 'create' ? 'Add' : 'Edit') . ' Location'])
@section('content')
<div class="auth-wrapper"><div class="auth-card wide"><h1>{{ $mode === 'create' ? 'Add' : 'Edit' }} {{ $type === 'hq' ? 'HQ Location' : 'Branch' }}</h1>
<form method="POST" action="{{ $mode === 'create' ? route('admin.locations.store', $type) : route('admin.locations.update', $location) }}">@csrf @if($mode==='edit') @method('PUT') @endif
@if($mode==='edit')<input type="hidden" name="type" value="{{ old('type', $location->type) }}">@endif
<label>Name</label><input type="text" name="name" value="{{ old('name', $location->name) }}" required>
<label>Address</label><input type="text" name="address" value="{{ old('address', $location->address) }}">
<label class="inline-check"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $location->exists ? $location->is_active : true) ? 'checked' : '' }}> Active</label>
<div class="action-row" style="margin-top:18px;"><button class="btn primary" type="submit">Save Changes</button><a class="btn ghost" href="{{ route('admin.locations.index', $type) }}">Discard</a></div>
</form></div></div>
@endsection
