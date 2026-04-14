@extends('layouts.app', ['title' => 'Manage SSU'])
@section('content')
<div class="page-header"><div><h1>Manage SSU</h1><p>Register SSU accounts and control which branch states they can request for.</p></div><a class="btn primary" href="{{ route('admin.ssu.create') }}">Add SSU</a></div>
<div class="panel"><table class="table"><thead><tr><th>SSU Name</th><th>Email</th><th>Phone</th><th>Region</th></tr></thead><tbody>@forelse($ssuAccounts as $ssu)<tr><td>{{ $ssu->name }}</td><td>{{ $ssu->email }}</td><td>{{ $ssu->phone_number }}</td><td>{{ collect($ssu->region_states ?? [])->join(', ') ?: '-' }}</td></tr>@empty<tr><td colspan="4">No SSU account available.</td></tr>@endforelse</tbody></table></div>
@endsection
