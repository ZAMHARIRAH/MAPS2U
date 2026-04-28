@extends('layouts.app', ['title' => 'Announcements'])
@section('content')
<div class="page-header">
    <div>
        <h1>Announcements</h1>
        <p> </p>
    </div>
    <a class="btn primary" href="{{ route('admin.announcements.create') }}">Add Announcement</a>
</div>

<section class="panel">
    <table class="table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Content</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Action</th>
                <th>Delete</th>
            </tr>
        </thead>
        <tbody>
            @forelse($announcements as $announcement)
                <tr>
                    <td>{{ $announcement->title }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($announcement->content, 120) }}</td>
                    <td><span class="badge {{ $announcement->priorityBadgeClass() }}">{{ $announcement->priorityLabel() }}</span></td>
                    <td>
                        <form method="POST" action="{{ route('admin.announcements.toggle', $announcement) }}" class="switch-form-inline">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="is_active" value="0">
                            <label class="status-switch" style="transform:scale(.82);transform-origin:left center;" aria-label="Toggle active status">
                                <input type="checkbox" name="is_active" value="1" {{ $announcement->is_active ? 'checked' : '' }} onchange="this.form.submit()">
                                <span class="status-switch-slider"></span>
                            </label>
                            <span class="switch-state-label" style="font-size:12px;">{{ $announcement->is_active ? 'Active' : 'Inactive' }}</span>
                        </form>
                    </td>
                    <td><a class="btn small ghost" href="{{ route('admin.announcements.edit', $announcement) }}">Edit</a></td>
                    <td><form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" onsubmit="return confirm('Delete this announcement?')">@csrf @method('DELETE')<button class="btn small danger" type="submit">Delete</button></form></td>
                </tr>
            @empty
                <tr><td colspan="6">No announcement created yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>
@endsection
