@extends('layouts.app', ['title' => 'Technician Report'])
@section('content')
<style>
@media print{.no-print,.footer-bar,.topbar,.sidebar{display:none !important;} .content-shell{padding:0 !important;} .panel{box-shadow:none !important;border:none !important;} body{background:#fff !important;}}
.report-filter-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.summary-card{border:1px solid #dbe4f0;border-radius:18px;padding:16px;background:#fff}.summary-card strong{display:block;font-size:11px;text-transform:uppercase;color:#64748b;margin-bottom:8px}.summary-card span{font-size:24px;font-weight:700}.filter-actions{display:flex;gap:10px;align-items:end;flex-wrap:wrap}.table-shell{border:1px solid #e2e8f0;border-radius:18px;overflow:auto}.table-shell table{width:100%;border-collapse:collapse;min-width:1080px}.table-shell th,.table-shell td{padding:11px 12px;border-bottom:1px solid #e2e8f0;white-space:nowrap;font-size:13px;text-align:left}.table-shell th{background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase}@media (max-width:1100px){.report-filter-grid,.summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media (max-width:760px){.report-filter-grid,.summary-grid{grid-template-columns:1fr}}
</style>
<div class="page-header no-print"><div><h1>Report / Technician</h1><p> </p></div></div>
<section class="panel no-print">
    <form method="GET" class="details-grid report-filter-grid">
        <input type="hidden" name="generate" value="1">
        <div><span>Technician</span><select name="technician_id"><option value="">All</option>@foreach($technicians as $tech)<option value="{{ $tech->id }}" {{ ($filters['technician_id'] ?? '') == $tech->id ? 'selected' : '' }}>{{ $tech->name }}</option>@endforeach</select></div>
        <div><span>Task</span><input type="text" name="task" value="{{ $filters['task'] ?? '' }}"></div>
        <div><span>Month</span><input type="month" name="month" value="{{ $filters['month'] ?? '' }}"></div>
        <div class="filter-actions"><button class="btn primary" type="submit">Generate Report</button>@if(request('generate') && ($reportItems->count() ?? 0) > 0)<a class="btn ghost js-view-print" href="{{ route('admin.reports.technician.merged', array_merge($filters, ['generate' => 1])) }}" target="_blank">View for Print</a>@endif</div>
    </form>
</section>
<section class="summary-grid" style="margin-top:20px;">
    <div class="summary-card"><strong>Total Tasks</strong><span>{{ $items->count() }}</span></div>
    <div class="summary-card"><strong>Completed CSR</strong><span>{{ $items->filter(fn($item) => $item->technician_completed_at && !empty($item->customer_service_report))->count() }}</span></div>
    <div class="summary-card"><strong>Invoice Uploaded</strong><span>{{ $items->filter(fn($item) => $item->invoice_uploaded_at)->count() }}</span></div>
    <div class="summary-card"><strong>Total Approved Cost</strong><span>RM {{ number_format($items->sum(fn($item) => is_numeric(data_get($item->approvedQuotation(), 'amount')) ? (float) data_get($item->approvedQuotation(), 'amount') : 0), 2) }}</span></div>
</section>
<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Technician Activity Table</h3><strong>{{ $items->count() }} row(s)</strong></div>
    <div class="table-shell"><table><thead><tr><th>Job ID</th><th>Technician</th><th>Task</th><th>Assigned</th><th>Completed</th><th>Invoice Uploaded</th><th>Status</th><th>Hours</th><th>Report</th><th>Client Feedback</th></tr></thead><tbody>@forelse($items as $item)<tr><td>{{ $item->request_code }}</td><td>{{ $item->assignedTechnician?->name ?? '-' }}</td><td>{{ $item->primaryTaskTitleName() ?? ($item->requestType?->name ?? '-') }}</td><td>{{ $item->assigned_at ? $item->assigned_at->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') : '-' }}</td><td>{{ $item->technician_completed_at ? $item->technician_completed_at->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') : '-' }}</td><td>{{ $item->invoice_uploaded_at ? $item->invoice_uploaded_at->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') : '-' }}</td><td>{{ $item->technicianStatusLabel() }}</td><td>{{ number_format($item->reportDurationHours(), 2) }}</td><td>@if($item->technician_completed_at && $item->customer_service_report)<span class="helper-text">Included in merged view</span>@else<span class="helper-text">Pending CSR</span>@endif</td><td>@if($item->technician_completed_at && $item->feedback)<span class="helper-text">Included in merged view</span>@else<span class="helper-text">Pending Feedback</span>@endif</td></tr>@empty<tr><td colspan="10">No records found.</td></tr>@endforelse</tbody></table></div><div class="pagination-wrap">{{ $items->links() }}</div>
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
