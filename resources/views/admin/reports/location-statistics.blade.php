@extends('layouts.app', ['title' => $title . ' Report'])
@section('content')
@php
    $docPrintFlag = request('document_print');
@endphp
<style>
@media print{.no-print,.footer-bar,.topbar,.sidebar{display:none !important;} .content-shell{padding:0 !important;} .panel{box-shadow:none !important;border:none !important;} body{background:#fff !important;}}
.stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:16px}.stat-card{border:1px solid #dbe4f0;border-radius:16px;padding:14px;background:#fff}.stat-card strong{display:block;font-size:11px;text-transform:uppercase;color:#64748b;margin-bottom:8px}.stat-card span{font-size:24px;font-weight:700}.mini-bar{height:8px;border-radius:999px;background:#e2e8f0;overflow:hidden}.mini-bar > div{height:100%;background:#0f172a}.table-wrap{overflow:auto}.action-stack{display:flex;flex-wrap:wrap;gap:8px}.helper-list{display:grid;gap:8px}.helper-item{padding:10px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc}.scroll-x{overflow-x:auto}.compact-table th,.compact-table td{white-space:nowrap;vertical-align:top}.compact-table td .muted{display:block;font-size:12px;color:#64748b;margin-top:4px}
</style>
<div class="page-header no-print">
    <div>
        <h1>Report / {{ $title }}</h1>
        <p>Power filter for {{ strtolower($title) }} with total jobs, task totals, approved quotation amount, and technician duration hours.</p>
    </div>
    <div class="action-stack">
        <a class="btn ghost" href="{{ $statisticsPrintRoute }}">Print Report Statistic</a>
        <a class="btn ghost" href="{{ $listPrintRoute }}">Print Report</a>
    </div>
</div>
<section class="panel no-print">
    <form method="GET" class="details-grid report-filter-grid">
        @if($showEntityFilter)
            <div>
                <span>{{ $entityLabel }}</span>
                <select name="location_id">
                    <option value="">All {{ $title }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ ($filters['location_id'] ?? '') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if($showStateFilter)
            <div>
                <span>State</span>
                <select name="state">
                    <option value="">All States</option>
                    @foreach($availableStates as $state)
                        <option value="{{ $state }}" {{ ($filters['state'] ?? '') === $state ? 'selected' : '' }}>{{ $state }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div><span>Date From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
        <div><span>Date To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
        <div class="action-row"><button class="btn primary" type="submit">Generate Report</button></div>
    </form>
</section>
<section class="stat-grid">
    <div class="stat-card"><strong>Total {{ $title }}</strong><span>{{ $rows->count() }}</span></div>
    <div class="stat-card"><strong>Total Jobs</strong><span>{{ $items->count() }}</span></div>
    <div class="stat-card"><strong>Total Approved Cost</strong><span>RM {{ number_format($rows->sum('total_cost'), 2) }}</span></div>
    <div class="stat-card"><strong>Total Duration Hours</strong><span>{{ number_format($rows->sum('duration_hours'), 2) }}</span></div>
</section>
<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>{{ $title }} Statistics</h3><strong>{{ $rows->count() }} item(s)</strong></div>
    <div class="scroll-x">
        <table class="table compact-table">
            <thead>
                <tr>
                    <th>{{ $entityLabel }}</th>
                    <th>State</th>
                    <th>Total Jobs</th>
                    @foreach($requestTypes as $requestType)
                        <th>{{ $requestType->name }}<br><small>Qty</small></th>
                        <th>{{ $requestType->name }}<br><small>Cost</small></th>
                    @endforeach
                    <th>Total Cost</th>
                    <th>Duration Hours</th>
                    <th>Statistic</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php
                        $maxJobs = max(1, (int) $rows->max('total_jobs'));
                        $barWidth = min(100, round(($row['total_jobs'] / $maxJobs) * 100));
                    @endphp
                    <tr>
                        <td>{{ $row['location']?->name ?? '-' }}</td>
                        <td>{{ $row['location']?->state ?? '-' }}</td>
                        <td>{{ $row['total_jobs'] }}</td>
                        @foreach($requestTypes as $requestType)
                            <td>{{ $row['task_counts'][$requestType->id] ?? 0 }}</td>
                            <td>RM {{ number_format($row['task_costs'][$requestType->id] ?? 0, 2) }}</td>
                        @endforeach
                        <td>RM {{ number_format($row['total_cost'], 2) }}</td>
                        <td>{{ number_format($row['duration_hours'], 2) }}</td>
                        <td style="min-width:180px;"><div class="mini-bar"><div style="width:{{ $barWidth }}%"></div></div><small>{{ $barWidth }}%</small></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 7 + ($requestTypes->count() * 2) }}">No records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Request Details</h3><strong>{{ $items->count() }} job(s)</strong></div>
    <div class="table-wrap">
        <table class="table compact-table">
            <thead>
                <tr>
                    <th>Job ID</th>
                    <th>Client</th>
                    <th>{{ $entityLabel }}</th>
                    <th>Task</th>
                    <th>Status</th>
                    <th>Approved Cost</th>
                    <th>Feedback</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>{{ $item->request_code }}</td>
                        <td>{{ $item->full_name }}<span class="muted">{{ $item->phone_number }}</span></td>
                        <td>{{ $item->location?->name ?? '-' }}</td>
                        <td>{{ $item->requestType?->name ?? '-' }}</td>
                        <td>{{ $item->adminWorkflowLabel() }}</td>
                        <td>RM {{ number_format((float) (is_numeric(data_get($item->approvedQuotation(), 'amount')) ? data_get($item->approvedQuotation(), 'amount') : 0), 2) }}</td>
                        <td>{{ $item->feedbackAverage() ? number_format($item->feedbackAverage(), 2).' / 5' : '-' }}</td>
                        <td>
                            <div class="action-stack">
                                <a class="btn small ghost" href="{{ route($viewRouteName, $item) }}">View</a>
                                <a class="btn small primary" href="{{ route($documentPrintRouteName, [$item, 'print' => 1]) }}">Print Report</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">No request records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@if($printMode || $docPrintFlag)
<script>window.addEventListener('load',()=>window.print());</script>
@endif
@endsection
