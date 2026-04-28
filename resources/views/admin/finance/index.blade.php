@extends('layouts.app', ['title' => 'Finance'])
@section('content')
<div class="page-header">
    <div>
        <h1>{{ $pageTitle ?? 'Finance' }}</h1>
        <p>{{ $pageIntro ?? ($isViewer ? 'Viewer can monitor all HQ Staff and Kindergarten finance submissions in view-only mode.' : 'Signed approved quotation and payment receipt history from technician jobs will appear here for finance processing.') }}</p>
    </div>
</div>

<div class="stats-grid four-up">
    <div class="stat-card"><span>Total Queue</span><strong>{{ $requests->count() }}</strong></div>
    <div class="stat-card"><span>Pending Finance</span><strong>{{ $pendingCount }}</strong></div>
    <div class="stat-card"><span>Completed</span><strong>{{ $completedCount }}</strong></div>
    <div class="stat-card"><span>Print Ready</span><strong>{{ $completedCount }}</strong></div>
</div>

<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>{{ ($mapsScope ?? false) ? 'Pending Finance MAPS Form' : 'Pending Finance Form' }}</h3></div>
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
            @forelse(($pendingRequests ?? $requests) as $item)
                <tr>
                    <td>{{ $item->request_code }}</td>
                    <td>{{ $item->full_name }}</td>
                    <td>{{ $item->user?->roleLabel() ?? '-' }}</td>
                    <td>{{ $item->requestType?->name ?? '-' }}</td>
                    <td>{{ $item->assignedTechnician?->name ?? '-' }}</td>
                    <td>
                        @if($item->technician_completed_at)
                            <span class="badge warning">Ready {{ $item->technician_completed_at->diffForHumans() }}</span>
                        @else
                            <span class="badge neutral">-</span>
                        @endif
                    </td>
                    <td><span class="badge warning">Pending Upload</span></td>
                    <td>
                        @if($isViewer)
                            <span class="helper-text">Not uploaded yet</span>
                        @else
                            <a class="btn small accent" href="{{ ($mapsScope ?? false) ? route('admin.maps.finance.show', $item) : route('admin.finance.show', $item) }}">Open</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No pending finance form.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>

<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>{{ ($mapsScope ?? false) ? 'Completed Finance MAPS Form' : 'Completed Finance Form' }}</h3></div>
    <table class="table">
        <thead>
            <tr>
                <th>Job ID</th>
                <th>Client</th>
                <th>Role</th>
                <th>Request Type</th>
                <th>Technician</th>
                <th>Completed At</th>
                <th>Approved Amount</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($completedRequests ?? collect()) as $item)
                <tr>
                    <td>{{ $item->request_code }}</td>
                    <td>{{ $item->full_name }}</td>
                    <td>{{ $item->user?->roleLabel() ?? '-' }}</td>
                    <td>{{ $item->requestType?->name ?? '-' }}</td>
                    <td>{{ $item->assignedTechnician?->name ?? '-' }}</td>
                    <td>{{ $item->finance_completed_at?->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') ?? '-' }}</td>
                    <td>RM {{ number_format((float) (data_get($item->finance_form, 'approved_amount') ?: $item->approvedCostAmount()), 2) }}</td>
                    <td><a class="btn small ghost" href="{{ ($mapsScope ?? false) ? route('admin.maps.finance.show', $item) : route('admin.finance.show', $item) }}">View Finance Form</a></td>
                </tr>
            @empty
                <tr><td colspan="8">No completed finance form yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>
@endsection
