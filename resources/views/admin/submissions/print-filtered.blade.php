<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Incoming Request Filter Print</title>
<style>
:root{--line:#111827;--muted:#64748b;--soft:#f8fafc;}*{box-sizing:border-box}body{margin:0;padding:24px;background:#edf2f7;color:#111827;font-family:Arial,Helvetica,sans-serif}.toolbar{width:min(100%,1180px);margin:0 auto 16px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}.btn{padding:10px 14px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#111827;text-decoration:none;font-size:14px}.btn.primary{background:#0f172a;color:#fff;border-color:#0f172a}.page,.document-page{width:min(100%,1180px);margin:0 auto;background:#fff;box-shadow:0 12px 35px rgba(15,23,42,.08)}.page{padding:24px}.document-page{padding:24px;margin-top:18px;page-break-before:always}.page-title{text-align:center;font-size:28px;margin:0 0 8px}.page-sub{text-align:center;color:var(--muted);margin:0 0 18px}.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}.summary-card,.box,.filter-box{border:1px solid #d1d5db;border-radius:12px;padding:14px;background:#fcfcfd}.summary-card strong,.filter-box strong,.row strong{display:block;font-size:12px;text-transform:uppercase;color:#374151;margin-bottom:6px}.summary-card span{font-size:18px;font-weight:700}.filter-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.table-box{border:1px solid #d1d5db;border-radius:12px;overflow:hidden}.table-title{text-align:center;font-size:15px;font-weight:700;padding:10px;border-bottom:1px solid #d1d5db;background:#fff}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #111827;padding:8px;font-size:12px;vertical-align:top;line-height:1.35}th{background:#f1f5f9;text-align:left}.row{border:1px solid #d1d5db;border-radius:10px;padding:10px 12px;background:#fff}.row span,.row div{font-size:14px;line-height:1.45;word-break:break-word}.detail-grid{display:grid;grid-template-columns:1.35fr .95fr;gap:18px}.list{display:grid;gap:10px}.rating-badge{display:inline-flex;align-items:center;justify-content:center;min-width:42px;min-height:28px;padding:4px 10px;border-radius:999px;background:#e8f3ff;color:#0b5f82;font-weight:700}.muted{color:var(--muted)}@page{size:A4 portrait;margin:8mm}@media print{html,body{background:#fff!important;padding:0!important;margin:0!important}.toolbar{display:none!important}.page,.document-page{width:auto!important;margin:0!important;box-shadow:none!important}.document-page{margin-top:0!important;padding-top:0!important}.box,.row,tr,td,th{break-inside:avoid;page-break-inside:avoid}}@media (max-width:900px){.summary-grid,.filter-list,.detail-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="toolbar">
    <a href="{{ route('admin.incoming-requests.index', $filters) }}" class="btn">Back</a>
    <a href="{{ route('admin.incoming-requests.print-filtered', array_merge($filters, ['print' => 1])) }}" class="btn">Download / Save PDF</a>
    <button class="btn primary" onclick="window.print()">Print</button>
</div>
<div class="page">
    @php
        $admin = auth()->user();
        $showBothLocations = $admin?->isViewer();
        $primaryClientRole = $admin?->primaryHandledClientRole();
    @endphp
    <h1 class="page-title">Incoming Request Filter Report</h1>
    <section class="table-box">
        <div class="table-title">Filtered Request List</div>
        <table>
            <thead><tr><th>Job ID</th><th>Client</th><th>Role</th><th>Type</th>@if($showBothLocations)<th>Location (HQ Staff)</th><th>Branches (Kindergarten)</th>@elseif($primaryClientRole === \App\Models\User::CLIENT_HQ)<th>Location</th>@else<th>Branches</th>@endif<th>Department</th><th>Technician</th><th>Status</th><th>Approval</th></tr></thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item->request_code }}</td>
                    <td>{{ $item->full_name }}<br><span class="muted">{{ $item->phone_number }}</span></td>
                    <td>{{ $item->user?->roleLabel() ?? '-' }}</td>
                    <td>{{ $item->requestType?->name ?? '-' }}</td>
                    @if($showBothLocations)
                    <td>{{ $item->user?->sub_role === \App\Models\User::CLIENT_HQ ? ($item->location?->name ?? '-') : '-' }}</td>
                    <td>{{ $item->user?->sub_role === \App\Models\User::CLIENT_KINDERGARTEN ? ($item->location?->name ?? '-') : '-' }}</td>
                    @elseif($primaryClientRole === \App\Models\User::CLIENT_HQ)
                    <td>{{ $item->location?->name ?? '-' }}</td>
                    @else
                    <td>{{ $item->location?->name ?? '-' }}</td>
                    @endif
                    <td>{{ $item->department?->name ?? '-' }}</td>
                    <td>{{ $item->assignedTechnician?->name ?? '-' }}</td>
                    <td>{{ $item->adminWorkflowLabel() }}</td>
                    <td>{{ $item->adminApprovalLabel() }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $showBothLocations ? 11 : 10 }}" style="text-align:center;">No data found for the selected filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</div>
