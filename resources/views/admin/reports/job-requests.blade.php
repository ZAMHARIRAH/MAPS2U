@extends('layouts.app', ['title' => 'Job Request Report'])
@section('content')
<style>
@media print{.no-print,.footer-bar,.topbar,.sidebar{display:none !important;} .content-shell{padding:0 !important;} .panel{box-shadow:none !important;border:none !important;} body{background:#fff !important;}}
.report-grid{display:grid;gap:22px}.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.summary-card{border:1px solid #dbe4f0;border-radius:18px;padding:16px;background:#fff}.summary-card strong{display:block;font-size:11px;text-transform:uppercase;color:#64748b;margin-bottom:8px}.summary-card span{font-size:24px;font-weight:700}.report-table-shell{border:1px solid #e2e8f0;border-radius:18px;overflow:auto}.report-table-shell table{width:100%;border-collapse:collapse;min-width:1080px}.report-table-shell th,.report-table-shell td{padding:11px 12px;border-bottom:1px solid #e2e8f0;white-space:nowrap;font-size:13px;text-align:left}.report-table-shell th{background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase}@media (max-width:1000px){.summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media (max-width:640px){.summary-grid{grid-template-columns:1fr}}
</style>
<div class="page-header no-print"><div><h1>Report / Job Request</h1><p> </p></div><button class="btn ghost" onclick="window.print()">Print Report</button></div>
<div class="report-grid">
<section class="panel no-print">
    <form method="GET" class="details-grid report-filter-grid">
        @if($clientRole === \App\Models\User::CLIENT_HQ)<div><span>Location</span><select name="location_id"><option value="">All Location</option>@foreach($locations as $loc)<option value="{{ $loc->id }}" {{ ($filters['location_id'] ?? '') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>@endforeach</select></div>@else<div><span>Branch</span><select name="location_id"><option value="">All Branches</option>@foreach($locations as $loc)<option value="{{ $loc->id }}" {{ ($filters['location_id'] ?? '') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>@endforeach</select></div>@endif
        <div><span>Client Name</span><input type="text" name="client_name" value="{{ $filters['client_name'] ?? '' }}"></div>
        <div><span>Status</span><select name="status"><option value="">All Status</option>@foreach(\App\Models\ClientRequest::adminVisibleStatusOptions() as $status)<option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ $status }}</option>@endforeach</select></div>
        <div><span>Date From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
        <div><span>Date To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
        <div class="action-row"><button class="btn primary" type="submit">Generate Report</button><a class="btn ghost" href="{{ route('admin.reports.job-request') }}">Reset</a></div>
    </form>
</section>
<section class="summary-grid">
    <div class="summary-card"><strong>Total Jobs</strong><span>{{ $items->count() }}</span></div>
    <div class="summary-card"><strong>Total Locations / Branches</strong><span>{{ $items->pluck('location_id')->filter()->unique()->count() }}</span></div>
    <div class="summary-card"><strong>Completed Jobs</strong><span>{{ $items->filter(fn($item) => $item->finance_completed_at)->count() }}</span></div>
    <div class="summary-card"><strong>Total Approved Cost</strong><span>RM {{ number_format($items->sum(fn($item) => $item->approvedCostAmount()), 2) }}</span></div>
</section>
<section class="panel">
    <div class="panel-head"><h3>Job Request Listing</h3><strong>{{ $items->count() }} row(s)</strong></div>
    <div class="report-table-shell"><table><thead><tr><th>Job ID</th><th>Client</th><th>Task Title</th><th>Location</th><th>Department</th><th>Status</th><th>Submitted</th><th>Completed</th><th>Cost</th></tr></thead><tbody>@forelse($items as $item)<tr><td>{{ $item->request_code }}</td><td>{{ $item->full_name }}</td><td>{{ $item->primaryTaskTitleName() ?? ($item->requestType?->name ?? '-') }}</td><td>{{ $item->location?->name ?? '-' }}</td><td>{{ $item->department?->name ?? '-' }}</td><td>{{ $item->adminWorkflowLabel() }}</td><td>{{ $item->created_at->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') }}</td><td>{{ $item->finance_completed_at ? $item->finance_completed_at->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') : '-' }}</td><td>RM {{ number_format($item->approvedCostAmount(), 2) }}</td></tr>@empty<tr><td colspan="9">No records found.</td></tr>@endforelse</tbody></table></div><div class="pagination-wrap">{{ $items->links() }}</div>
</section>
</div>
@endsection
