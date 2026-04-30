@extends('layouts.app', ['title' => 'Vendor'])
@section('content')
@php
    $fileUrl = function ($path) {
        return $path ? route('files.show', ['encodedPath' => rtrim(strtr(base64_encode($path), '+/', '-_'), '=')]) : null;
    };
@endphp
<div class="page-header"><div><h1>Vendor</h1><p> </p></div><a class="btn primary" href="{{ route('admin.vendors.create') }}">Create Vendor</a></div>
<div class="panel"><table class="table"><thead><tr><th>Company Name</th><th>SSM Number</th><th>Phone</th><th>Official Email</th><th>Contact Person</th><th>Uploaded File</th><th>Action</th></tr></thead><tbody>@forelse($vendors as $vendor)<tr><td>{{ $vendor->company_name }}</td><td>{{ $vendor->ssm_number ?: '-' }}</td><td>{{ $vendor->phone_number ?: '-' }}</td><td>{{ $vendor->official_email ?: '-' }}</td><td>{{ $vendor->contact_person ?: '-' }}</td><td>@if($vendor->document_path)<a href="{{ $fileUrl($vendor->document_path) }}" target="_blank">{{ $vendor->document_original_name ?: 'View File' }}</a>@else-@endif</td><td><div class="action-row"><a class="btn tiny ghost" href="{{ route('admin.vendors.edit', $vendor) }}">Edit</a><form method="POST" action="{{ route('admin.vendors.destroy', $vendor) }}" onsubmit="return confirm('Delete this vendor?');">@csrf @method('DELETE')<button class="btn tiny danger" type="submit">Delete</button></form></div></td></tr>@empty<tr><td colspan="7">No vendor registered yet.</td></tr>@endforelse</tbody></table><div class="pagination-wrap">{{ $vendors->links() }}</div></div>
@endsection
