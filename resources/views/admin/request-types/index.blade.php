@extends('layouts.app', ['title' => 'Request Types'])
@section('content')
<div class="page-header"><div><h1>Request Types</h1><p> </p></div><a class="btn primary" href="{{ route('admin.request-types.create') }}">Add Request Type</a></div>
<section class="panel">
    <table class="table">
        <thead>
            <tr>
                <th>Request ID</th>
                <th>Type of Request</th>
                <th>Questions</th>
                <th>Role</th>
                <th>Urgency</th>
                <th>File Upload</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requestTypes as $requestType)
                <tr>
                    <td>RT{{ str_pad($requestType->id, 4, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $requestType->name }}</td>
                    <td>
                        <button type="button" class="btn small ghost" onclick="document.getElementById('q-{{ $requestType->id }}').showModal()">List of Questions</button>
                        <dialog id="q-{{ $requestType->id }}" class="modal-card">
                            <h3>{{ $requestType->name }}</h3>
                            <ol>
                                @foreach($requestType->questions as $question)
                                    <li>{{ $question->question_text }} <span class="helper-text">({{ $question->typeLabel() }})</span></li>
                                @endforeach
                            </ol>
                            <form method="dialog"><button class="btn ghost">Close</button></form>
                        </dialog>
                    </td>
                    <td>{{ $requestType->roleScopeLabel() }}</td>
                    <td><span class="badge {{ $requestType->urgency_enabled ? 'warning' : 'neutral' }}">{{ $requestType->urgency_enabled ? 'Enabled' : 'Off' }}</span></td>
                    <td><span class="badge {{ $requestType->attachment_required ? 'accented' : 'neutral' }}">{{ $requestType->attachment_required ? 'Required' : 'Optional' }}</span></td>
                    <td class="action-row">
                        <a href="{{ route('admin.request-types.show', $requestType) }}">View</a>
                        <a href="{{ route('admin.request-types.edit', $requestType) }}">Edit</a>
                        <form method="POST" action="{{ route('admin.request-types.destroy', $requestType) }}" data-delete-confirm="Delete this request type?">
                            @csrf @method('DELETE')
                            <button class="btn small danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No request type found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="pagination-wrap">{{ $requestTypes->links() }}</div>
</section>
@endsection
