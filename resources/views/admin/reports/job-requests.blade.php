@extends('layouts.app', ['title' => 'Job Request Report'])
@section('content')
<style>@media print{.no-print,.footer-bar,.topbar,.sidebar{display:none !important;} .content-shell{padding:0 !important;} .panel{box-shadow:none !important;border:none !important;} body{background:#fff !important;}}</style>
<div class="page-header no-print"><div><h1>Report / Job Request</h1><p> </p></div><button class="btn ghost" onclick="window.print()">Print Report</button></div>
<section class="panel no-print">
    <form method="GET" class="details-grid report-filter-grid">
        @if($clientRole === \App\Models\User::CLIENT_HQ)<div><span>Location</span><select name="location_id"><option value="">All Location</option>@foreach($locations as $loc)<option value="{{ $loc->id }}" {{ ($filters['location_id'] ?? '') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>@endforeach</select></div>@else<div><span>Branch</span><select name="location_id"><option value="">All Branches</option>@foreach($locations as $loc)<option value="{{ $loc->id }}" {{ ($filters['location_id'] ?? '') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>@endforeach</select></div>@endif
        <div><span>Client Name</span><input type="text" name="client_name" value="{{ $filters['client_name'] ?? '' }}"></div>
        <div><span>Date From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
        <div><span>Date To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
        <div class="action-row"><button class="btn primary" type="submit">Generate Report</button></div>
    </form>
</section>
<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Total Jobs</h3><strong>{{ $items->count() }}</strong></div>
    <table class="table"><thead><tr><th>Job ID</th><th>Client</th><th>Type</th><th>Location</th><th>Department</th><th>Status</th><th>Date</th></tr></thead><tbody>@forelse($items as $item)<tr><td>{{ $item->request_code }}</td><td>{{ $item->full_name }}</td><td>{{ $item->requestType?->name ?? '-' }}</td><td>{{ $item->location?->name ?? '-' }}</td><td>{{ $item->department?->name ?? '-' }}</td><td>{{ $item->adminWorkflowLabel() }}</td><td>{{ $item->created_at->format('d M Y') }}</td></tr>@empty<tr><td colspan="7">No records found.</td></tr>@endforelse</tbody></table>
</section>
@endsection
