<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Report {{ $job->request_code }}</title>
<style>
:root{--line:#111827;--muted:#6b7280;--soft:#f8fafc;}
*{box-sizing:border-box}
body{margin:0;padding:14px;background:#edf2f7;color:#111827;font-family:Arial,Helvetica,sans-serif}
.toolbar{width:min(100%,1120px);margin:0 auto 16px;display:flex;justify-content:flex-end;gap:10px}
.btn{padding:10px 14px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#111827;text-decoration:none;font-size:11px}
.btn.primary{background:#0f172a;color:#fff;border-color:#0f172a}
.page{width:min(100%,1120px);margin:0 auto;background:#fff;padding:16px;box-shadow:0 12px 35px rgba(15,23,42,.08)}
h1{text-align:center;font-size:22px;margin:0 0 24px}
.meta{display:grid;grid-template-columns:1fr 1fr;gap:12px 28px;margin-bottom:20px}
.meta-item{font-size:16px;line-height:1.5}.meta-item strong{display:inline-block;min-width:230px}
.top-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
.box{border:2px solid var(--line);border-radius:14px;padding:16px;min-height:280px;page-break-inside:avoid}.box h2{text-align:center;font-size:18px;margin:0 0 14px}
.section-list{display:grid;gap:10px}.row{border:1px solid #d1d5db;border-radius:10px;padding:10px 12px;background:#fcfcfd}.row strong{display:block;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#374151;margin-bottom:4px}.row span,.row div{font-size:14px;line-height:1.45;word-break:break-word}
.images{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.images.single{grid-template-columns:1fr}.image-card{border:1px solid #d1d5db;border-radius:10px;padding:8px;background:#fff}.image-card img{width:100%;height:220px;object-fit:contain;background:#f8fafc;border-radius:8px;display:block}.image-card embed,.image-card iframe{width:100%;height:260px;border:none;border-radius:8px;background:#fff;display:block}.image-card .label{font-size:12px;color:#374151;margin-top:6px}
.table-box{border:2px solid var(--line);border-radius:14px;overflow:hidden}.table-title{text-align:center;font-size:18px;padding:14px;border-bottom:2px solid var(--line)}
table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid var(--line);padding:10px;vertical-align:top;font-size:13px}th{text-align:center;background:#f8fafc}td img{width:100%;max-height:180px;object-fit:contain;border-radius:6px;background:#fff}.file-grid embed,.file-grid iframe{width:100%;height:220px;border:none;border-radius:6px;background:#fff;display:block}.file-grid{display:grid;gap:8px}.muted{color:var(--muted)}.cell-small{white-space:nowrap}
@page{size:A4 portrait;margin:6mm}
@media print{body{background:#fff;padding:0}.toolbar{display:none}.page{width:auto;margin:0;box-shadow:none;padding:0}.box,.table-box{break-inside:avoid}}
@media (max-width:900px){.meta,.top-grid{grid-template-columns:1fr}.meta-item strong{display:block;min-width:0}.images{grid-template-columns:1fr}}
</style>
</head>
<body>
@php
$report = $job->customer_service_report ?? [];
$review = $job->technician_review ?? [];
$inspect = $job->inspect_data ?? [];
$approvedQuotation = $job->approvedQuotation();
$fileUrl = function (?string $path) {
    if (!$path) return null;
    return route('files.show', ['encodedPath' => rtrim(strtr(base64_encode($path), '+/', '-_'), '=')]);
};
$clientFiles = collect($job->attachments ?? [])->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values();
$quotationFiles = collect([$approvedQuotation['file'] ?? null])->filter()->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values();
$quotationSupportingFiles = collect(data_get($approvedQuotation, 'summary_files', []))->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values();
$visitSiteFiles = collect(data_get($review, 'visit_site_files', []))->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values();
$inspectFiles = collect(array_merge($inspect['before_files'] ?? [], $inspect['after_files'] ?? []))->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values();
$dailyLogs = collect($job->inspection_sessions ?? [])->map(function ($session) use ($job) {
    $session = (array) $session;
    $session['resolved_duration'] = $job->formattedDuration($job->inspectionSessionDurationSeconds($session));
    return $session;
});
$approvalRemarks = collect($job->approvalPrintableRemarks());
$sharedRemarks = collect($job->adminTechnicianRemarkLines());
@endphp
<div class="toolbar">
    <a href="{{ $backRoute ?? route('technician.job-requests.show', $job) }}" class="btn">Back</a>
    <a href="{{ $printRoute ?? route('technician.job-requests.report', [$job, 'print' => 1]) }}" class="btn">Download / Save PDF</a>
    <button class="btn primary" onclick="window.print()">Print</button>
</div>
<div class="page">
    <h1>REPORT ({{ $job->request_code }})</h1>
    <div class="meta">
        <div class="meta-item"><strong>Technician name :</strong> {{ $job->assignedTechnician?->name ?? ($report['technician_name'] ?? '-') }}</div>
        <div class="meta-item"><strong>Total Duration Completed :</strong> {{ $report['duration_of_work'] ?? $job->formattedDuration($job->totalInspectionDurationSeconds()) }}</div>
        @if($job->related_request_id)
            <div class="meta-item"><strong>Related Job ID :</strong> {{ $job->relatedRequest?->request_code ?? $job->related_request_id }}</div>
        @endif
    </div>
    <div class="top-grid">
        <section class="box">
            <h2>Client Details ( include request form )</h2>
            <div class="section-list">
                <div class="row"><strong>Client Name</strong><span>{{ $job->full_name }}</span></div>
                <div class="row"><strong>Phone Number</strong><span>{{ $job->phone_number }}</span></div>
                <div class="row"><strong>Request Type</strong><span>{{ $job->requestType?->name ?? '-' }}</span></div>
                <div class="row"><strong>Location</strong><span>{{ $job->location?->name ?? '-' }}</span></div>
                <div class="row"><strong>Department</strong><span>{{ $job->department?->name ?? '-' }}</span></div>
                <div class="row"><strong>Urgency Level</strong><span>{{ $job->urgencyLabel() }}</span></div>
                @foreach($job->requestType->questions as $question)
                    @php($answer = $job->answers[$question->id] ?? null)
                    <div class="row">
                        <strong>{{ $question->question_text }}</strong>
                        <div>
                            @if($question->question_type === 'remark')
                                {{ $answer ?: '-' }}
                            @elseif(in_array($question->question_type, ['radio', 'task_title'], true))
                                {{ data_get($answer, 'value', '-') }}@if(data_get($answer, 'other')) - {{ data_get($answer, 'other') }}@endif
                            @elseif($question->question_type === 'date_range')
                                {{ $question->start_label ?: 'Start Date' }}: {{ data_get($answer, 'start', '-') }} / {{ $question->end_label ?: 'End Date' }}: {{ data_get($answer, 'end', '-') }}
                            @else
                                @php($items = collect($answer ?? [])->map(fn ($selected) => trim((data_get($selected, 'value') ?? '-') . (data_get($selected, 'other') ? ' - ' . data_get($selected, 'other') : '')))->implode(', '))
                                {{ $items ?: '-' }}
                            @endif
                        </div>
                    </div>
                @endforeach
                @foreach($approvalRemarks as $remark)
                    <div class="row"><strong>{{ $remark['label'] }}</strong><span>{!! nl2br(e($remark['value'])) !!}</span></div>
                @endforeach
                @if($clientFiles->isNotEmpty())
                    <div class="row">
                        <strong>Client Images</strong>
                        <div class="images {{ $clientFiles->count() === 1 ? 'single' : '' }}">
                            @foreach($clientFiles as $file)
                                @include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Image'])
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
        <section class="box">
            <h2>Technician review + approved quotation + inspect form</h2>
            <div class="section-list">
                <div class="row"><strong>Clarification</strong><span>{{ ucfirst(str_replace('_', ' ', data_get($review, 'clarification_level', '-'))) }}</span></div>
                <div class="row"><strong>Repair Channel</strong><span>{{ ucfirst(str_replace('_', ' ', data_get($review, 'repair_channel', '-'))) }}</span></div>
                <div class="row"><strong>Repair Scale</strong><span>{{ ucfirst(str_replace('_', ' ', data_get($review, 'repair_scale', '-'))) }}</span></div>
                <div class="row"><strong>Processing</strong><span>{{ ucfirst(str_replace('_', ' ', data_get($review, 'processing_type', '-'))) }}</span></div>
                <div class="row"><strong>Visit Site</strong><span>{{ ucfirst(data_get($review, 'visit_site', 'no')) }}</span></div>
                @if(data_get($review, 'visit_site_remark'))<div class="row"><strong>Visit Site Remark</strong><span>{{ data_get($review, 'visit_site_remark') }}</span></div>@endif
                @if($visitSiteFiles->isNotEmpty())
                    <div class="row"><strong>Visit Site Files</strong><div class="images {{ $visitSiteFiles->count() === 1 ? 'single' : '' }}">@foreach($visitSiteFiles as $file)@include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Visit site file'])@endforeach</div></div>
                @endif
                <div class="row"><strong>Approved Quotation Company</strong><span>{{ data_get($approvedQuotation, 'company_name', '-') }}</span></div>
                <div class="row"><strong>Approved Quotation Amount</strong><span>{{ data_get($approvedQuotation, 'amount') ? 'RM ' . number_format((float) data_get($approvedQuotation, 'amount'), 2) : '-' }}</span></div>
                @if(data_get($approvedQuotation, 'admin_signed_at'))<div class="row"><strong>Admin Approved At</strong><span>{{ data_get($approvedQuotation, 'admin_signed_at') }}</span></div>@endif
                @if($quotationFiles->isNotEmpty())
                    <div class="row"><strong>Approved Quotation File</strong><div class="images {{ $quotationFiles->count() === 1 ? 'single' : '' }}">@foreach($quotationFiles as $file)@include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Attachment'])@endforeach</div></div>
                @endif
                @if($quotationSupportingFiles->isNotEmpty())
                    <div class="row"><strong>Quotation Supporting Files</strong><div class="images {{ $quotationSupportingFiles->count() === 1 ? 'single' : '' }}">@foreach($quotationSupportingFiles as $file)@include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Supporting file'])@endforeach</div></div>
                @endif
                @if($sharedRemarks->isNotEmpty())<div class="row"><strong>Admin ↔ Technician Remarks</strong>@foreach($sharedRemarks as $remark)<div style="margin-top:8px;"><strong style="font-size:12px;">{{ $remark['header'] }}</strong><div>{!! nl2br(e($remark['remark'])) !!}</div></div>@endforeach</div>@endif
                @if(count($job->viewerSummaryHistoryLines()))<div class="row"><strong>Management Viewer Remark Summary History</strong><div style="display:grid;gap:10px;">@foreach($job->viewerSummaryHistoryLines() as $log)<div><div style="margin-top:6px;font-size:12px;color:#475569;">{{ $log['header'] }}</div><div>{!! nl2br(e($log['remark'])) !!}</div>@if($log['signature'])<div class="images single" style="margin-top:8px;">@include('components.print-media-card', ['file' => ['path' => null, 'data_url' => $log['signature'], 'mime_type' => 'image/png', 'original_name' => 'Viewer Signature'], 'label' => 'Viewer Signature'])</div>@endif</div>@endforeach</div></div>@endif
                <div class="row"><strong>Inspection Remark</strong><span>{{ data_get($inspect, 'inspection_remark', '-') }}</span></div>
                <div class="row"><strong>Safety Checked</strong><span>{{ data_get($inspect, 'safety_checked') ? 'Yes' : 'No' }}</span></div>
                <div class="row"><strong>Quality Checked</strong><span>{{ data_get($inspect, 'quality_checked') ? 'Yes' : 'No' }}</span></div>
                <div class="row"><strong>Customer Satisfaction Checked</strong><span>{{ data_get($inspect, 'customer_satisfaction_checked') ? 'Yes' : 'No' }}</span></div>
                @if($inspectFiles->isNotEmpty())
                    <div class="row"><strong>Inspect Form Images</strong><div class="images {{ $inspectFiles->count() === 1 ? 'single' : '' }}">@foreach($inspectFiles as $file)@include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Attachment'])@endforeach</div></div>
                @endif
            </div>
        </section>
    </div>
    <section class="table-box">
        <div class="table-title">Technician daily log record</div>
        <table>
            <thead><tr><th style="width:12%;">Date</th><th style="width:13%;">Started at</th><th style="width:13%;">End time</th><th style="width:12%;">Duration</th><th style="width:22%;">Remark</th><th style="width:28%;">Uploaded files</th></tr></thead>
            <tbody>
                @forelse($dailyLogs as $session)
                    <tr>
                        <td class="cell-small">{{ $session['date_label'] ?? '-' }}</td>
                        <td class="cell-small">{{ $session['time_start'] ?? '-' }}</td>
                        <td class="cell-small">{{ $session['time_end'] ?? '-' }}</td>
                        <td class="cell-small">{{ $session['resolved_duration'] ?? '-' }}</td>
                        <td>{{ $session['remark'] ?? '-' }}@if(!empty($session['verify_by_signed_at_label']))<div class="muted" style="margin-top:6px;">Verify signed: {{ $session['verify_by_signed_at_label'] }}</div>@endif @if(!empty($session['verify_by']))<div class="file-grid" style="margin-top:8px;">@include('components.print-media-card', ['file' => ['path' => null, 'data_url' => $session['verify_by'], 'mime_type' => 'image/png', 'original_name' => 'Verify Signature'], 'label' => 'Verify Signature'])</div>@endif</td>
                        <td>
                            @php($images = collect($session['attachments'] ?? [])->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values())
                            @if($images->isEmpty())
                                <span class="muted">-</span>
                            @else
                                <div class="file-grid">@foreach($images as $file)@include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Attachment'])@endforeach</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;">No technician daily log record.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
@include('components.print-pdf-script')
@if($printMode)<script>window.addEventListener('load',()=>window.__maps2uPrintRenderReady?.finally(() => window.print()));</script>@endif
</body>
</html>
