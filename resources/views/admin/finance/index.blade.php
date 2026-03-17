@extends('layouts.app', ['title' => 'Finance'])
@section('content')
<div class="page-header">
    <div>
        <h1>Finance</h1>
        <p>Invoice uploads from technician will appear here for finance processing.</p>
    </div>
</div>

<div class="stats-grid four-up">
    <div class="stat-card"><span>Total Queue</span><strong>{{ $requests->count() }}</strong></div>
    <div class="stat-card"><span>Pending Finance</span><strong>{{ $pendingCount }}</strong></div>
    <div class="stat-card"><span>Completed</span><strong>{{ $completedCount }}</strong></div>
    <div class="stat-card"><span>Print Ready</span><strong>{{ $requests->whereNotNull('finance_completed_at')->count() }}</strong></div>
</div>

<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Finance Queue</h3></div>
    <table class="table">
        <thead>
            <tr>
                <th>Job ID</th>
                <th>Client</th>
                <th>Request Type</th>
                <th>Technician</th>
                <th>Invoice</th>
                <th>Finance Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $item)
                <tr>
                    <td>{{ $item->request_code }}</td>
                    <td>{{ $item->full_name }}</td>
                    <td>{{ $item->requestType->name }}</td>
                    <td>{{ $item->assignedTechnician?->name ?? '-' }}</td>
                    <td>
                        @if($item->invoice_uploaded_at)
                            <span class="badge warning">Uploaded {{ $item->invoice_uploaded_at->diffForHumans() }}</span>
                        @else
                            <span class="badge neutral">-</span>
                        @endif
                    </td>
                    <td><span class="badge {{ $item->adminWorkflowBadgeClass() }}">{{ $item->adminWorkflowLabel() }}</span></td>
                    <td><a class="btn small accent" href="{{ route('admin.finance.show', $item) }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="7">No invoice has been uploaded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>
@endsection
