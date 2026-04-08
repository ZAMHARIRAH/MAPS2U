@extends('layouts.app', ['title' => ucfirst($type) . ' Locations'])
@section('content')
<div class="page-header"><div><h1>{{ $type === 'hq' ? 'HQ Locations' : 'Branches' }}</h1><p> </p></div><a class="btn primary" href="{{ route('admin.locations.create', $type) }}">Add Location</a></div>
<section class="panel">
<table class="table"><thead><tr><th>Name</th><th>Address</th><th>Status</th><th>Action</th></tr></thead><tbody>@forelse($locations as $location)<tr><td>{{ $location->name }}</td><td>{{ $location->address ?: '-' }}</td><td>{{ $location->is_active ? 'Active' : 'Inactive' }}</td><td class="action-row"><a href="{{ route('admin.locations.edit', $location) }}">Edit</a><form method="POST" action="{{ route('admin.locations.destroy', $location) }}" data-delete-confirm="Delete this location?">@csrf @method('DELETE')<button class="btn small danger" type="submit">Delete</button></form></td></tr>@empty<tr><td colspan="4">No location found.</td></tr>@endforelse</tbody></table>
</section>
@endsection
