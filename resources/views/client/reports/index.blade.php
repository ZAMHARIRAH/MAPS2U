@extends('layouts.app', ['title' => 'SSU Report'])
@section('content')
@php
    $printMode = request()->boolean('print');
    $months = collect(range(1,12))->mapWithKeys(fn($m) => [$m => \Carbon\Carbon::create(null,$m,1)->format('M')]);
    $taskNames = $items->flatMap(fn($item) => $item->selectedTaskTitleNames() ?: [($item->requestType?->name ?? '-')])->filter()->unique()->sort()->values();
    $selectedYear = (int) (request('year') ?: now()->year);
    $locationRows = $locations->map(function($location) use ($items, $months, $selectedYear){
        $monthsData = $months->mapWithKeys(fn($label,$month) => [$month => $items->filter(fn($item) => (int)$item->location_id === (int)$location->id && (int)$item->created_at->format('Y') === $selectedYear && (int)$item->created_at->format('n') === $month)->count()]);
        return ['location' => $location, 'months' => $monthsData, 'total' => $monthsData->sum()];
    })->filter(fn($row) => $row['total'] > 0)->values();
    $taskRows = $taskNames->map(function($task) use ($items, $months, $selectedYear){
        $monthsData = $months->mapWithKeys(fn($label,$month) => [$month => $items->filter(fn($item) => ((int)$item->created_at->format('Y') === $selectedYear) && ((int)$item->created_at->format('n') === $month) && (in_array($task, $item->selectedTaskTitleNames() ?: [($item->requestType?->name ?? '-')], true)))->count()]);
        return ['task' => $task, 'months' => $monthsData, 'total' => $monthsData->sum()];
    })->filter(fn($row) => $row['total'] > 0)->values();
