<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} Report Print</title>
    <style>
        :root{--border:#cbd5e1;--text:#0f172a;--muted:#64748b}
        *{box-sizing:border-box}body{margin:0;padding:12px;background:#eef2f7;color:var(--text);font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.35}
        .toolbar{max-width:1280px;margin:0 auto 10px;display:flex;justify-content:flex-end;gap:8px}.btn{padding:8px 12px;border:1px solid var(--border);border-radius:10px;background:#fff;text-decoration:none;color:var(--text);font-size:11px}
        .sheet{max-width:1280px;margin:0 auto;background:#fff;padding:16px;border:1px solid #e2e8f0;box-shadow:0 12px 28px rgba(15,23,42,.08)}
        .header{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:10px}.header h1{margin:0 0 4px;font-size:20px}.sub{margin:0;color:var(--muted);font-size:11px}
        .print-summary{margin-bottom:10px;font-size:10px;color:#334155}.print-summary strong{font-size:10px;color:#0f172a}
        .section{margin-top:14px}.section h2{margin:0 0 6px;font-size:14px}.note{margin:0 0 8px;color:var(--muted);font-size:10px}
        table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #111827;padding:4px 6px;font-size:10px;text-align:left;vertical-align:top;word-wrap:break-word}th{background:#f1f5f9}.summary-row th,.summary-row td{font-weight:700;background:#f8fbff}
        @page{size:A4 landscape;margin:6mm}@media print{body{background:#fff;padding:0}.toolbar{display:none}.sheet{max-width:none;border:none;box-shadow:none;padding:0}.print-meta{border:1px solid #cbd5e1;padding:8px 10px;margin-bottom:10px}}
        
    </style>
</head>
<body>
@php($section = $printSection ?? 'overview')
<div class="toolbar">
    <a class="btn" href="javascript:history.back()">Back</a>
    <button class="btn" onclick="window.print()">Print</button>
</div>
<div class="sheet">
    <div class="header">
        <div>
            <h1>{{ $title }} Report</h1>
            <p class="sub">Worksheet layout based on the selected report container and current filters.</p>
        </div>
        <div class="sub"><strong>Printed:</strong> {{ now('Asia/Kuala_Lumpur')->format('d M Y h:i A') }}</div>
    </div>

    <div class="print-summary"><strong>Year:</strong> {{ $selectedYear }} &nbsp; | &nbsp; <strong>Total Job:</strong> {{ $overviewMetrics['total_jobs'] ?? 0 }} &nbsp; | &nbsp; <strong>Total Hours:</strong> {{ number_format($overviewMetrics['total_hours'] ?? 0, 2) }} &nbsp; | &nbsp; <strong>Total Cost:</strong> RM {{ number_format($overviewMetrics['total_cost'] ?? 0, 2) }}<br><strong>State:</strong> {{ $selectedState ?: 'All States' }} &nbsp; | &nbsp; <strong>Status:</strong> {{ $filters['status'] ?? 'All Status' }} &nbsp; | &nbsp; <strong>Date Range:</strong> {{ ($filters['date_from'] ?? '-') . ' to ' . ($filters['date_to'] ?? '-') }} &nbsp; | &nbsp; <strong>{{ $entityLabel }}:</strong> {{ $selectedEntityName ?: ('All ' . $entityPlural) }} &nbsp; | &nbsp; <strong>Task:</strong> {{ $filters['detail_task'] ?? 'All Tasks' }}</div>

    @if(in_array($section, ['overview','monthly_task'], true))
    <section class="section">
        <h2>Monthly Task Summary</h2>
        <table>
            <thead><tr><th>Task Title</th>@foreach($months as $monthNumber => $label)<th>{{ $label }}</th>@endforeach<th>Total</th><th>Average / Month</th><th>Total Hours</th><th>Total Cost</th></tr></thead>
            <tbody>
                @forelse($monthlyTaskSummary as $row)
                    <tr><td>{{ data_get($row, 'task_name') }}</td>@foreach($months as $monthNumber => $label)<td>{{ data_get($row, "months.$monthNumber", 0) }}</td>@endforeach<td>{{ data_get($row, 'total', 0) }}</td><td>{{ number_format((float) data_get($row, 'average_month', 0), 2) }}</td><td>{{ number_format((float) data_get($row, 'total_hours', 0), 2) }}</td><td>RM {{ number_format((float) data_get($row, 'total_cost', 0), 2) }}</td></tr>
                @empty
                    <tr><td colspan="17">No task statistics available.</td></tr>
                @endforelse
                <tr class="summary-row"><th>Total</th>@foreach($months as $monthNumber => $label)<th>{{ collect($monthlyTaskSummary)->sum(fn ($row) => data_get($row, "months.$monthNumber", 0)) }}</th>@endforeach<th>{{ collect($monthlyTaskSummary)->sum('total') }}</th><th>{{ number_format(collect($monthlyTaskSummary)->sum('total') / 12, 2) }}</th><th>{{ number_format(collect($monthlyTaskSummary)->sum('total_hours'), 2) }}</th><th>RM {{ number_format(collect($monthlyTaskSummary)->sum('total_cost'), 2) }}</th></tr>
            </tbody>
        </table>
    </section>
    @endif

    @if(in_array($section, ['overview','monthly_entity'], true))
    <section class="section">
        <h2>Monthly {{ $entityLabel }} Summary</h2>
        <table>
            <thead><tr><th>{{ $entityLabel }}</th>@foreach($months as $monthNumber => $label)<th>{{ $label }}</th>@endforeach<th>Total</th><th>Average / Month</th><th>Total Hours</th><th>Total Cost</th></tr></thead>
            <tbody>
                @forelse($monthlyEntitySummary as $row)
                    <tr><td>{{ data_get($row, 'branch.name') ?? data_get($row, 'branch->name') }}</td>@foreach($months as $monthNumber => $label)<td>{{ data_get($row, "months.$monthNumber", 0) }}</td>@endforeach<td>{{ data_get($row, 'total', 0) }}</td><td>{{ number_format((float) data_get($row, 'average_month', 0), 2) }}</td><td>{{ number_format((float) data_get($row, 'total_hours', 0), 2) }}</td><td>RM {{ number_format((float) data_get($row, 'total_cost', 0), 2) }}</td></tr>
                @empty
                    <tr><td colspan="17">No {{ strtolower($entityLabel) }} statistics available.</td></tr>
                @endforelse
                <tr class="summary-row"><th>Total</th>@foreach($months as $monthNumber => $label)<th>{{ collect($monthlyEntitySummary)->sum(fn ($row) => data_get($row, "months.$monthNumber", 0)) }}</th>@endforeach<th>{{ collect($monthlyEntitySummary)->sum('total') }}</th><th>{{ number_format(collect($monthlyEntitySummary)->sum('total') / 12, 2) }}</th><th>{{ number_format(collect($monthlyEntitySummary)->sum('total_hours'), 2) }}</th><th>RM {{ number_format(collect($monthlyEntitySummary)->sum('total_cost'), 2) }}</th></tr>
            </tbody>
        </table>
    </section>
    @endif

    @if(in_array($section, ['overview','detail'], true))
    <section class="section">
        <h2>{{ $entityLabel }} &amp; Task Detail</h2>
        <p class="note">This section follows the selected date range, status, state, {{ strtolower($entityLabel) }}, and task filters.</p>
        <table>
            <thead><tr><th>Request Code</th><th>{{ $entityLabel }}</th><th>Task Title</th><th>Status</th><th>Submitted At</th><th>Completed At</th><th>Cost</th><th>Hours</th></tr></thead>
            <tbody>
                @forelse(data_get($detail, 'jobs', []) as $job)
                    @php($jobCode = is_array($job) ? data_get($job, 'request_code') : $job->request_code)
                    @php($jobEntity = is_array($job) ? data_get($job, 'location_name') : ($job->location?->name ?? '-'))
                    @php($jobTask = is_array($job) ? data_get($job, 'task_title') : ($job->primaryTaskTitleName() ?? ($job->requestType?->name ?? '-')))
                    @php($jobStatus = is_array($job) ? data_get($job, 'status') : $job->adminWorkflowLabel())
                    @php($jobSubmitted = is_array($job) ? data_get($job, 'submitted_at') : $job->created_at?->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y h:i A'))
                    @php($jobCompleted = is_array($job) ? data_get($job, 'completed_at') : ($job->finance_completed_at ? $job->finance_completed_at->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y h:i A') : '-'))
                    @php($jobCost = is_array($job) ? (float) data_get($job, 'cost', 0) : (is_numeric(data_get($job->approvedQuotation(), 'amount')) ? (float) data_get($job->approvedQuotation(), 'amount') : 0))
                    @php($jobHours = is_array($job) ? (float) data_get($job, 'hours', 0) : (float) $job->reportDurationHours())
                    <tr><td>{{ $jobCode }}</td><td>{{ $jobEntity }}</td><td>{{ $jobTask }}</td><td>{{ $jobStatus }}</td><td>{{ $jobSubmitted }}</td><td>{{ $jobCompleted }}</td><td>RM {{ number_format($jobCost, 2) }}</td><td>{{ number_format($jobHours, 2) }}</td></tr>
                @empty
                    <tr><td colspan="8">No request records found for this filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    @endif

    @if(in_array($section, ['overview','combined'], true))
    <section class="section">
        <h2>Combined Statistics</h2>
        <table>
            <thead><tr><th>Task</th>@foreach(collect(data_get($combined, 'branchPerformance', []))->take(12) as $entityRow)<th>{{ data_get($entityRow, 'entity.name') ?? data_get($entityRow, 'entity->name') }}</th>@endforeach<th>Total</th></tr></thead>
            <tbody>
                @forelse(data_get($combined, 'taskBranchMatrix', []) as $row)
                    <tr><td>{{ data_get($row, 'task') }}</td>@foreach(collect(data_get($combined, 'branchPerformance', []))->take(12) as $entityRow)<td>{{ data_get($row, 'entities.' . (data_get($entityRow, 'entity.name') ?? data_get($entityRow, 'entity->name')), 0) }}</td>@endforeach<td>{{ collect(data_get($row, 'entities', []))->sum() }}</td></tr>
                @empty
                    <tr><td colspan="14">No matrix data available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    @endif
</div>
<script>window.addEventListener('load',()=>window.print());</script>
</body>
</html>
