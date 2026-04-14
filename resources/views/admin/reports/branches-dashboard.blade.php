@extends('layouts.app', ['title' => 'Branches Report'])
@section('content')
@php
    $grandTotalJobs = $items->count();
    $grandTotalHours = round($items->sum(fn ($item) => $item->technicianLogStartedAt() && $item->technician_completed_at ? max(0, $item->technicianLogStartedAt()->diffInMinutes($item->technician_completed_at->copy()->timezone('Asia/Kuala_Lumpur')) / 60) : 0), 2);
    $grandTotalCost = round($items->sum(fn ($item) => is_numeric(data_get($item->approvedQuotation(), 'amount')) ? (float) data_get($item->approvedQuotation(), 'amount') : 0), 2);
    $activeBranchCount = $monthlyBranchSummary->count();
    $activeTaskCount = $monthlyTaskSummary->count();
    $maxBranchJobs = max(1, (int) $combined['branchPerformance']->max('total_jobs'));
@endphp
<style>
@media print{.no-print,.footer-bar,.topbar,.sidebar{display:none !important}.content-shell{padding:0 !important}.panel{box-shadow:none !important;border:none !important}body{background:#fff !important}}
.report-shell{display:grid;gap:20px}.kpi-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.kpi-card{padding:18px;border:1px solid #dbe7f5;border-radius:18px;background:#fff}.kpi-card span{display:block;color:#64748b;font-size:12px;text-transform:uppercase;margin-bottom:8px}.kpi-card strong{font-size:28px;line-height:1.1}.report-table-shell{overflow:auto}.report-table-shell table th,.report-table-shell table td{white-space:nowrap}.mini-action-link{font-size:12px}.dashboard-grid-2{display:grid;grid-template-columns:1.5fr 1fr;gap:20px}.matrix-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}.matrix-card{border:1px solid #dbe7f5;border-radius:16px;padding:16px;background:#f8fbff}.stat-line{display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px dashed #dbe7f5}.stat-line:last-child{border-bottom:none}.bar-line{display:grid;gap:8px}.bar-row{display:grid;grid-template-columns:160px 1fr 64px;align-items:center;gap:10px}.bar-track{height:10px;border-radius:999px;background:#e2e8f0;overflow:hidden}.bar-track div{height:100%;background:#2563eb}.line-series{display:flex;align-items:end;gap:8px;min-height:160px}.line-series .point{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px}.line-series .point .bar{width:100%;max-width:24px;border-radius:999px 999px 0 0;background:#93c5fd;min-height:4px}.helper-card{padding:12px;border:1px solid #dbe7f5;border-radius:14px;background:#fff}.filter-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}@media (max-width: 1100px){.kpi-grid,.filter-grid,.dashboard-grid-2{grid-template-columns:1fr}.bar-row{grid-template-columns:120px 1fr 56px}}
</style>
<div class="page-header no-print">
    <div>
        <h1>Report / Branches</h1>
        <p>Monthly dashboard, branch detail, and combined statistics using dynamic task titles from Manage Task.</p>
    </div>
    <div class="action-stack">
        <a class="btn ghost" href="{{ $statisticsPrintRoute }}">Print Report Statistic</a>
        <a class="btn ghost" href="{{ $listPrintRoute }}">Print Report</a>
    </div>
</div>
<section class="panel no-print">
    <form method="GET" class="filter-grid">
        <div><span>Year</span><input type="number" name="year" min="2020" max="2100" value="{{ $selectedYear }}"></div>
        <div><span>State</span><select name="state"><option value="">All States</option>@foreach($availableStates as $state)<option value="{{ $state }}" @selected(($filters['state'] ?? '') === $state)>{{ $state }}</option>@endforeach</select></div>
        <div><span>Date From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
        <div><span>Date To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
        <div><span>Detail Branch</span><select name="detail_branch_id"><option value="">All Branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)($filters['detail_branch_id'] ?? '') === (string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
        <div><span>Detail Task</span><select name="detail_task"><option value="">All Tasks</option>@foreach($taskTitles as $task)<option value="{{ $task->title }}" @selected(($filters['detail_task'] ?? '') === $task->title)>{{ $task->title }}</option>@endforeach</select></div>
        <div class="action-row" style="grid-column:1/-1"><button class="btn primary" type="submit">Generate Dashboard</button></div>
    </form>
</section>
<div class="report-shell">
    <section class="kpi-grid">
        <article class="kpi-card"><span>Total Task Bulanan</span><strong>{{ $activeTaskCount }}</strong></article>
        <article class="kpi-card"><span>Total Branch Bulanan</span><strong>{{ $activeBranchCount }}</strong></article>
        <article class="kpi-card"><span>Total Job</span><strong>{{ $grandTotalJobs }}</strong></article>
        <article class="kpi-card"><span>Total Hours</span><strong>{{ number_format($grandTotalHours, 2) }}</strong></article>
        <article class="kpi-card"><span>Grand Total Cost</span><strong>RM {{ number_format($grandTotalCost, 2) }}</strong></article>
    </section>

    <section class="panel">
        <div class="panel-head"><h3>Monthly Task Summary</h3><a class="btn small ghost no-print" href="#branch-detail">View Details</a></div>
        <div class="report-table-shell">
            <table class="table compact-table">
                <thead><tr><th>Task Title</th>@foreach($months as $monthNumber => $label)<th>{{ $label }}</th>@endforeach<th>Total</th></tr></thead>
                <tbody>
                    @forelse($monthlyTaskSummary as $row)
                        <tr>
                            <td>{{ $row['task']->title }}</td>
                            @foreach($months as $monthNumber => $label)
                                <td><a class="mini-action-link" href="{{ route('admin.reports.branches', array_merge(request()->all(), ['detail_task' => $row['task']->title])) }}#branch-detail">{{ $row['months'][$monthNumber] ?? 0 }}</a></td>
                            @endforeach
                            <td><strong>{{ $row['total'] }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="14">No task statistics available for the selected filter.</td></tr>
                    @endforelse
                    <tr>
                        <th>Grand Total</th>
                        @foreach($months as $monthNumber => $label)
                            <th>{{ $monthlyTaskSummary->sum(fn ($row) => $row['months'][$monthNumber] ?? 0) }}</th>
                        @endforeach
                        <th>{{ $monthlyTaskSummary->sum('total') }}</th>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><h3>Monthly Branch Summary</h3><a class="btn small ghost no-print" href="#branch-detail">View Details</a></div>
        <div class="report-table-shell">
            <table class="table compact-table">
                <thead><tr><th>Branch</th>@foreach($months as $monthNumber => $label)<th>{{ $label }}</th>@endforeach<th>Total</th></tr></thead>
                <tbody>
                    @forelse($monthlyBranchSummary as $row)
                        <tr>
                            <td>{{ $row['branch']->name }}</td>
                            @foreach($months as $monthNumber => $label)
                                <td><a class="mini-action-link" href="{{ route('admin.reports.branches', array_merge(request()->all(), ['detail_branch_id' => $row['branch']->id])) }}#branch-detail">{{ $row['months'][$monthNumber] ?? 0 }}</a></td>
                            @endforeach
                            <td><strong>{{ $row['total'] }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="14">No branch statistics available for the selected filter.</td></tr>
                    @endforelse
                    <tr>
                        <th>Grand Total</th>
                        @foreach($months as $monthNumber => $label)
                            <th>{{ $monthlyBranchSummary->sum(fn ($row) => $row['months'][$monthNumber] ?? 0) }}</th>
                        @endforeach
                        <th>{{ $monthlyBranchSummary->sum('total') }}</th>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <div class="dashboard-grid-2" id="branch-detail">
        <section class="panel">
            <div class="panel-head"><h3>Branch &amp; Task Detail Page</h3><span>{{ $detail['selectedBranch']?->name ?? 'All Branches' }}{{ $detail['selectedTask'] ? ' / '.$detail['selectedTask'] : '' }}</span></div>
            <div class="kpi-grid" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:16px;">
                <article class="helper-card"><span>Total Jobs</span><strong>{{ $detail['summary']['total_jobs'] }}</strong></article>
                <article class="helper-card"><span>Total Hours</span><strong>{{ number_format($detail['summary']['total_hours'], 2) }}</strong></article>
                <article class="helper-card"><span>Total Cost</span><strong>RM {{ number_format($detail['summary']['total_cost'], 2) }}</strong></article>
            </div>
            <div class="report-table-shell">
                <table class="table compact-table">
                    <thead><tr><th>Task Title</th><th>Total Job</th><th>Total Hours</th><th>Total Cost</th><th>Total per Task</th></tr></thead>
                    <tbody>
                        @forelse($detail['rows'] as $row)
                            <tr><td>{{ $row['task'] }}</td><td>{{ $row['total_job'] }}</td><td>{{ number_format($row['total_hours'], 2) }}</td><td>RM {{ number_format($row['total_cost'], 2) }}</td><td>RM {{ number_format($row['total_per_task'], 2) }}</td></tr>
                        @empty
                            <tr><td colspan="5">No detail rows available for the selected branch/task.</td></tr>
                        @endforelse
                        <tr><th>Total</th><th>{{ collect($detail['rows'])->sum('total_job') }}</th><th>{{ number_format(collect($detail['rows'])->sum('total_hours'), 2) }}</th><th>RM {{ number_format(collect($detail['rows'])->sum('total_cost'), 2) }}</th><th>RM {{ number_format(collect($detail['rows'])->sum('total_per_task'), 2) }}</th></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h3>Export / Print</h3></div>
            <div class="helper-card" style="display:grid;gap:12px;">
                <div>Use <strong>Print Report Statistic</strong> to print monthly summaries and dashboard totals.</div>
                <div>Use <strong>Print Report</strong> to print merged request details, feedback forms, and completed documents from the filtered list below.</div>
                <div class="action-row no-print"><a class="btn ghost" href="{{ $statisticsPrintRoute }}">Print Statistic</a><a class="btn ghost" href="{{ $listPrintRoute }}">Print Documents</a></div>
            </div>
            <div class="helper-card" style="margin-top:14px;display:grid;gap:8px;">
                <strong>Completed Requests in Current Filter</strong>
                <div style="max-height:420px;overflow:auto;display:grid;gap:8px;">
                    @forelse($detail['jobs']->take(50) as $job)
                        <div class="stat-line"><div><strong>{{ $job->request_code }}</strong><div class="helper-text">{{ $job->location?->name ?? '-' }} / {{ $job->primaryTaskTitleName() ?? ($job->requestType?->name ?? '-') }}</div></div><div class="action-stack no-print"><a href="{{ route('admin.incoming-requests.show', $job) }}">View</a><a href="{{ route('admin.incoming-requests.print', $job) }}" target="_blank">Print</a></div></div>
                    @empty
                        <div class="helper-text">No request found for this detail filter.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    <section class="panel">
        <div class="panel-head"><h3>Combined Statistics Page</h3><span>Task × Branch, Branch Performance, Task Trend</span></div>
        <div class="dashboard-grid-2">
            <div class="helper-card">
                <h4 style="margin-bottom:12px;">Task × Branch Matrix</h4>
                <div class="report-table-shell">
                    <table class="table compact-table">
                        <thead><tr><th>Task</th>@foreach($combined['branchPerformance']->take(12) as $branchRow)<th>{{ $branchRow['branch']->name }}</th>@endforeach</tr></thead>
                        <tbody>
                            @forelse($combined['taskBranchMatrix'] as $row)
                                <tr><td>{{ $row['task'] }}</td>@foreach($combined['branchPerformance']->take(12) as $branchRow)<td>{{ $row['branches'][$branchRow['branch']->name] ?? 0 }}</td>@endforeach</tr>
                            @empty
                                <tr><td colspan="13">No matrix data available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="helper-card">
                <h4 style="margin-bottom:12px;">Branch Performance</h4>
                <div class="bar-line">
                    @forelse($combined['branchPerformance']->take(12) as $row)
                        <div class="bar-row"><span>{{ $row['branch']->name }}</span><div class="bar-track"><div style="width:{{ ($row['total_jobs'] / $maxBranchJobs) * 100 }}%"></div></div><strong>{{ $row['total_jobs'] }}</strong></div>
                    @empty
                        <div class="helper-text">No branch performance data available.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="matrix-grid" style="margin-top:20px;">
            @foreach($combined['taskTrend']->take(6) as $trend)
                @php($maxSeries = max(1, (int) collect($trend['series'])->max()))
                <div class="matrix-card">
                    <div class="panel-head compact"><h4>{{ $trend['task'] }}</h4><strong>{{ $trend['total'] }}</strong></div>
                    <div class="line-series">
                        @foreach($trend['series'] as $label => $value)
                            <div class="point"><div class="bar" style="height:{{ max(4, ($value / $maxSeries) * 120) }}px"></div><small>{{ $label }}</small><strong>{{ $value }}</strong></div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
