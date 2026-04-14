@extends('layouts.app', ['title' => $title . ' Report'])
@section('content')
@php
    $printSection = $printSection ?? 'overview';
    $grandTotalJobs = $overviewMetrics['total_jobs'] ?? 0;
    $grandTotalHours = $overviewMetrics['total_hours'] ?? 0;
    $grandTotalCost = $overviewMetrics['total_cost'] ?? 0;
    $activeEntityCount = collect($monthlyEntitySummary)->count();
    $activeTaskCount = collect($monthlyTaskSummary)->count();
    $branchPerformance = collect(data_get($combined, 'branchPerformance', []));
    $maxEntityJobs = max(1, (int) $branchPerformance->max('total_jobs'));
    $isArchiveView = $isArchiveView ?? false;
    $archiveRoute = $entityLabel === 'Branch' ? 'admin.reports.branches.archive.show' : 'admin.reports.locations.archive.show';
    $monthlyTaskColumns = collect($months);
@endphp
<style>
@media print{.no-print,.footer-bar,.topbar,.sidebar{display:none !important}.content-shell{padding:0 !important}.panel{box-shadow:none !important;border:none !important}body{background:#fff !important}}
.report-shell{display:grid;gap:24px}.page-header{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;flex-wrap:wrap}.page-header h1{margin:0 0 6px}.page-header p{margin:0;color:#64748b;max-width:920px}.filter-panel,.print-panel,.panel{background:#fff;border:1px solid #dbe7f5;border-radius:24px;box-shadow:0 14px 34px rgba(15,23,42,.05);padding:22px}.filter-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.field-block{display:grid;gap:8px}.field-block span{font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em}.field-block input,.field-block select{width:100%;padding:12px 14px;border:1px solid #d6dfeb;border-radius:14px;background:#fff}.filter-actions,.print-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:18px}.kpi-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:16px}.kpi-card{padding:18px;border:1px solid #dbe7f5;border-radius:18px;background:linear-gradient(180deg,#fff 0%,#f8fbff 100%)}.kpi-card span{display:block;color:#64748b;font-size:12px;text-transform:uppercase;margin-bottom:8px}.kpi-card strong{font-size:28px;line-height:1.1}.panel-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:16px;flex-wrap:wrap}.panel-head h3,.panel-head h4{margin:0}.subtle{font-size:13px;color:#64748b}.report-table-shell{border:1px solid #e2e8f0;border-radius:18px;overflow:auto}.report-table-shell table{width:100%;border-collapse:collapse;min-width:1080px}.report-table-shell th,.report-table-shell td{padding:11px 12px;border-bottom:1px solid #e2e8f0;white-space:nowrap;font-size:13px;text-align:left}.report-table-shell th{background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase;letter-spacing:.03em}.report-table-shell tfoot th,.report-table-shell tfoot td{background:#f8fbff;font-weight:700}.dashboard-grid-2{display:grid;grid-template-columns:1.15fr .85fr;gap:24px}.insight-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.helper-card{padding:16px;border:1px solid #dbe7f5;border-radius:18px;background:#fff}.helper-card span{display:block;color:#64748b;font-size:12px;text-transform:uppercase;margin-bottom:8px}.helper-card strong{font-size:22px}.badge{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:700}.compact-note{font-size:12px;color:#64748b}.list-grid{display:grid;gap:10px;max-height:480px;overflow:auto}.list-row{display:flex;justify-content:space-between;gap:12px;padding:14px 0;border-bottom:1px dashed #dbe7f5}.list-row:last-child{border-bottom:none}.list-actions{display:flex;gap:8px;flex-wrap:wrap}.matrix-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px}.matrix-card{border:1px solid #dbe7f5;border-radius:18px;padding:16px;background:#f8fbff}.line-series{display:flex;align-items:end;gap:8px;min-height:170px}.line-series .point{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px}.line-series .point .bar{width:100%;max-width:24px;border-radius:999px 999px 0 0;background:#93c5fd;min-height:4px}.bar-line{display:grid;gap:10px}.bar-row{display:grid;grid-template-columns:180px 1fr 70px;align-items:center;gap:10px}.bar-track{height:12px;border-radius:999px;background:#e2e8f0;overflow:hidden}.bar-track div{height:100%;background:#2563eb}.section-gap{margin-top:0}.top-gap{margin-top:14px}@media (max-width:1200px){.filter-grid,.kpi-grid,.insight-grid,.dashboard-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}}@media (max-width:760px){.filter-grid,.kpi-grid,.insight-grid,.dashboard-grid-2{grid-template-columns:1fr}}
</style>
<div class="page-header no-print">
    <div>
        <h1>Report / {{ $title }}</h1>
        <p>{{ $isArchiveView ? 'Final archived annual snapshot stored in the system. This page shows the compiled result saved at year end.' : 'Live dashboard for submitted requests. Job totals update as soon as a request is submitted, while total cost and technician hours only count completed workflow data.' }}</p>
    </div>
</div>

<div class="report-shell">
    @unless($isArchiveView)
    <section class="filter-panel no-print">
        <form method="GET">
            <div class="filter-grid">
                <div class="field-block"><span>Year</span><input type="number" name="year" min="2020" max="2100" value="{{ $selectedYear }}"></div>
                <div class="field-block"><span>Status</span><select name="status"><option value="">All Status</option>@foreach($statusOptions as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
                <div class="field-block"><span>State</span><select name="state"><option value="">All States</option>@foreach($availableStates as $state)<option value="{{ $state }}" @selected(($filters['state'] ?? '') === $state)>{{ $state }}</option>@endforeach</select></div>
                <div class="field-block"><span>{{ $entityLabel }}</span><select name="detail_location_id"><option value="">All {{ $entityPlural }}</option>@foreach($locations as $entity)<option value="{{ $entity->id }}" @selected((string)($filters['detail_location_id'] ?? '') === (string)$entity->id)>{{ $entity->name }}</option>@endforeach</select></div>
                <div class="field-block"><span>Date From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
                <div class="field-block"><span>Date To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
                <div class="field-block"><span>Task Title</span><select name="detail_task"><option value="">All Tasks</option>@foreach($taskNames as $taskName)<option value="{{ $taskName }}" @selected(($filters['detail_task'] ?? '') === $taskName)>{{ $taskName }}</option>@endforeach</select></div>
                <div class="field-block"><span>Year Compile</span><select onchange="if(this.value) window.location.href=this.value;"><option value="">Open compiled year</option>@foreach($compiledArchiveYears as $archiveYear)<option value="{{ route($archiveRoute, $archiveYear) }}">{{ $archiveYear }}</option>@endforeach</select></div>
            </div>
            <div class="filter-actions">
                <button class="btn primary" type="submit">Generate Dashboard</button>
                <a class="btn ghost" href="{{ route($reportRouteName) }}">Reset Filter</a>
                <span class="compact-note">Use year, status, date range, {{ strtolower($entityLabel) }}, and task together for a tighter report.</span>
            </div>
        </form>
    </section>

    <section class="print-panel no-print">
        <form method="GET" action="{{ $documentPrintBaseRoute }}">
            @foreach($filters as $key => $value)
                @if(!is_array($value) && $key !== 'print' && $key !== 'print_section')
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <div class="filter-grid" style="grid-template-columns:1.8fr .9fr;align-items:end;">
                <div class="field-block">
                    <span>Print Container</span>
                    <select name="print_section">
                        <option value="overview" @selected($printSection==='overview')>Dashboard Overview</option>
                        <option value="monthly_task" @selected($printSection==='monthly_task')>Monthly Task Summary</option>
                        <option value="monthly_entity" @selected($printSection==='monthly_entity')>Monthly {{ $entityLabel }} Summary</option>
                        <option value="detail" @selected($printSection==='detail')>{{ $entityLabel }} &amp; Task Detail</option>
                        <option value="combined" @selected($printSection==='combined')>Combined Statistics</option>
                    </select>
                </div>
                <div class="print-actions"><button class="btn primary" type="submit">Print Documents</button></div>
            </div>
        </form>
    </section>
    @else
    <section class="filter-panel no-print">
        <div class="panel-head" style="margin-bottom:0;">
            <div>
                <h3>Compiled Year {{ $selectedYear }}</h3>
                <div class="subtle">Saved automatically at year end. Current live dashboard resets on the next year while this archived result stays unchanged.</div>
            </div>
            <a class="btn ghost" href="{{ route($reportRouteName, ['year' => $selectedYear, 'print' => 1]) }}" target="_blank">Print Documents</a>
        </div>
    </section>
    @endunless

    <section class="kpi-grid">
        <article class="kpi-card"><span>Total Task Bulanan</span><strong>{{ $activeTaskCount }}</strong></article>
        <article class="kpi-card"><span>Total {{ $entityLabel }} Bulanan</span><strong>{{ $activeEntityCount }}</strong></article>
        <article class="kpi-card"><span>Total Job</span><strong>{{ $grandTotalJobs }}</strong></article>
        <article class="kpi-card"><span>Total Hours</span><strong>{{ number_format($grandTotalHours, 2) }}</strong></article>
        <article class="kpi-card"><span>Grand Total Cost</span><strong>RM {{ number_format($grandTotalCost, 2) }}</strong></article>
    </section>

    <section class="panel">
        <div class="panel-head"><div><h3>Monthly Task Summary</h3><div class="subtle">Pivot table with monthly totals, average, completed hours, and approved cost.</div></div><span class="badge">Pivot</span></div>
        <div class="report-table-shell">
            <table>
                <thead><tr><th>Task Title</th>@foreach($months as $monthNumber => $label)<th>{{ $label }}</th>@endforeach<th>Total</th><th>Average / Month</th><th>Total Hours</th><th>Total Cost</th></tr></thead>
                <tbody>
                    @forelse($monthlyTaskSummary as $row)
                        <tr>
                            <td>{{ data_get($row, 'task_name') }}</td>
                            @foreach($months as $monthNumber => $label)
                                <td>{{ data_get($row, "months.$monthNumber", 0) }}</td>
                            @endforeach
                            <td><strong>{{ data_get($row, 'total', 0) }}</strong></td>
                            <td>{{ number_format((float) data_get($row, 'average_month', (data_get($row, 'total', 0) / 12)), 2) }}</td>
                            <td>{{ number_format((float) data_get($row, 'total_hours', 0), 2) }}</td>
                            <td>RM {{ number_format((float) data_get($row, 'total_cost', 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="17">No task statistics available for the selected filter.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><th>Grand Total</th>@foreach($months as $monthNumber => $label)<th>{{ collect($monthlyTaskSummary)->sum(fn ($row) => data_get($row, "months.$monthNumber", 0)) }}</th>@endforeach<th>{{ collect($monthlyTaskSummary)->sum('total') }}</th><th>{{ number_format(collect($monthlyTaskSummary)->sum('total') / 12, 2) }}</th><th>{{ number_format(collect($monthlyTaskSummary)->sum('total_hours'), 2) }}</th><th>RM {{ number_format(collect($monthlyTaskSummary)->sum('total_cost'), 2) }}</th></tr></tfoot>
            </table>
        </div>
    </section>

    <section class="panel top-gap">
        <div class="panel-head"><div><h3>Monthly {{ $entityLabel }} Summary</h3><div class="subtle">Pivot table with monthly totals, average, completed hours, and approved cost.</div></div><span class="badge">Pivot</span></div>
        <div class="report-table-shell">
            <table>
                <thead><tr><th>{{ $entityLabel }}</th>@foreach($months as $monthNumber => $label)<th>{{ $label }}</th>@endforeach<th>Total</th><th>Average / Month</th><th>Total Hours</th><th>Total Cost</th></tr></thead>
                <tbody>
                    @forelse($monthlyEntitySummary as $row)
                        <tr>
                            <td>{{ data_get($row, 'branch.name') ?? data_get($row, 'branch->name') }}</td>
                            @foreach($months as $monthNumber => $label)
                                <td>{{ data_get($row, "months.$monthNumber", 0) }}</td>
                            @endforeach
                            <td><strong>{{ data_get($row, 'total', 0) }}</strong></td>
                            <td>{{ number_format((float) data_get($row, 'average_month', (data_get($row, 'total', 0) / 12)), 2) }}</td>
                            <td>{{ number_format((float) data_get($row, 'total_hours', 0), 2) }}</td>
                            <td>RM {{ number_format((float) data_get($row, 'total_cost', 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="17">No {{ strtolower($entityLabel) }} statistics available for the selected filter.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><th>Grand Total</th>@foreach($months as $monthNumber => $label)<th>{{ collect($monthlyEntitySummary)->sum(fn ($row) => data_get($row, "months.$monthNumber", 0)) }}</th>@endforeach<th>{{ collect($monthlyEntitySummary)->sum('total') }}</th><th>{{ number_format(collect($monthlyEntitySummary)->sum('total') / 12, 2) }}</th><th>{{ number_format(collect($monthlyEntitySummary)->sum('total_hours'), 2) }}</th><th>RM {{ number_format(collect($monthlyEntitySummary)->sum('total_cost'), 2) }}</th></tr></tfoot>
            </table>
        </div>
    </section>

    <div class="dashboard-grid-2 top-gap" id="detail-section">
        <section class="panel">
            <div class="panel-head"><div><h3>{{ $entityLabel }} &amp; Task Detail</h3><div class="subtle">Filtered detail, totals, averages, and request-level rows.</div></div><span class="badge">Detail</span></div>
            <div class="insight-grid" style="margin-bottom:16px;">
                <article class="helper-card"><span>Total Jobs</span><strong>{{ data_get($detail, 'summary.total_jobs', 0) }}</strong></article>
                <article class="helper-card"><span>Total Hours</span><strong>{{ number_format((float) data_get($detail, 'summary.total_hours', 0), 2) }}</strong></article>
                <article class="helper-card"><span>Total Cost</span><strong>RM {{ number_format((float) data_get($detail, 'summary.total_cost', 0), 2) }}</strong></article>
                <article class="helper-card"><span>Average Cost / Job</span><strong>RM {{ number_format(data_get($detail, 'summary.total_jobs', 0) ? (float) data_get($detail, 'summary.total_cost', 0) / max(1, (int) data_get($detail, 'summary.total_jobs', 0)) : 0, 2) }}</strong></article>
            </div>
            <div class="report-table-shell">
                <table>
                    <thead><tr><th>Task Title</th><th>Total Job</th><th>Total Hours</th><th>Total Cost</th><th>Total per Task</th></tr></thead>
                    <tbody>
                        @forelse(data_get($detail, 'rows', []) as $row)
                            <tr><td>{{ data_get($row, 'task') }}</td><td>{{ data_get($row, 'total_job', 0) }}</td><td>{{ number_format((float) data_get($row, 'total_hours', 0), 2) }}</td><td>RM {{ number_format((float) data_get($row, 'total_cost', 0), 2) }}</td><td>RM {{ number_format((float) data_get($row, 'total_per_task', 0), 2) }}</td></tr>
                        @empty
                            <tr><td colspan="5">No detail rows available for the selected filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><div><h3>Filtered Request List</h3><div class="subtle">Request records inside the current filter.</div></div></div>
            <div class="list-grid">
                @forelse(data_get($detail, 'jobs', []) as $job)
                    @php($jobCode = is_array($job) ? data_get($job, 'request_code') : $job->request_code)
                    @php($jobEntity = is_array($job) ? data_get($job, 'location_name') : ($job->location?->name ?? '-'))
                    @php($jobTask = is_array($job) ? data_get($job, 'task_title') : ($job->primaryTaskTitleName() ?? ($job->requestType?->name ?? '-')))
                    @php($jobStatus = is_array($job) ? data_get($job, 'status') : $job->adminWorkflowLabel())
                    @php($jobSubmitted = is_array($job) ? data_get($job, 'submitted_at') : $job->created_at?->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y h:i A'))
                    <div class="list-row">
                        <div>
                            <strong>{{ $jobCode }}</strong>
                            <div class="compact-note">{{ $jobEntity }} / {{ $jobTask }} / {{ $jobStatus }}</div>
                            <div class="compact-note">Submitted {{ $jobSubmitted }}</div>
                        </div>
                        @if(!is_array($job))
                        <div class="list-actions no-print">
                            <a class="btn small ghost" href="{{ route('admin.incoming-requests.show', $job) }}">View</a>
                            <a class="btn small ghost" href="{{ route('admin.incoming-requests.print', $job) }}" target="_blank">Print</a>
                        </div>
                        @endif
                    </div>
                @empty
                    <div class="compact-note">No request found for this detail filter.</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="panel top-gap">
        <div class="panel-head"><div><h3>Combined Statistics</h3><div class="subtle">Useful for management overview, allocation planning, and annual review.</div></div><span class="badge">Advanced</span></div>
        <div class="dashboard-grid-2">
            <div class="helper-card">
                <h4 style="margin:0 0 12px;">Task × {{ $entityLabel }} Matrix</h4>
                <div class="report-table-shell">
                    <table>
                        <thead><tr><th>Task</th>@foreach($branchPerformance->take(12) as $entityRow)<th>{{ data_get($entityRow, 'entity.name') ?? data_get($entityRow, 'entity->name') }}</th>@endforeach<th>Total</th></tr></thead>
                        <tbody>
                            @forelse(data_get($combined, 'taskBranchMatrix', []) as $row)
                                <tr><td>{{ data_get($row, 'task') }}</td>@foreach($branchPerformance->take(12) as $entityRow)<td>{{ data_get($row, 'entities.' . (data_get($entityRow, 'entity.name') ?? data_get($entityRow, 'entity->name')), 0) }}</td>@endforeach<td><strong>{{ collect(data_get($row, 'entities', []))->sum() }}</strong></td></tr>
                            @empty
                                <tr><td colspan="14">No matrix data available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="helper-card">
                <h4 style="margin:0 0 12px;">{{ $entityLabel }} Performance</h4>
                <div class="bar-line">
                    @forelse($branchPerformance->take(12) as $row)
                        <div class="bar-row"><span>{{ data_get($row, 'entity.name') ?? data_get($row, 'entity->name') }}</span><div class="bar-track"><div style="width:{{ ((float) data_get($row, 'total_jobs', 0) / $maxEntityJobs) * 100 }}%"></div></div><strong>{{ data_get($row, 'total_jobs', 0) }}</strong></div>
                    @empty
                        <div class="compact-note">No performance data available.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="matrix-grid" style="margin-top:18px;">
            @foreach(collect(data_get($combined, 'taskTrend', []))->take(6) as $trend)
                @php($maxSeries = max(1, (int) collect(data_get($trend, 'series', []))->max()))
                <div class="matrix-card">
                    <div class="panel-head" style="margin-bottom:10px;"><h4>{{ data_get($trend, 'task') }}</h4><strong>{{ data_get($trend, 'total', 0) }}</strong></div>
                    <div class="line-series">
                        @foreach(data_get($trend, 'series', []) as $label => $value)
                            <div class="point"><div class="bar" style="height:{{ max(4, ($value / $maxSeries) * 120) }}px"></div><small>{{ $label }}</small><strong>{{ $value }}</strong></div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
