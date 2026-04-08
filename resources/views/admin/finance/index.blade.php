@extends('layouts.app', ['title' => 'Finance'])
@section('content')
<div class="page-header">
    <div>
        <h1>Finance</h1>
        <p> </p>
    </div>
</div>

<div class="stats-grid four-up">
    <div class="stat-card"><span>Total Queue</span><strong>{{ $requests->count() }}</strong></div>
    <div class="stat-card"><span>Pending Finance</span><strong>{{ $pendingCount }}</strong></div>
    <div class="stat-card"><span>Completed</span><strong>{{ $completedCount }}</strong></div>
    <div class="stat-card"><span>Print Ready</span><strong>{{ $completedCount }}</strong></div>
</div>

<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>{{ $isViewer ? 'Finance Monitoring' : 'Finance Queue' }}</h3></div>
    <table class="table">
        <thead>
            <tr>
                <th>Job ID</th>
                <th>Client</th>
                <th>Role</th>
                <th>Request Type</th>
                <th>Technician</th>
                <th>Evidence Ready</th>
                <th>Finance Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $item)
                <tr>
                    <td>{{ $item->request_code }}</td>
                    <td>{{ $item->full_name }}</td>
                    <td>{{ $item->user->roleLabel() }}</td>
                    <td>{{ $item->requestType->name }}</td>
                    <td>{{ $item->assignedTechnician?->name ?? '-' }}</td>
                    <td>
                        @if($item->technician_completed_at)
                            <span class="badge warning">Ready {{ $item->technician_completed_at->diffForHumans() }}</span>
                        @else
                            <span class="badge neutral">-</span>
                        @endif
                    </td>
                    <td>
                        @if($item->finance_completed_at)
                            <span class="badge success">Uploaded</span>
                        @else
                            <span class="badge warning">Pending Upload</span>
                        @endif
                    </td>
                    <td>
                        @if($isViewer)
                            @if($item->finance_completed_at)
                                <a class="btn small accent" href="{{ route('admin.finance.show', $item) }}">View Finance Form</a>
                            @else
                                <span class="helper-text">Not uploaded yet</span>
                            @endif
                        @else
                            <a class="btn small accent" href="{{ route('admin.finance.show', $item) }}">Open</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">{{ $isViewer ? 'No finance records found yet.' : 'No finance evidence is ready yet.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
</section>
@endsection
