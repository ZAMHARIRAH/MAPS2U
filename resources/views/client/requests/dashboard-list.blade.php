@extends('layouts.app', ['title' => 'Dashboard List Request'])
@section('content')
<section class="panel" style="margin-bottom:20px;"><form method="GET" class="report-filter-grid no-print"><div class="field-block"><span>Branch</span><select name="location_id"><option value="">All Branches</option>@foreach($locations as $location)<option value="{{ $location->id }}" {{ ($filters['location_id'] ?? '') == $location->id ? 'selected' : '' }}>{{ $location->name }}</option>@endforeach</select></div><div class="field-block"><span>Status</span><select name="status"><option value="">All Status</option>@foreach($statusOptions as $status)<option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ $status }}</option>@endforeach</select></div><div class="field-block"><span>Date From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div><div class="field-block"><span>Date To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div><div class="action-row"><button class="btn primary" type="submit">Filter</button><a class="btn ghost" href="{{ route('client.dashboard-list.index') }}">Reset</a></div></form></section>
<div class="page-header"><div><h1>Dashboard List Request</h1><p>Monitor current request list and workflow progress for kindergarten and SSU submissions.</p></div></div>
<section class="panel premium-table-panel client-table-panel">
    <div class="table-scroll-shell">
        <table class="table admin-command-table client-command-table">
            <thead><tr><th>Job ID</th><th>Client</th><th>Role</th><th>Type</th><th>Request For</th><th>Department</th><th>Urgency</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td><strong>{{ $item->request_code }}</strong></td>
                        <td><div class="table-primary-cell">{{ $item->full_name }}</div><div class="helper-text">{{ $item->phone_number }}</div></td>
                        <td>{{ $item->user?->roleLabel() ?? (data_get($item->inspect_data, 'legacy_client_role') ? \Illuminate\Support\Str::headline(str_replace('_', ' ', data_get($item->inspect_data, 'legacy_client_role'))) : '-') }}</td>
                        <td>{{ $item->requestType?->name ?? '-' }}</td>
                        <td>{{ $item->location?->name ?? '-' }}</td>
                        <td>{{ $item->department?->name ?? '-' }}</td>
                        <td><span class="badge {{ $item->urgencyBadgeClass() }}">{{ $item->urgencyLabel() }}</span></td>
                        <td><span class="badge {{ $item->adminWorkflowBadgeClass() }}">{{ $item->adminWorkflowLabel() }}</span></td>
                        <td><a class="btn small ghost" href="{{ route('client.dashboard-list.show', $item) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9">No request found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px;">{{ $items->links() }}</div>
</section>
@endsection
