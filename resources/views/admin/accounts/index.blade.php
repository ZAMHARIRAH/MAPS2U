@extends('layouts.app', ['title' => 'View Accounts'])
@section('content')
<div class="page-header"><div><h1>View Accounts</h1><p>{{ $admin->isViewer() ? 'Viewer can monitor HQ Staff and Kindergarten accounts.' : $admin->roleLabel() . ' handles ' . implode(' & ', array_map(fn($item) => ucwords(str_replace('_', ' ', $item)), $admin->handledClientRoles())) . '.' }}</p></div></div>
<div style="display:grid;gap:22px;">
    <div class="panel" style="margin:0;">
        <h3>Client Accounts In Scope</h3>
        <table class="table"><thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Phone</th><th>Action</th></tr></thead><tbody>@forelse($clients as $client)<tr><td>{{ $client->name }}</td><td>{{ $client->roleLabel() }}</td><td>{{ $client->email }}</td><td>{{ $client->phone_number }}</td><td><a href="{{ route('admin.clients.show', $client) }}">View</a></td></tr>@empty<tr><td colspan="5">No client accounts found.</td></tr>@endforelse</tbody></table><div class="pagination-wrap">{{ $clients->links() }}</div>
    </div>
    <div class="panel" style="margin:0;">
        <div class="panel-head"><h3>SSU Accounts</h3><a class="btn primary small" href="{{ route('admin.ssu.create') }}">Add SSU</a></div>
        <table class="table"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Request For</th></tr></thead><tbody>@forelse($ssuAccounts as $ssu)<tr><td>{{ $ssu->name }}</td><td>{{ $ssu->email }}</td><td>{{ $ssu->phone_number }}</td><td>{{ $ssu->isMasterSsu() ? 'All Branches' : ($ssu->assignedBranches()->pluck('name')->join(', ') ?: '-') }}</td></tr>@empty<tr><td colspan="4">No SSU accounts found.</td></tr>@endforelse</tbody></table><div class="pagination-wrap">{{ $ssuAccounts->links() }}</div>
    </div>
    <div class="panel" style="margin:0;">
        <div class="panel-head"><h3>Staff Accounts</h3><a class="btn primary small" href="{{ route('admin.technicians.create') }}">Add Staff</a></div>
        <table class="table"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Action</th></tr></thead><tbody>@forelse($technicians as $tech)<tr><td>{{ $tech->name }}</td><td>{{ $tech->email }}</td><td>{{ $tech->phone_number }}</td><td><a href="{{ route('admin.technicians.show', $tech) }}">View</a></td></tr>@empty<tr><td colspan="4">No staff accounts found.</td></tr>@endforelse</tbody></table><div class="pagination-wrap">{{ $technicians->links() }}</div>
    </div>
</div>
@endsection