@if($isCompletedWorkflow && $items->isNotEmpty())
    @foreach($items as $item)
        @php
            $feedback = $item->feedback ?? [];
        @endphp
        <section class="document-page">
            <h2 class="page-title" style="font-size:24px;">Completed Request Document ({{ $item->request_code }})</h2>
            <p class="page-sub">Clean table printout for filtered report documents.</p>
            <section class="table-box">
                <div class="table-title">Request Details</div>
                <table>
                    <tbody>
                        <tr><th style="width:30%">Client</th><td>{{ $item->full_name }}</td></tr>
                        <tr><th>Phone Number</th><td>{{ $item->phone_number }}</td></tr>
                        <tr><th>Role</th><td>{{ $item->user?->roleLabel() ?? '-' }}</td></tr>
                        <tr><th>Request Type</th><td>{{ $item->requestType?->name ?? '-' }}</td></tr>
                        <tr><th>Location / Branch</th><td>{{ $item->location?->name ?? '-' }}</td></tr>
                        <tr><th>Department</th><td>{{ $item->department?->name ?? '-' }}</td></tr>
                        <tr><th>Assigned Technician</th><td>{{ $item->assignedTechnician?->name ?? '-' }}</td></tr>
                        <tr><th>Status</th><td>{{ $item->adminWorkflowLabel() }}</td></tr>
                        <tr><th>Completed At</th><td>{{ $item->finance_completed_at ? $item->finance_completed_at->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y h:i A') : '-' }}</td></tr>
                        @foreach(($item->requestType->questions ?? []) as $question)
                            <tr>
                                <th>{{ $question->question_text }}</th>
                                <td>{!! nl2br(e($item->displayAnswerForQuestion($question))) !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
            <section class="table-box" style="margin-top:16px;">
                <div class="table-title">Client Feedback</div>
                <table>
                    <tbody>
                        <tr><th style="width:30%">Average Rating</th><td>{{ $item->feedbackAverage() ? number_format($item->feedbackAverage(), 2) . ' / 5' : '-' }}</td></tr>
                        <tr><th>Additional Comments</th><td>{{ data_get($feedback, 'additional_comments') ?: '-' }}</td></tr>
                        @php $hasRows = false; @endphp
                        @foreach($feedbackSections as $sectionKey => $section)
                            @foreach($section['questions'] as $questionKey => $questionText)
                                @php $rating = data_get($feedback, "ratings.$sectionKey.$questionKey"); @endphp
                                @if($rating)
                                    @php $hasRows = true; @endphp
                                    <tr><th>{{ $section['title'] }}</th><td>{{ $questionText }} — {{ $rating }}/5</td></tr>
                                @endif
                            @endforeach
                        @endforeach
                        @if(!$hasRows)
                            <tr><th>Feedback</th><td>No client feedback submitted.</td></tr>
                        @endif
                    </tbody>
                </table>
            </section>
        </section>
    @endforeach
@endif
@if(request('print'))<script>window.addEventListener('load',()=>window.print());</script>@endif
</body>
</html>
