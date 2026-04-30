@extends('layouts.app', ['title' => 'Manage Staff'])
@section('content')
<div class="page-header"><div><h1>Manage Technician Account</h1><p> </p></div><a class="btn primary" href="{{ route('admin.technicians.create') }}">Add Staff</a></div><div class="panel"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Phone Number</th><th>Action</th></tr></thead><tbody>@forelse($technicians as $technician)<tr><td>{{ $technician->name }}</td><td>{{ $technician->email }}</td><td>{{ $technician->phone_number }}</td><td><a href="{{ route('admin.technicians.show', $technician) }}">View</a></td></tr>@empty<tr><td colspan="4">No staff account available.</td></tr>@endforelse</tbody></table><div class="pagination-wrap">{{ $technicians->links() }}</div></div>
@endsection
