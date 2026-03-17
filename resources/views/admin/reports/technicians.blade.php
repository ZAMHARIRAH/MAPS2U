@extends('layouts.app', ['title' => 'Technician Report'])
@section('content')
<style>@media print{.no-print,.footer-bar,.topbar,.sidebar{display:none !important;} .content-shell{padding:0 !important;} .panel{box-shadow:none !important;border:none !important;} body{background:#fff !important;}}</style>
<div class="page-header no-print"><div><h1>Report / Technician</h1><p>Track technician productivity and completion timing.</p></div><button class="btn ghost" onclick="window.print()">Print Report</button></div>
<section class="panel no-print">
    <form method="GET" class="details-grid report-filter-grid">
        <div><span>Technician</span><select name="technician_id"><option value="">All</option>@foreach($technicians as $tech)<option value="{{ $tech->id }}" {{ ($filters['technician_id'] ?? '') == $tech->id ? 'selected' : '' }}>{{ $tech->name }}</option>@endforeach</select></div>
        <div><span>Task</span><input type="text" name="task" value="{{ $filters['task'] ?? '' }}"></div>
        <div><span>Month</span><input type="month" name="month" value="{{ $filters['month'] ?? '' }}"></div>
        <div class="action-row"><button class="btn primary" type="submit">Generate Report</button></div>
    </form>
</section>
<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Total Tasks</h3><strong>{{ $items->count() }}</strong></div>
    <table class="table"><thead><tr><th>Job ID</th><th>Technician</th><th>Task</th><th>Assigned</th><th>Invoice Uploaded</th><th>Duration</th><th>Status</th></tr></thead><tbody>@forelse($items as $item)<tr><td>{{ $item->request_code }}</td><td>{{ $item->assignedTechnician?->name ?? '-' }}</td><td>{{ $item->requestType?->name ?? '-' }}</td><td>{{ optional($item->assigned_at)->format('d M Y H:i') ?: '-' }}</td><td>{{ optional($item->invoice_uploaded_at)->format('d M Y H:i') ?: '-' }}</td><td>{{ $item->formattedDuration($item->technicianProductivitySeconds()) }}</td><td>{{ $item->technicianStatusLabel() }}</td></tr>@empty<tr><td colspan="7">No records found.</td></tr>@endforelse</tbody></table>
</section>
@endsection
