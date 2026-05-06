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

        .print-mode-label{display:inline-block;margin-left:8px;padding:3px 7px;border:1px solid var(--border);border-radius:999px;font-size:10px;color:#334155;background:#f8fafc}
        .graph-section{display:none;margin-top:8px}.print-graph-mode .table-section{display:none}.print-graph-mode .graph-section{display:block}.print-table-mode .table-section{display:block}.print-table-mode .graph-section{display:none}
        .print-chart{position:relative;display:flex;align-items:flex-end;gap:8px;min-height:230px;padding:22px 8px 54px 40px;border-left:1px solid #111827;border-bottom:1px solid #111827;background:repeating-linear-gradient(to top,#fff 0,#fff 44px,#d1d5db 45px);overflow:visible}.print-bar-row{height:170px;min-width:28px;max-width:42px;flex:1;display:flex;align-items:flex-end;justify-content:center;position:relative;break-inside:avoid}.print-bar-label{position:absolute;bottom:-42px;left:50%;width:58px;transform:translateX(-50%) rotate(-35deg);transform-origin:top center;font-size:6.5px;font-weight:700;text-align:right;line-height:1.15;color:#111827;white-space:normal;overflow:hidden}.print-bar-track{width:100%;height:170px;display:flex;align-items:flex-end;justify-content:center}.print-bar-fill{width:100%;max-width:28px;background:#111827;border-radius:7px 7px 0 0;min-height:2px}.print-bar-value{position:absolute;top:-14px;left:50%;transform:translateX(-50%);font-size:7px;font-weight:700;color:#111827;white-space:nowrap}.print-empty{font-size:10px;color:var(--muted)}
        @page{size:A4 landscape;margin:6mm}@media print{body{background:#fff;padding:0}.toolbar{display:none}.sheet{max-width:none;border:none;box-shadow:none;padding:0}.print-meta{border:1px solid #cbd5e1;padding:8px 10px;margin-bottom:10px}}
        
    </style>
</head>
<body class="{{ request('view', 'table') === 'graph' ? 'print-graph-mode' : 'print-table-mode' }}">
@php($section = $printSection ?? 'overview')
@php($viewMode = request('view', 'table') === 'graph' ? 'graph' : 'table')
<div class="toolbar">
    <a class="btn" href="javascript:history.back()">Back</a>
    <a class="btn" href="{{ request()->fullUrlWithQuery(['view' => 'table']) }}">Table Print</a>
    <a class="btn" href="{{ request()->fullUrlWithQuery(['view' => 'graph']) }}">Graph Print</a>
    <button class="btn" onclick="window.print()">Print Current View</button>
</div>
<div class="sheet">
    <div class="header">
        <div>
            <h1>{{ $title }} Report</h1>
            <p class="sub">Worksheet layout based on the selected report container and current filters. <span class="print-mode-label">{{ strtoupper($viewMode) }} VIEW</span></p>
        </div>
        <div class="sub"><strong>Printed:</strong> {{ now('Asia/Kuala_Lumpur')->format('d M Y h:i A') }}</div>
    </div>

    <div class="print-summary"><strong>Year:</strong> {{ $selectedYear }} &nbsp; | &nbsp; <strong>Total Job:</strong> {{ $overviewMetrics['total_jobs'] ?? 0 }} &nbsp; | &nbsp; <strong>Total Hours:</strong> {{ number_format($overviewMetrics['total_hours'] ?? 0, 2) }} &nbsp; | &nbsp; <strong>Total Cost:</strong> RM {{ number_format($overviewMetrics['total_cost'] ?? 0, 2) }}<br><strong>State:</strong> {{ $selectedState ?: 'All States' }} &nbsp; | &nbsp; <strong>Status:</strong> {{ $filters['status'] ?? 'All Status' }} &nbsp; | &nbsp; <strong>Date Range:</strong> {{ ($filters['date_from'] ?? '-') . ' to ' . ($filters['date_to'] ?? '-') }} &nbsp; | &nbsp; <strong>{{ $entityLabel }}:</strong> {{ $selectedEntityName ?: ('All ' . $entityPlural) }} &nbsp; | &nbsp; <strong>Task:</strong> {{ $filters['detail_task'] ?? 'All Tasks' }}</div>

    @if(in_array($section, ['overview','monthly_task'], true))
    <section class="section">
        <h2>Monthly Task Summary</h2>
        <div class="table-section">
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
        </div>
        @php($maxMonthlyTaskTotal = max(1, (int) collect($monthlyTaskSummary)->max('total')))
        <div class="graph-section"><div class="print-chart">
            @forelse(collect($monthlyTaskSummary)->sortByDesc('total')->take(30) as $row)
                @php($value = (int) data_get($row, 'total', 0))
                <div class="print-bar-row"><div class="print-bar-track"><div class="print-bar-fill" style="height:{{ max(2, ($value / $maxMonthlyTaskTotal) * 170) }}px"></div></div><div class="print-bar-value">{{ number_format($value) }}</div><div class="print-bar-label">{{ data_get($row, 'task_name') }}</div></div>
            @empty
                <div class="print-empty">No graph data available.</div>
            @endforelse
        </div></div>
    </section>
    @endif

    @if(in_array($section, ['overview','monthly_entity'], true))
    <section class="section">
        <h2>Monthly {{ $entityLabel }} Summary</h2>
        <div class="table-section">
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
        </div>
        @php($maxMonthlyEntityTotal = max(1, (int) collect($monthlyEntitySummary)->max('total')))
        <div class="graph-section"><div class="print-chart">
            @forelse(collect($monthlyEntitySummary)->sortByDesc('total')->take(30) as $row)
                @php($value = (int) data_get($row, 'total', 0))
                <div class="print-bar-row"><div class="print-bar-track"><div class="print-bar-fill" style="height:{{ max(2, ($value / $maxMonthlyEntityTotal) * 170) }}px"></div></div><div class="print-bar-value">{{ number_format($value) }}</div><div class="print-bar-label">{{ data_get($row, 'branch.name') ?? data_get($row, 'branch->name') }}</div></div>
            @empty
                <div class="print-empty">No graph data available.</div>
            @endforelse
        </div></div>
    </section>
    @endif


    
</div>
<script>window.addEventListener('load',()=>window.print());</script>
</body>
</html>
