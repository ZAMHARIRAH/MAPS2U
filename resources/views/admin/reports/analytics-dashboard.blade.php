@extends('layouts.app', ['title' => ($title ?? 'Analytics') . ' Report'])

@section('content')
@php
    $printSection = $printSection ?? 'overview';
    $isArchiveView = $isArchiveView ?? false;
    $entityLabel = $entityLabel ?? 'Branch';
    $entityPlural = $entityPlural ?? ($entityLabel . 'es');
    $filters = $filters ?? [];
    $months = collect($months ?? []);
    $locations = collect($locations ?? []);
    $taskNames = collect($taskNames ?? []);
    $statusOptions = collect($statusOptions ?? []);
    $monthlyEntitySummary = collect($monthlyEntitySummary ?? []);
    $monthlyTaskSummary = collect($monthlyTaskSummary ?? []);
    $overviewMetrics = $overviewMetrics ?? [];
    $combined = $combined ?? [];
    $extended = collect($extended ?? []);
    $detail = $detail ?? [];
    $compiledArchiveYears = collect($compiledArchiveYears ?? []);

    $grandTotalJobs = (int) data_get($overviewMetrics, 'total_jobs', 0);
    $grandTotalHours = (float) data_get($overviewMetrics, 'total_hours', 0);
    $grandTotalCost = (float) data_get($overviewMetrics, 'total_cost', 0);
    $archiveRoute = $entityLabel === 'Branch' ? 'admin.reports.branches.archive.show' : 'admin.reports.locations.archive.show';

    $taskEntityMatrix = collect($extended->get('taskEntityMatrix', []));
    $taskCountGraph = collect($extended->get('taskCountGraph', []));
    $taskAmountTable = collect($extended->get('taskAmountTable', []));
    $entityAmountTable = collect($extended->get('entityAmountTable', []));
    $taskAmountGraph = collect($extended->get('taskAmountGraph', []));
    $entityStatusMatrix = collect($extended->get('entityStatusMatrix', []));
    $entityStatusGraph = collect($extended->get('entityStatusGraph', []));
    $technicianCsrGraph = collect($extended->get('technicianCsrGraph', []));
    $technicianStatusMatrix = collect($extended->get('technicianStatusMatrix', []));
    $entityHoursGraph = collect($extended->get('entityHoursGraph', []));
    $branchPerformance = collect(data_get($combined, 'branchPerformance', []));
    $selectedDetailId = (string)($filters['detail_location_id'] ?? $filters['location_id'] ?? '');
    $selectedDetailEntity = $selectedDetailId !== '' ? $locations->first(fn($entity) => (string) $entity->id === $selectedDetailId) : null;
    $selectedDetailName = $selectedDetailEntity?->name ?? ('Selected ' . $entityLabel);

    $maxTaskCount = max(1, (int) $taskCountGraph->max('count'));
    $maxTaskAmount = max(1, (float) $taskAmountGraph->max('amount'));
    $maxCsr = max(1, (int) $technicianCsrGraph->max('count'));
    $maxEntityHours = max(1, (float) $entityHoursGraph->max('hours'));
    $maxEntityJobs = max(1, (int) $branchPerformance->max('total_jobs'));
    $statusPalette = [
        'Under Review' => '#2563eb',
        'Returned for Update' => '#dc2626',
        'Pending Approval' => '#f59e0b',
        'Approved' => '#16a34a',
        'Work In Progress' => '#f97316',
        'Pending Technician Feedback' => '#0ea5e9',
        'Client Has Returned Request' => '#7c3aed',
        'Finance Pending' => '#ca8a04',
        'Completed' => '#22c55e',
        'Rejected' => '#991b1b',
        'Subject To Approval' => '#db2777',
    ];
@endphp