@endphp
<style>
@media print{.no-print,.footer-bar,.topbar,.sidebar{display:none !important}.content-shell{padding:0 !important}.panel{box-shadow:none !important;border:none !important}body{background:#fff !important}.report-table-shell{overflow:visible !important}.report-table-shell table{min-width:0 !important}th,td{font-size:10px !important;padding:5px 6px !important}}
.report-shell{display:grid;gap:24px}.print-filter-note{font-size:11px;color:#475569;margin:0 0 10px;line-height:1.45}.filter-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px}.field-block{display:grid;gap:8px}.field-block span{font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase}.field-block input,.field-block select{width:100%;padding:12px 14px;border:1px solid #d6dfeb;border-radius:14px;background:#fff}.summary-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px}.summary-card{border:1px solid #dbe4f0;border-radius:18px;padding:16px;background:#fff}.summary-card strong{display:block;font-size:11px;text-transform:uppercase;color:#64748b;margin-bottom:8px}.summary-card span{font-size:24px;font-weight:700}.report-table-shell{border:1px solid #e2e8f0;border-radius:18px;overflow:auto}.report-table-shell table{width:100%;border-collapse:collapse;min-width:1080px}.report-table-shell th,.report-table-shell td{padding:10px 12px;border-bottom:1px solid #e2e8f0;white-space:nowrap;font-size:13px;text-align:left}.report-table-shell th{background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase}.panel-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:14px;flex-wrap:wrap}.subtle{font-size:13px;color:#64748b}.action-stack{display:flex;gap:8px;flex-wrap:wrap}@media (max-width:1100px){.filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media (max-width:720px){.filter-grid,.summary-grid{grid-template-columns:1fr}}
</style>
<div class="page-header no-print">
    <div>
        <h1>SSU Report</h1>
        <p>Monitor request activity in your assigned state region, including branch totals, task totals, status, and job request details.</p>
    </div>
    <a class="btn ghost" href="{{ route('client.reports.index', array_merge($filters, ['print' => 1])) }}">Print Documents</a>
</div>

<div class="report-shell">
    <section class="panel no-print">
        <form method="GET">
            <div class="filter-grid">
                <div class="field-block"><span>Year</span><input type="number" name="year" value="{{ $selectedYear }}"></div>
                <div class="field-block"><span>Request For</span><select name="location_id"><option value="">All Branches</option>@foreach($locations as $location)<option value="{{ $location->id }}" {{ ($filters['location_id'] ?? '') == $location->id ? 'selected' : '' }}>{{ $location->name }}</option>@endforeach</select></div>
                <div class="field-block"><span>State</span><select name="state"><option value="">All States</option>@foreach($availableStates as $state)<option value="{{ $state }}" {{ ($filters['state'] ?? '') === $state ? 'selected' : '' }}>{{ $state }}</option>@endforeach</select></div>
                <div class="field-block"><span>Status</span><select name="status"><option value="">All Status</option>@foreach($statusOptions as $status)<option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ $status }}</option>@endforeach</select></div>
                <div class="field-block"><span>Date From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
                <div class="field-block"><span>Date To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
            </div>
            <div class="action-stack" style="margin-top:16px;">
                <button class="btn primary" type="submit">Generate Report</button>
                <a class="btn ghost" href="{{ route('client.reports.index') }}">Reset Filter</a>
                <span class="subtle">Printed report follows your current filters and region access.</span>
            </div>
        </form>
    </section>

    @unless($printMode)
    <section class="summary-grid">
        <div class="summary-card"><strong>Total Request</strong><span>{{ $items->count() }}</span></div>
        <div class="summary-card"><strong>Total Branches</strong><span>{{ $items->pluck('location_id')->filter()->unique()->count() }}</span></div>
        <div class="summary-card"><strong>Total Task</strong><span>{{ $taskRows->sum('total') }}</span></div>
        <div class="summary-card"><strong>Completed Job</strong><span>{{ $items->filter(fn($item) => (bool) $item->finance_completed_at)->count() }}</span></div>
    </section>
    @endunless

    @if($printMode)
        <p class="print-filter-note"><strong>State:</strong> {{ $filters['state'] ?? 'All States' }} &nbsp; | &nbsp; <strong>Status:</strong> {{ $filters['status'] ?? 'All Status' }} &nbsp; | &nbsp; <strong>Date Range:</strong> {{ ($filters['date_from'] ?? '-') . " to " . ($filters['date_to'] ?? '-') }} &nbsp; | &nbsp; <strong>Branch:</strong> {{ optional($locations->firstWhere('id', $filters['location_id'] ?? null))->name ?? 'All Branches' }}</p>
    @endif

    <section class="panel">
        <div class="panel-head"><div><h3>Task × Month</h3><div class="subtle">Task activity within your assigned branches and states.</div></div></div>
        <div class="report-table-shell">
            <table>
                <thead><tr><th>Task Title</th>@foreach($months as $monthNumber => $label)<th>{{ $label }}</th>@endforeach<th>Total</th></tr></thead>
                <tbody>
                    @forelse($taskRows as $row)
                        <tr><td>{{ $row['task'] }}</td>@foreach($months as $monthNumber => $label)<td>{{ $row['months'][$monthNumber] ?? 0 }}</td>@endforeach<td><strong>{{ $row['total'] }}</strong></td></tr>
                    @empty
                        <tr><td colspan="14">No task data available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><div><h3>Branch × Month</h3><div class="subtle">Branch movement based on your state region access.</div></div></div>
        <div class="report-table-shell">
            <table>
                <thead><tr><th>Branch</th>@foreach($months as $monthNumber => $label)<th>{{ $label }}</th>@endforeach<th>Total</th></tr></thead>
                <tbody>
                    @forelse($locationRows as $row)
                        <tr><td>{{ $row['location']->name }}</td>@foreach($months as $monthNumber => $label)<td>{{ $row['months'][$monthNumber] ?? 0 }}</td>@endforeach<td><strong>{{ $row['total'] }}</strong></td></tr>
                    @empty
                        <tr><td colspan="14">No branch data available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><div><h3>Request Detail Report</h3><div class="subtle">Includes request date, job request status, technician completion timestamp, and quick access to full request detail.</div></div></div>
        <div class="report-table-shell">
            <table>
                <thead>
                    <tr>
                        <th>Job ID</th>
                        <th>Request For</th>
                        <th>State</th>
                        <th>Task Title</th>
                        <th>Requested At</th>
                        <th>Completed by Technician</th>
                        <th>Job Request Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ $item->request_code }}</td>
                            <td>{{ $item->location?->name ?? '-' }}</td>
                            <td>{{ $item->location?->state ?? '-' }}</td>
                            <td>{{ $item->primaryTaskTitleName() ?? ($item->requestType?->name ?? '-') }}</td>
                            <td>{{ $item->created_at?->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y h:i A') }}</td>
                            <td>{{ $item->technician_completed_at ? $item->technician_completed_at->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y h:i A') : '-' }}</td>
                            <td>{{ $item->adminWorkflowLabel() }}</td>
                            <td>
                                <div class="action-stack">
                                    <a class="btn small ghost" href="{{ route('client.requests.show', $item) }}">View</a>
                                    <a class="btn small ghost" href="{{ route('client.requests.show', array_merge(['clientRequest' => $item], ['print' => 1])) }}" target="_blank">Print Full Report</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No request found for the selected filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@if($printMode)
<style>.page-header,.panel.no-print{display:none !important}.panel-head .subtle{display:none}.panel{border:none !important;box-shadow:none !important;padding:0 !important}.report-table-shell{border:none !important;border-radius:0 !important;overflow:visible !important}.report-shell{gap:14px}.action-stack{display:none !important}</style>
<script>window.addEventListener('load',()=>window.print());</script>
@endif
@endsection
