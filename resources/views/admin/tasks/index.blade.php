@extends('layouts.app', ['title' => 'Manage Task'])
@section('content')
<div class="page-header"><div><h1>Manage Task</h1><p> </p></div><a class="btn primary" href="{{ route('admin.tasks.create') }}">Add Task</a></div>
<section class="panel">
    <table class="table">
        <thead><tr><th>Task Title</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
            @forelse($tasks as $task)
                <tr>
                    <td>{{ $task->title }}</td>
                    <td><span class="badge {{ $task->is_active ? 'success' : 'neutral' }}">{{ $task->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="actions-inline"><a class="btn small" style="background:#1d8a52;color:#fff;" href="{{ route('admin.tasks.edit', $task) }}">Edit</a><form method="POST" action="{{ route('admin.tasks.destroy', $task) }}" onsubmit="return confirm('Delete this task title?')">@csrf @method('DELETE')<button class="btn small danger" type="submit">Delete</button></form></td>
                </tr>
            @empty
                <tr><td colspan="3">No task title created yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="pagination-wrap">{{ $tasks->links() }}</div>
</section>
@endsection