<style>
@@media print{
    @page{size:landscape;margin:10mm}
    .no-print,.footer-bar,.topbar,.sidebar,.panel-tools,.filter-panel,.list-actions{display:none!important}
    body{background:#fff!important;color:#111827!important}
    .content-shell{padding:0!important;margin:0!important}
    .report-shell{display:block!important;gap:0!important}
    .panel,.kpi-card,.helper-card{box-shadow:none!important;border:0!important;border-radius:0!important;background:#fff!important;padding:0!important;margin:0 0 14px!important;break-inside:avoid}
    .panel-head{display:block!important;margin-bottom:8px!important;border-bottom:1px solid #111827!important;padding-bottom:5px!important}
    .panel-head h3,.panel-head h4{font-size:15px!important;margin:0!important;color:#111827!important}
    .subtle,.compact-note{font-size:10px!important;color:#374151!important}
    .report-table-shell{overflow:visible!important;border:0!important;border-radius:0!important}
    .report-table-shell table{min-width:0!important;width:100%!important;border-collapse:collapse!important;table-layout:auto!important}
    .report-table-shell th,.report-table-shell td{border:1px solid #111827!important;padding:4px 5px!important;font-size:9px!important;white-space:normal!important;color:#111827!important;background:#fff!important}
    .compact-matrix th,.compact-matrix td{padding:3px 4px!important;font-size:7.5px!important;line-height:1.1!important;min-width:0!important;max-width:none!important}
    .compact-matrix th:first-child,.compact-matrix td:first-child{position:static!important;min-width:110px!important;background:#fff!important}
    .vertical-chart,.horizontal-chart{overflow:visible!important;break-inside:avoid}
    .hbar{grid-template-columns:220px 1fr 70px!important}
    .detail-grid,.dashboard-grid-2,.two-table-grid{display:block!important}
    .entity-pick-list{max-height:none!important;overflow:visible!important}
    .entity-pick{border:1px solid #111827!important;border-radius:0!important;padding:5px!important}
    body.print-section-mode [data-print-section]{display:none!important}
    body.print-section-mode [data-print-section].printing{display:block!important}
    body.print-section-mode [data-print-section].printing .report-table-shell,body.print-section-mode [data-print-section].printing .list-grid{max-height:none!important;overflow:visible!important}
    body.print-section-mode #detail-section.printing .detail-selector,body.print-section-mode #detail-section.printing .detail-summary-cards{display:none!important}
    body.print-section-mode #detail-section.printing .print-detail-title{display:block!important;margin:0 0 10px!important;padding:0 0 6px!important;border-bottom:1px solid #111827!important;font-size:16px!important;font-weight:800!important;color:#111827!important}
    body.print-section-mode #detail-section.printing .detail-grid{display:block!important}
    body.print-section-mode #detail-section.printing .detail-result-panel{display:block!important;width:100%!important}
}
.report-shell{display:grid;gap:22px}.page-header{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;flex-wrap:wrap}.page-header h1{margin:0 0 6px}.page-header p,.subtle,.compact-note{color:#64748b}.filter-panel,.panel{background:#fff;border:1px solid #dbe7f5;border-radius:24px;box-shadow:0 14px 34px rgba(15,23,42,.05);padding:22px}.filter-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.field-block{display:grid;gap:8px}.field-block span{font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase}.field-block input,.field-block select{width:100%;padding:12px 14px;border:1px solid #d6dfeb;border-radius:14px;background:#fff}.filter-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:18px}.kpi-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:16px}.kpi-card{padding:18px;border:1px solid #dbe7f5;border-radius:18px;background:linear-gradient(180deg,#fff 0%,#f8fbff 100%)}.kpi-card span{display:block;color:#64748b;font-size:12px;text-transform:uppercase;margin-bottom:8px}.kpi-card strong{font-size:28px}.panel-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:16px;flex-wrap:wrap}.panel-head h3,.panel-head h4{margin:0}.badge{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:700}.report-table-shell{border:1px solid #e2e8f0;border-radius:18px;overflow:auto}.report-table-shell table{width:100%;border-collapse:collapse;min-width:980px}.report-table-shell th,.report-table-shell td{padding:11px 12px;border-bottom:1px solid #e2e8f0;white-space:nowrap;font-size:13px;text-align:left}.report-table-shell th{background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase}.report-table-shell tfoot th,.report-table-shell tfoot td{background:#f8fbff;font-weight:700}.dashboard-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:22px}.two-table-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.insight-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.helper-card{padding:16px;border:1px solid #dbe7f5;border-radius:18px;background:#fff}.helper-card span{display:block;color:#64748b;font-size:12px;text-transform:uppercase;margin-bottom:8px}.helper-card strong{font-size:22px}.vertical-chart{display:flex;align-items:flex-end;gap:14px;min-height:250px;overflow-x:auto;padding:12px 4px}.vbar{display:flex;flex-direction:column;align-items:center;gap:8px;min-width:74px}.vbar .bar{width:34px;border-radius:12px 12px 4px 4px;background:#2563eb;min-height:4px}.vbar small{font-size:11px;color:#475569;text-align:center;max-width:90px;white-space:normal}.horizontal-chart{display:grid;gap:10px}.hbar{display:grid;grid-template-columns:210px 1fr 90px;gap:10px;align-items:center}.hbar span{font-size:12px;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.hbar .track{height:14px;background:#e2e8f0;border-radius:999px;overflow:hidden}.hbar .fill{height:100%;border-radius:999px;background:#2563eb}.status-stack{display:flex;height:18px;border-radius:999px;overflow:hidden;background:#e2e8f0}.status-segment{height:100%}.list-grid{display:grid;gap:10px;max-height:480px;overflow:auto}.list-row{display:flex;justify-content:space-between;gap:12px;padding:14px 0;border-bottom:1px dashed #dbe7f5}.matrix-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px}.matrix-card{border:1px solid #dbe7f5;border-radius:18px;padding:16px;background:#f8fbff}.line-series{display:flex;align-items:end;gap:8px;min-height:170px}.line-series .point{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px}.line-series .bar{width:100%;max-width:24px;border-radius:999px 999px 0 0;background:#93c5fd;min-height:4px}.top-gap{margin-top:0}.print-detail-title{display:none}.entity-pick-list{display:grid;gap:8px}.entity-pick{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:11px 12px;border:1px solid #dbe7f5;border-radius:14px;text-decoration:none;color:#0f172a;background:#fff}.entity-pick.active{border-color:#2563eb;background:#eff6ff}.detail-grid{display:grid;grid-template-columns:320px 1fr;gap:18px}.detail-result-panel{min-width:0}@media(max-width:1200px){.filter-grid,.kpi-grid,.insight-grid,.dashboard-grid-2,.two-table-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.filter-grid,.kpi-grid,.insight-grid,.dashboard-grid-2,.two-table-grid{grid-template-columns:1fr}}
.panel-tools{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.compact-matrix table{min-width:max-content}.compact-matrix th,.compact-matrix td{padding:7px 8px;font-size:11px;line-height:1.2;text-align:center}.compact-matrix th:first-child,.compact-matrix td:first-child{text-align:left;position:sticky;left:0;background:#fff;z-index:2;min-width:210px}.compact-matrix th:first-child{background:#f8fafc}.compact-matrix th:not(:first-child),.compact-matrix td:not(:first-child){min-width:46px;max-width:none;white-space:normal;word-break:break-word}.hours-chart .hbar{grid-template-columns:300px 1fr 100px}.hours-chart .hbar span{font-size:14px;font-weight:700;color:#0f172a;white-space:normal}.detail-grid{display:grid;grid-template-columns:320px 1fr;gap:18px}.entity-pick-list{display:grid;gap:8px;max-height:560px;overflow:auto}.entity-pick{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:11px 12px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;color:#0f172a;text-decoration:none}.entity-pick.active{border-color:#2563eb;background:#eff6ff}.entity-pick strong{font-size:13px}.entity-pick span{font-size:12px;color:#64748b}@media(max-width:1200px){.detail-grid{grid-template-columns:1fr}}

.report-view-switch{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px}
.report-view-switch .btn.active{background:#1d4ed8;color:#fff;border-color:#1d4ed8}
.report-graph-shell{display:none;border:1px solid #e2e8f0;border-radius:18px;background:#fff;padding:18px;overflow:auto}
.report-graph-title{font-size:12px;font-weight:800;color:#475569;text-transform:uppercase;margin:0 0 12px}
.generated-chart{min-width:760px}
.generated-bar-chart{position:relative;display:flex;align-items:flex-end;gap:14px;min-height:340px;padding:30px 16px 70px 54px;border-left:2px solid #334155;border-bottom:2px solid #334155;background:repeating-linear-gradient(to top,#fff 0,#fff 67px,#e2e8f0 68px)}
.generated-axis-label{position:absolute;font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.04em}
.generated-axis-y{left:-36px;top:48%;transform:rotate(-90deg)}
.generated-axis-x{right:18px;bottom:10px}
.generated-y-tick{position:absolute;left:8px;right:0;border-top:1px dashed #cbd5e1;color:#64748b;font-size:10px;line-height:1;pointer-events:none}
.generated-y-tick span{position:absolute;left:-46px;top:-6px;width:38px;text-align:right}
.generated-bar-item{height:260px;min-width:48px;max-width:72px;flex:1;display:flex;align-items:flex-end;justify-content:center;position:relative}
.generated-bar-fill{width:100%;max-width:46px;border-radius:10px 10px 0 0;background:#2563eb;min-height:3px;box-shadow:0 10px 18px rgba(37,99,235,.14)}
.generated-bar-value{position:absolute;top:-22px;left:50%;transform:translateX(-50%);font-size:11px;font-weight:900;color:#0f172a;white-space:nowrap}
.generated-bar-label{position:absolute;bottom:-58px;left:50%;width:88px;transform:translateX(-50%) rotate(-35deg);transform-origin:top center;font-size:10px;font-weight:700;color:#334155;text-align:right;line-height:1.15;white-space:normal;overflow:hidden;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical}
.generated-empty{font-size:13px;color:#64748b;padding:12px}
body.report-graph-mode .report-table-shell{display:none}
body.report-graph-mode .report-graph-shell{display:block}
body.report-table-mode .report-table-shell{display:block}
body.report-table-mode .report-graph-shell{display:none}
body.report-graph-mode .panel-tools .view-mode-label::after{content:'Graph view'}
body.report-table-mode .panel-tools .view-mode-label::after{content:'Table view'}
@@media print{
    body.report-graph-mode .report-table-shell{display:none!important}
    body.report-graph-mode .report-graph-shell{display:block!important;border:0!important;padding:0!important;overflow:visible!important}
    body.report-table-mode .report-table-shell{display:block!important}
    body.report-table-mode .report-graph-shell{display:none!important}
    .generated-chart{min-width:0!important}
    .generated-bar-chart{min-height:230px!important;padding:22px 8px 54px 40px!important;gap:7px!important;background:repeating-linear-gradient(to top,#fff 0,#fff 44px,#d1d5db 45px)!important;border-left:1px solid #111827!important;border-bottom:1px solid #111827!important}
    .generated-bar-item{height:170px!important;min-width:28px!important;max-width:42px!important}
    .generated-bar-fill{background:#111827!important;box-shadow:none!important;max-width:28px!important}
    .generated-bar-value{font-size:7px!important;color:#111827!important;top:-14px!important}
    .generated-bar-label{font-size:6.5px!important;color:#111827!important;width:58px!important;bottom:-42px!important}
    .generated-y-tick{font-size:6.5px!important;color:#111827!important}
    .generated-y-tick span{left:-34px!important;width:28px!important}
    .generated-axis-label{font-size:7px!important;color:#111827!important}
}

</style>

<div class="page-header no-print">
    <div>
        <h1>Report / {{ $title }}</h1>
        <p></p>
        <div class="report-view-switch" aria-label="Report view mode">
            <button class="btn small ghost active" type="button" data-report-view="table">Table View</button>
            <button class="btn small ghost" type="button" data-report-view="graph">Graph View</button>
        </div>
    </div>
</div>

<div class="report-shell">
    @if(!$isArchiveView)
        <section class="filter-panel no-print">
            <form method="GET">
                <div class="filter-grid">
                    <div class="field-block"><span>Year</span><input type="number" name="year" min="2020" max="2100" value="{{ $selectedYear }}"></div>
                    <div class="field-block"><span>Month Filter</span><input type="month" name="month" value="{{ $filters['month'] ?? '' }}"></div>
                    <div class="field-block"><span>Status</span><select name="status"><option value="">All Status</option>@foreach($statusOptions as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
                    <div class="field-block"><span>{{ $entityLabel }}</span><select name="detail_location_id"><option value="">All {{ $entityPlural }}</option>@foreach($locations as $entity)<option value="{{ $entity->id }}" @selected((string)($filters['detail_location_id'] ?? '') === (string)$entity->id)>{{ $entity->name }}</option>@endforeach</select></div>
                    <div class="field-block"><span>Date From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
                    <div class="field-block"><span>Date To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
                    <div class="field-block"><span>Task Title</span><select name="detail_task"><option value="">All Tasks</option>@foreach($taskNames as $taskName)<option value="{{ $taskName }}" @selected(($filters['detail_task'] ?? '') === $taskName)>{{ $taskName }}</option>@endforeach</select></div>
                    <div class="field-block"><span>Year Compile</span><select onchange="if(this.value) window.location.href=this.value;"><option value="">Open compiled year</option>@foreach($compiledArchiveYears as $archiveYear)<option value="{{ route($archiveRoute, $archiveYear) }}">{{ $archiveYear }}</option>@endforeach</select></div>
                </div>
                <div class="filter-actions">
                    <button class="btn primary" type="submit">Generate Dashboard</button>
                    <a class="btn ghost" href="{{ route($reportRouteName) }}">Reset Filter</a>
                    <button class="btn ghost" type="button" onclick="window.print()">Print Full Filtered Report</button>
                </div>
            </form>
        </section>
    @else
        <section class="filter-panel no-print">
            <div class="panel-head" style="margin-bottom:0;">
                <div><h3>Compiled Year {{ $selectedYear }}</h3><div class="subtle">Saved automatically at year end.</div></div>
                <a class="btn ghost" href="{{ route($reportRouteName, ['year' => $selectedYear, 'print' => 1]) }}" target="_blank">Print Documents</a>
            </div>
        </section>
    @endif

    <section class="kpi-grid" id="section-overview" data-print-section="overview">
        <article class="kpi-card"><span>Total Monthly Task</span><strong>{{ $monthlyTaskSummary->count() }}</strong></article>
        <article class="kpi-card"><span>Total Monthly {{ $entityLabel }} </span><strong>{{ $monthlyEntitySummary->count() }}</strong></article>
        <article class="kpi-card"><span>Total Job</span><strong>{{ $grandTotalJobs }}</strong></article>
        <article class="kpi-card"><span>Total Hours</span><strong>{{ number_format($grandTotalHours, 2) }}</strong></article>
        <article class="kpi-card"><span>Grand Total Cost</span><strong>RM {{ number_format($grandTotalCost, 2) }}</strong></article>
    </section>

    <section class="panel" id="section-monthly-task" data-print-section="monthly-task">
        <div class="panel-head"><div><h3>Monthly Task Summary</h3><div class="subtle">  </div></div><div class="panel-tools no-print"><span class="badge">Pivot</span><button class="btn small ghost" type="button" onclick="printReportSection('section-monthly-task')">Print Data</button></div></div>
        <div class="report-table-shell">
            <table>
                <thead><tr><th>Task Title</th>@foreach($months as $monthNumber => $label)<th>{{ $label }}</th>@endforeach<th>Total</th><th>Average / Month</th><th>Total Hours</th><th>Total Cost</th></tr></thead>
                <tbody>
                    @forelse($monthlyTaskSummary as $row)
                        <tr>
                            <td>{{ data_get($row, 'task_name') }}</td>
                            @foreach($months as $monthNumber => $label)<td>{{ data_get($row, 'months.' . $monthNumber, 0) }}</td>@endforeach
                            <td><strong>{{ data_get($row, 'total', 0) }}</strong></td>
                            <td>{{ number_format((float) data_get($row, 'average_month', data_get($row, 'total', 0) / 12), 2) }}</td>
                            <td>{{ number_format((float) data_get($row, 'total_hours', 0), 2) }}</td>
                            <td>RM {{ number_format((float) data_get($row, 'total_cost', 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="17">No task statistics available.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><th>Grand Total</th>@foreach($months as $monthNumber => $label)<th>{{ $monthlyTaskSummary->sum(fn($row) => data_get($row, 'months.' . $monthNumber, 0)) }}</th>@endforeach<th>{{ $monthlyTaskSummary->sum('total') }}</th><th>{{ number_format($monthlyTaskSummary->sum('total') / 12, 2) }}</th><th>{{ number_format($monthlyTaskSummary->sum('total_hours'), 2) }}</th><th>RM {{ number_format($monthlyTaskSummary->sum('total_cost'), 2) }}</th></tr></tfoot>
            </table>
        </div>
    </section>

    <section class="panel top-gap" id="section-monthly-entity" data-print-section="monthly-entity">
        <div class="panel-head"><div><h3>Monthly {{ $entityLabel }} Summary</h3><div class="subtle">  </div></div><div class="panel-tools no-print"><span class="badge">Pivot</span><button class="btn small ghost" type="button" onclick="printReportSection('section-monthly-entity')">Print Data</button></div></div>
        <div class="report-table-shell">
            <table>
                <thead><tr><th>{{ $entityLabel }}</th>@foreach($months as $monthNumber => $label)<th>{{ $label }}</th>@endforeach<th>Total</th><th>Average / Month</th><th>Total Hours</th><th>Total Cost</th></tr></thead>
                <tbody>
                    @forelse($monthlyEntitySummary as $row)
                        <tr>
                            <td>{{ data_get($row, 'branch.name') ?? data_get($row, 'branch->name') }}</td>
                            @foreach($months as $monthNumber => $label)<td>{{ data_get($row, 'months.' . $monthNumber, 0) }}</td>@endforeach
                            <td><strong>{{ data_get($row, 'total', 0) }}</strong></td>
                            <td>{{ number_format((float) data_get($row, 'average_month', data_get($row, 'total', 0) / 12), 2) }}</td>
                            <td>{{ number_format((float) data_get($row, 'total_hours', 0), 2) }}</td>
                            <td>RM {{ number_format((float) data_get($row, 'total_cost', 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="17">No {{ strtolower($entityLabel) }} statistics available.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><th>Grand Total</th>@foreach($months as $monthNumber => $label)<th>{{ $monthlyEntitySummary->sum(fn($row) => data_get($row, 'months.' . $monthNumber, 0)) }}</th>@endforeach<th>{{ $monthlyEntitySummary->sum('total') }}</th><th>{{ number_format($monthlyEntitySummary->sum('total') / 12, 2) }}</th><th>{{ number_format($monthlyEntitySummary->sum('total_hours'), 2) }}</th><th>RM {{ number_format($monthlyEntitySummary->sum('total_cost'), 2) }}</th></tr></tfoot>
            </table>
        </div>
    </section>

    <section class="panel top-gap" id="section-task-entity-matrix" data-print-section="task-entity-matrix">
        <div class="panel-head"><div><h3>Task Title by {{ $entityLabel }}</h3><div class="subtle"> </div></div><div class="panel-tools no-print"><span class="badge">Matrix</span><button class="btn small ghost" type="button" onclick="printReportSection('section-task-entity-matrix')">Print Data</button></div></div>
        <div class="report-table-shell compact-matrix">
            <table>
                <thead><tr><th>{{ $entityLabel }}</th>@foreach($taskNames as $taskName)<th title="{{ $taskName }}">{{ $taskName }}</th>@endforeach<th>Total</th></tr></thead>
                <tbody>
                    @forelse($taskEntityMatrix as $row)
                        <tr><td>{{ data_get($row, 'entity.name') ?? data_get($row, 'entity->name') }}</td>@foreach($taskNames as $taskName)<td>{{ data_get($row, 'counts.' . $taskName, 0) }}</td>@endforeach<td><strong>{{ data_get($row, 'total', 0) }}</strong></td></tr>
                    @empty
                        <tr><td colspan="{{ $taskNames->count() + 2 }}">No matrix data available.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><th>Grand Total</th>@foreach($taskNames as $taskName)<th>{{ $taskEntityMatrix->sum(fn($row) => data_get($row, 'counts.' . $taskName, 0)) }}</th>@endforeach<th>{{ $taskEntityMatrix->sum('total') }}</th></tr></tfoot>
            </table>
        </div>
    </section>

    <section class="dashboard-grid-2 top-gap">
        <div class="panel" id="section-record-count" data-print-section="record-count">
            <div class="panel-head"><div><h3>Record Count by Task Title</h3><div class="subtle">Y axis = count, X axis = task title.</div></div><div class="panel-tools no-print"><span class="badge">Graph</span><button class="btn small ghost" type="button" onclick="printReportSection('section-record-count')">Print Data</button></div></div>
            <div class="vertical-chart">
                @forelse($taskCountGraph as $row)
                    <div class="vbar"><strong>{{ data_get($row, 'count', 0) }}</strong><div class="bar" style="height:{{ max(4, ((int) data_get($row, 'count', 0) / $maxTaskCount) * 210) }}px"></div><small>{{ data_get($row, 'task') }}</small></div>
                @empty
                    <div class="compact-note">No record count data.</div>
                @endforelse
            </div>
        </div>
        <div class="panel" id="section-payment-graph" data-print-section="payment-graph">
            <div class="panel-head"><div><h3>Payment Amount by Task Title</h3><div class="subtle">Y axis = payment amount, X axis = task title.</div></div><div class="panel-tools no-print"><span class="badge">Graph</span><button class="btn small ghost" type="button" onclick="printReportSection('section-payment-graph')">Print Data</button></div></div>
            <div class="vertical-chart">
                @forelse($taskAmountGraph as $row)
                    <div class="vbar"><strong>RM {{ number_format((float) data_get($row, 'amount', 0), 0) }}</strong><div class="bar" style="height:{{ max(4, ((float) data_get($row, 'amount', 0) / $maxTaskAmount) * 210) }}px"></div><small>{{ data_get($row, 'task') }}</small></div>
                @empty
                    <div class="compact-note">No payment amount data.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="two-table-grid top-gap">
        <div class="panel" id="section-amount-task" data-print-section="amount-task">
            <div class="panel-head"><h3>Approved Amount by Task Title</h3><div class="panel-tools no-print"><button class="btn small ghost" type="button" onclick="printReportSection('section-amount-task')">Print Data</button></div></div>
            <div class="report-table-shell"><table><thead><tr><th>Task Title</th><th>Amount</th></tr></thead><tbody>
                @forelse($taskAmountTable as $row)<tr><td>{{ data_get($row, 'task') }}</td><td>RM {{ number_format((float) data_get($row, 'amount', 0), 2) }}</td></tr>@empty<tr><td colspan="2">No amount data.</td></tr>@endforelse
            </tbody><tfoot><tr><th>Grand Total</th><th>RM {{ number_format($taskAmountTable->sum('amount'), 2) }}</th></tr></tfoot></table></div>
        </div>
        <div class="panel" id="section-amount-entity" data-print-section="amount-entity">
            <div class="panel-head"><h3>Approved Amount by {{ $entityLabel }}</h3><div class="panel-tools no-print"><button class="btn small ghost" type="button" onclick="printReportSection('section-amount-entity')">Print Data</button></div></div>
            <div class="report-table-shell"><table><thead><tr><th>{{ $entityLabel }}</th><th>Amount</th></tr></thead><tbody>
                @forelse($entityAmountTable as $row)<tr><td>{{ data_get($row, 'entity.name') ?? data_get($row, 'entity->name') }}</td><td>RM {{ number_format((float) data_get($row, 'amount', 0), 2) }}</td></tr>@empty<tr><td colspan="2">No amount data.</td></tr>@endforelse
            </tbody><tfoot><tr><th>Grand Total</th><th>RM {{ number_format($entityAmountTable->sum('amount'), 2) }}</th></tr></tfoot></table></div>
        </div>
    </section>

    <section class="panel top-gap" id="section-status-entity" data-print-section="status-entity">
        <div class="panel-head"><div><h3>Status by {{ $entityLabel }}</h3><div class="subtle">  </div></div><div class="panel-tools no-print"><span class="badge">Status</span><button class="btn small ghost" type="button" onclick="printReportSection('section-status-entity')">Print Data</button></div></div>
        <div class="report-table-shell compact-matrix">
            <table>
                <thead><tr><th>{{ $entityLabel }}</th>@foreach($statusOptions as $status)<th>{{ $status }}</th>@endforeach<th>Grand Total</th></tr></thead>
                <tbody>
                    @forelse($entityStatusMatrix as $row)
                        <tr>
                            <td>{{ data_get($row, 'entity.name') ?? data_get($row, 'entity->name') }}</td>
                            @foreach($statusOptions as $status)<td>{{ data_get($row, 'statuses.' . $status, data_get($row, 'counts.' . $status, 0)) }}</td>@endforeach
                            <td><strong>{{ data_get($row, 'total', 0) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $statusOptions->count() + 2 }}">No status table data.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><th>Grand Total</th>@foreach($statusOptions as $status)<th>{{ $entityStatusMatrix->sum(fn($row) => data_get($row, 'statuses.' . $status, data_get($row, 'counts.' . $status, 0))) }}</th>@endforeach<th>{{ $entityStatusMatrix->sum('total') }}</th></tr></tfoot>
            </table>
        </div>
    </section>
    <section class="dashboard-grid-2 top-gap">
        <div class="panel" id="section-csr-technician" data-print-section="csr-technician">
            <div class="panel-head"><div><h3>Daily CSR Report</h3><div class="subtle">X axis = technician, Y axis = total CSR submit.</div></div><div class="panel-tools no-print"><span class="badge">CSR</span><button class="btn small ghost" type="button" onclick="printReportSection('section-csr-technician')">Print Data</button></div></div>
            <div class="vertical-chart">@forelse($technicianCsrGraph as $row)<div class="vbar"><strong>{{ data_get($row, 'count', 0) }}</strong><div class="bar" style="height:{{ max(4, ((int) data_get($row, 'count', 0) / $maxCsr) * 210) }}px"></div><small>{{ data_get($row, 'technician') }}</small></div>@empty<div class="compact-note">No CSR data.</div>@endforelse</div>
        </div>
        <div class="panel" id="section-technician-status" data-print-section="technician-status">
            <div class="panel-head"><div><h3>Technician Completed vs Pending</h3><div class="subtle"> </div></div><div class="panel-tools no-print"><button class="btn small ghost" type="button" onclick="printReportSection('section-technician-status')">Print Data</button></div></div>
            <div class="report-table-shell"><table><thead><tr><th>Technician</th><th>Completed</th><th>Pending</th><th>Grand Total</th></tr></thead><tbody>@forelse($technicianStatusMatrix as $row)<tr><td>{{ data_get($row, 'technician') }}</td><td>{{ data_get($row, 'completed', 0) }}</td><td>{{ data_get($row, 'pending', 0) }}</td><td><strong>{{ data_get($row, 'total', 0) }}</strong></td></tr>@empty<tr><td colspan="4">No technician status data.</td></tr>@endforelse</tbody><tfoot><tr><th>Grand Total</th><th>{{ $technicianStatusMatrix->sum('completed') }}</th><th>{{ $technicianStatusMatrix->sum('pending') }}</th><th>{{ $technicianStatusMatrix->sum('total') }}</th></tr></tfoot></table></div>
        </div>
    </section>

    <section class="panel top-gap" id="section-hours-entity" data-print-section="hours-entity">
        <div class="panel-head"><div><h3>Total Hours by {{ $entityLabel }}</h3><div class="subtle"> </div></div><div class="panel-tools no-print"><span class="badge">Hours</span><button class="btn small ghost" type="button" onclick="printReportSection('section-hours-entity')">Print Data</button></div></div>
        <div class="horizontal-chart hours-chart">@forelse($entityHoursGraph as $row)<div class="hbar"><span>{{ data_get($row, 'entity.name') ?? data_get($row, 'entity->name') }}</span><div class="track"><div class="fill" style="width:{{ ((float) data_get($row, 'hours', 0) / $maxEntityHours) * 100 }}%"></div></div><strong>{{ number_format((float) data_get($row, 'hours', 0), 2) }} h</strong></div>@empty<div class="compact-note">No duration data.</div>@endforelse</div>
    </section>

    <section class="panel top-gap" id="detail-section" data-print-section="detail-section-print" data-print-title="{{ $entityLabel }} & Task Detail - {{ $selectedDetailName }}">
        <div class="panel-head"><div><h3>{{ $entityLabel }} &amp; Task Detail</h3><div class="subtle"> </div></div><div class="panel-tools no-print"><span class="badge">Detail</span><button class="btn small ghost" type="button" onclick="printReportSection('detail-section')">Print Selected {{ $entityLabel }}</button></div></div>
        <div class="print-detail-title">{{ $entityLabel }}: {{ $selectedDetailName }}</div>
        <div class="detail-grid">
            <div class="detail-selector">
                <h4 style="margin:0 0 10px;">{{ $entityLabel }} List</h4>
                <div class="entity-pick-list">
                    @foreach($locations as $entity)
                        @php
                            $entityTotal = $monthlyEntitySummary->first(fn($row) => (int) data_get($row, 'branch.id') === (int) $entity->id || (int) data_get($row, 'branch->id') === (int) $entity->id);
                            $entityUrl = route($reportRouteName, array_merge($filters, ['detail_location_id' => $entity->id])) . '#detail-section';
                        @endphp
                        <a class="entity-pick {{ $selectedDetailId === (string) $entity->id ? 'active' : '' }}" href="{{ $entityUrl }}">
                            <strong>{{ $entity->name }}</strong>
                            <span>{{ data_get($entityTotal, 'total', 0) }} jobs</span>
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="detail-result-panel">
                <div class="insight-grid detail-summary-cards" style="margin-bottom:16px;">
                    <article class="helper-card"><span>Total Jobs</span><strong>{{ data_get($detail, 'summary.total_jobs', 0) }}</strong></article>
                    <article class="helper-card"><span>Total Hours</span><strong>{{ number_format((float) data_get($detail, 'summary.total_hours', 0), 2) }}</strong></article>
                    <article class="helper-card"><span>Total Cost</span><strong>RM {{ number_format((float) data_get($detail, 'summary.total_cost', 0), 2) }}</strong></article>
                    <article class="helper-card"><span>Average Cost / Job</span><strong>RM {{ number_format(data_get($detail, 'summary.total_jobs', 0) ? (float) data_get($detail, 'summary.total_cost', 0) / max(1, (int) data_get($detail, 'summary.total_jobs', 0)) : 0, 2) }}</strong></article>
                </div>
                <div class="report-table-shell"><table><thead><tr><th>Task Title</th><th>Total Job</th><th>Total Hours</th><th>Total Cost</th><th>Total per Task</th></tr></thead><tbody>@forelse(data_get($detail, 'rows', []) as $row)<tr><td>{{ data_get($row, 'task') }}</td><td>{{ data_get($row, 'total_job', 0) }}</td><td>{{ number_format((float) data_get($row, 'total_hours', 0), 2) }}</td><td>RM {{ number_format((float) data_get($row, 'total_cost', 0), 2) }}</td><td>RM {{ number_format((float) data_get($row, 'total_per_task', 0), 2) }}</td></tr>@empty<tr><td colspan="5">No detail rows available.</td></tr>@endforelse</tbody></table></div>
            </div>
        </div>
    </section>

</div>
<script>
(function () {
    const moneyPattern = /(RM|,)/gi;

    function toNumber(value) {
        if (!value) return 0;
        const cleaned = String(value).replace(moneyPattern, '').replace(/[^0-9.\-]/g, '');
        const parsed = parseFloat(cleaned);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function labelFromCell(cell) {
        return (cell?.innerText || '').replace(/\s+/g, ' ').trim() || '-';
    }

    function createGraphForTable(tableShell) {
        if (!tableShell || tableShell.dataset.graphReady === '1') return;
        const table = tableShell.querySelector('table');
        if (!table) return;

        const headers = Array.from(table.querySelectorAll('thead th')).map((th) => labelFromCell(th));
        const rows = Array.from(table.querySelectorAll('tbody tr'));
        const dataRows = rows.map((row) => {
            const cells = Array.from(row.children);
            const label = labelFromCell(cells[0]);
            if (!cells.length || label.toLowerCase().includes('no ')) return null;

            let bestIndex = -1;
            let bestLabel = '';
            let bestValue = 0;

            cells.slice(1).forEach((cell, offset) => {
                const index = offset + 1;
                const header = headers[index] || '';
                const numericValue = toNumber(cell.innerText);
                const preferred = /^(total|grand total|amount|completed|pending|total job|total hours|total cost|total per task)$/i.test(header.trim());
                const weightedValue = preferred ? numericValue + 0.0001 : numericValue;
                if (weightedValue > bestValue || (bestIndex === -1 && numericValue > 0)) {
                    bestIndex = index;
                    bestLabel = header;
                    bestValue = numericValue;
                }
            });

            if (bestIndex === -1) {
                const numericSum = cells.slice(1).reduce((total, cell) => total + toNumber(cell.innerText), 0);
                bestValue = numericSum;
                bestLabel = 'Total';
            }

            return { label, value: bestValue, metric: bestLabel || 'Total' };
        }).filter(Boolean);

        const graphShell = document.createElement('div');
        graphShell.className = 'report-graph-shell';

        if (!dataRows.length) {
            graphShell.innerHTML = '<div class="generated-empty">No graph data available for this section.</div>';
            tableShell.after(graphShell);
            tableShell.dataset.graphReady = '1';
            return;
        }

        const max = Math.max(...dataRows.map((row) => row.value), 1);
        const chart = document.createElement('div');
        chart.className = 'generated-chart';

        const title = document.createElement('div');
        title.className = 'report-graph-title';
        title.textContent = dataRows[0]?.metric ? `Bar chart based on ${dataRows[0].metric}` : 'Bar chart view';
        chart.appendChild(title);

        const barChart = document.createElement('div');
        barChart.className = 'generated-bar-chart';
        barChart.innerHTML = `
            <div class="generated-axis-label generated-axis-y">Value</div>
            <div class="generated-axis-label generated-axis-x">Item</div>
        `;

        [0, 25, 50, 75, 100].forEach((percent) => {
            const tick = document.createElement('div');
            tick.className = 'generated-y-tick';
            tick.style.bottom = `${70 + (percent / 100) * 260}px`;
            tick.innerHTML = `<span>${Number((max * percent) / 100).toLocaleString(undefined, { maximumFractionDigits: 0 })}</span>`;
            barChart.appendChild(tick);
        });

        dataRows
            .sort((a, b) => b.value - a.value)
            .slice(0, 24)
            .forEach((row) => {
                const item = document.createElement('div');
                item.className = 'generated-bar-item';
                const height = Math.max(3, (row.value / max) * 260);
                item.innerHTML = `
                    <div class="generated-bar-fill" style="height:${height}px"></div>
                    <div class="generated-bar-value">${Number(row.value).toLocaleString(undefined, { maximumFractionDigits: 2 })}</div>
                    <div class="generated-bar-label" title="${row.label.replace(/"/g, '&quot;')}">${row.label}</div>
                `;
                barChart.appendChild(item);
            });

        chart.appendChild(barChart);
        graphShell.appendChild(chart);
        tableShell.after(graphShell);
        tableShell.dataset.graphReady = '1';
    }

    function buildGraphs() {
        document.querySelectorAll('.report-table-shell').forEach(createGraphForTable);
    }

    function setReportView(mode) {
        const finalMode = mode === 'graph' ? 'graph' : 'table';
        document.body.classList.toggle('report-graph-mode', finalMode === 'graph');
        document.body.classList.toggle('report-table-mode', finalMode !== 'graph');
        document.querySelectorAll('[data-report-view]').forEach((button) => {
            button.classList.toggle('active', button.dataset.reportView === finalMode);
        });
        try { localStorage.setItem('maps2u-report-view', finalMode); } catch (error) {}
    }

    document.addEventListener('DOMContentLoaded', function () {
        buildGraphs();
        const saved = (() => { try { return localStorage.getItem('maps2u-report-view'); } catch (error) { return null; } })();
        setReportView(saved === 'graph' ? 'graph' : 'table');
        document.querySelectorAll('[data-report-view]').forEach((button) => {
            button.addEventListener('click', () => setReportView(button.dataset.reportView));
        });
    });

    window.printReportSection = function (id) {
        const target = document.getElementById(id);
        if (!target) return;
        buildGraphs();
        document.body.classList.add('print-section-mode');
        document.querySelectorAll('[data-print-section]').forEach((section) => section.classList.remove('printing'));
        target.classList.add('printing');
        window.print();
        setTimeout(() => {
            target.classList.remove('printing');
            document.body.classList.remove('print-section-mode');
        }, 500);
    };
})();
</script>

@endsection
