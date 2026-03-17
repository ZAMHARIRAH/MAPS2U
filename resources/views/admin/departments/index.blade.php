@extends('layouts.app', ['title' => 'Departments'])
@section('content')
<div class="page-header"><div><h1>Departments</h1><p>Create and manage department list for HQ Staff request form.</p></div><a class="btn primary" href="{{ route('admin.departments.create') }}">Add Department</a></div>
<section class="panel">
    <table class="table">
        <thead><tr><th>Name</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
            @forelse($departments as $department)
                <tr>
                    <td>{{ $department->name }}</td>
                    <td><span class="badge {{ $department->is_active ? 'success' : 'neutral' }}">{{ $department->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="actions-inline"><a href="{{ route('admin.departments.edit', $department) }}">Edit</a><form method="POST" action="{{ route('admin.departments.destroy', $department) }}" onsubmit="return confirm('Delete this department?')">@csrf @method('DELETE')<button class="link-danger" type="submit">Delete</button></form></td>
                </tr>
            @empty
                <tr><td colspan="3">No departments created yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>
@endsection
