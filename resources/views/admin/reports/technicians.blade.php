@extends('layouts.app', ['title' => 'Technician Report'])
@section('content')
<style>
@media print{.no-print,.footer-bar,.topbar,.sidebar{display:none !important;} .content-shell{padding:0 !important;} .panel{box-shadow:none !important;border:none !important;} body{background:#fff !important;}}
.report-filter-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.filter-actions{display:flex;gap:10px;align-items:end;flex-wrap:wrap}
@media (max-width:900px){.report-filter-grid{grid-template-columns:1fr}.filter-actions{align-items:stretch}.filter-actions .btn,.filter-actions button{width:100%;justify-content:center}}
</style>
<div class="page-header no-print"><div><h1>Report / Technician</h1><p> </p></div></div>
<section class="panel no-print">
    <form method="GET" class="details-grid report-filter-grid">
        <input type="hidden" name="generate" value="1">
        <div><span>Technician</span><select name="technician_id"><option value="">All</option>@foreach($technicians as $tech)<option value="{{ $tech->id }}" {{ ($filters['technician_id'] ?? '') == $tech->id ? 'selected' : '' }}>{{ $tech->name }}</option>@endforeach</select></div>
        <div><span>Task</span><input type="text" name="task" value="{{ $filters['task'] ?? '' }}"></div>
        <div><span>Month</span><input type="month" name="month" value="{{ $filters['month'] ?? '' }}"></div>
        <div class="filter-actions">
            <button class="btn primary" type="submit">Generate Report</button>
            @if(request('generate') && ($reportItems->count() ?? 0) > 0)
                <a class="btn ghost js-view-print" href="{{ route('admin.reports.technician.merged', array_merge($filters, ['generate' => 1])) }}" target="_blank">View for Print</a>
            @endif
        </div>
    </form>
</section>

<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Total Tasks</h3><strong>{{ $items->count() }}</strong></div>
    <div class="table-responsive"><table class="table admin-report-table"><thead><tr><th>Job ID</th><th>Technician</th><th>Task</th><th>Assigned</th><th>Invoice Uploaded</th><th>Status</th><th>Report</th><th>Client Feedback</th></tr></thead><tbody>@forelse($items as $item)<tr><td>{{ $item->request_code }}</td><td>{{ $item->assignedTechnician?->name ?? '-' }}</td><td>{{ $item->requestType?->name ?? '-' }}</td><td>{{ $item->assigned_at ? $item->assigned_at->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') : '-' }}</td><td>{{ $item->invoice_uploaded_at ? $item->invoice_uploaded_at->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') : '-' }}</td><td>{{ $item->technicianStatusLabel() }}</td><td>@if($item->technician_completed_at && $item->customer_service_report)<span class="helper-text">Included in merged view</span>@else<span class="helper-text">Pending CSR</span>@endif</td><td>@if($item->technician_completed_at && $item->feedback)<span class="helper-text">Included in merged view</span>@else<span class="helper-text">Pending Feedback</span>@endif</td></tr>@empty<tr><td colspan="8">No records found.</td></tr>@endforelse</tbody></table></div>
</section>
<script>
document.querySelectorAll('.js-view-print').forEach((link) => {
    link.addEventListener('click', (event) => {
        event.preventDefault();
        const url = new URL(link.href);
        const now = new Date();
        const pad = (value) => String(value).padStart(2, '0');
        const stamp = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
        url.searchParams.set('viewed_at', stamp);
        window.open(url.toString(), '_blank');
    });
});
</script>
@endsection
