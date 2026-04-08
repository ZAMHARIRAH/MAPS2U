<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Technician Reports</title>
<style>
:root{--line:#111827;--muted:#64748b;--soft:#f8fafc;--soft2:#eef2f7}
*{box-sizing:border-box}
html,body{margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;color:#111827;background:#edf2f7}
body{padding:20px}
.toolbar{width:min(100%,1040px);margin:0 auto 14px;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
.btn{padding:9px 13px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#111827;text-decoration:none;font-size:13px;line-height:1.2;cursor:pointer}
.btn.primary{background:#0f172a;color:#fff;border-color:#0f172a}
.page{width:min(100%,1040px);margin:0 auto;background:#fff;padding:20px;box-shadow:0 10px 28px rgba(15,23,42,.08);border-radius:14px}
.page-title{text-align:center;font-size:22px;font-weight:700;margin:0 0 6px}
.page-sub{text-align:center;font-size:13px;color:var(--muted);margin:0 0 16px}
.stamp-card{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:18px}.stamp-item{border:1px solid #d1d5db;border-radius:10px;padding:10px;background:#fcfcfd}.stamp-item strong{display:block;font-size:11px;text-transform:uppercase;color:#374151;margin-bottom:6px}.stamp-item span{font-size:14px;font-weight:700}
.document-page{border:1.6px solid var(--line);border-radius:14px;padding:16px;margin-bottom:18px;page-break-after:always;break-after:page;background:#fff}
.document-page.last{page-break-after:auto;break-after:auto}
.doc-title{text-align:center;font-size:20px;font-weight:700;margin:0 0 14px}
.meta{display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;margin-bottom:14px}.meta-item{font-size:14px;line-height:1.4}.meta-item strong{display:inline-block;min-width:190px}
.top-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.box{border:1px solid #d1d5db;border-radius:12px;padding:12px;background:#fcfcfd;page-break-inside:avoid;break-inside:avoid}.box h3{text-align:center;font-size:15px;margin:0 0 10px}
.list{display:grid;gap:8px}.row{border:1px solid #e5e7eb;border-radius:8px;padding:8px;background:#fff}.row strong{display:block;font-size:11px;text-transform:uppercase;color:#374151;margin-bottom:4px}.row div,.row span{font-size:12px;line-height:1.4;word-break:break-word}
.images{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.images.single{grid-template-columns:1fr}.image-card{border:1px solid #d1d5db;border-radius:10px;padding:8px;background:#fff}.image-card img,.image-card iframe{width:100%;height:180px;object-fit:contain;background:#f8fafc;border-radius:8px;display:block;border:none}.image-card .label{font-size:11px;color:#374151;margin-top:6px;line-height:1.3;word-break:break-word}
.table-box{border:1px solid #d1d5db;border-radius:12px;overflow:hidden}.table-title{text-align:center;font-size:15px;font-weight:700;padding:10px;border-bottom:1px solid #d1d5db;background:#fff}
table{width:100%;border-collapse:collapse;table-layout:fixed}.report-table th,.report-table td,.feedback-table th,.feedback-table td{border:1px solid #111827;padding:7px;font-size:11px;vertical-align:top;line-height:1.35}.report-table th,.feedback-table th{background:#f1f5f9;text-align:left}.report-table img,.report-table iframe{width:100%;max-height:120px;object-fit:contain;border-radius:6px;background:#fff;border:none;display:block}.file-grid{display:grid;gap:8px}.muted{color:var(--muted)}.cell-small{white-space:normal}
.rating-badge{display:inline-flex;align-items:center;justify-content:center;min-width:42px;min-height:28px;padding:4px 10px;border-radius:999px;background:#e8f3ff;color:#0b5f82;font-weight:700}.feedback-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:12px}.small-card{border:1px solid #d1d5db;border-radius:10px;padding:10px;background:#fff}.small-card strong{display:block;font-size:11px;text-transform:uppercase;color:#374151;margin-bottom:6px}.small-card span{font-size:14px;font-weight:700}
@page{size:A4 portrait;margin:8mm}
@media print{html,body{background:#fff!important;padding:0!important;margin:0!important;font-size:10px;zoom:.88;-webkit-print-color-adjust:exact;print-color-adjust:exact}.toolbar{display:none!important}.page{width:auto!important;margin:0!important;padding:0!important;box-shadow:none!important;border-radius:0!important}.document-page{margin:0 0 10px;border:none;padding:0}.box,.row,tr,td,th{break-inside:avoid;page-break-inside:avoid}.image-card img,.image-card iframe{height:150px}.report-table img,.report-table iframe{max-height:100px}}
@media (max-width:900px){.stamp-card,.meta,.top-grid,.feedback-summary{grid-template-columns:1fr}.images{grid-template-columns:1fr}}
</style>
</head>
<body>
@php
    $viewedAt = request('viewed_at') ?: now('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s');
@endphp
<div class="toolbar">
    <a href="{{ route('admin.reports.technician', array_merge($filters, ['generate' => 1])) }}" class="btn">Back</a>
    <a href="{{ route('admin.reports.technician.merged', array_merge($filters, ['generate' => 1, 'print' => 1])) }}" class="btn">Download / Save PDF</a>
    <button class="btn primary" onclick="window.print()">Print</button>
</div>
<div class="page">
    <h1 class="page-title">Technician Reports</h1>
    <p class="page-sub">  </p>
    <div class="stamp-card">
        <div class="stamp-item"><strong>Admin Clicked View For Print</strong><span>{{ $viewedAt }}</span></div>
        <div class="stamp-item"><strong>Printed Documents</strong><span>{{ $items->count() }} completed job(s)</span></div>
    </div>

    @foreach($items as $index => $item)
        @php
            $report = $item->customer_service_report ?? [];
            $review = $item->technician_review ?? [];
            $inspect = $item->inspect_data ?? [];
            $feedback = $item->feedback ?? [];
            $approvedQuotation = $item->approvedQuotation();
            $fileUrl = function (?string $path) {
                if (!$path) return null;
                return route('files.show', ['encodedPath' => rtrim(strtr(base64_encode($path), '+/', '-_'), '=')]);
            };
            $clientFiles = collect($item->attachments ?? [])->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values();
            $quotationFiles = collect([$approvedQuotation['file'] ?? null])->filter()->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values();
            $quotationSupportingFiles = collect(data_get($approvedQuotation, 'summary_files', []))->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values();
            $visitSiteFiles = collect(data_get($review, 'visit_site_files', []))->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values();
            $inspectFiles = collect(array_merge($inspect['before_files'] ?? [], $inspect['after_files'] ?? []))->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values();
            $dailyLogs = collect($item->inspection_sessions ?? [])->map(function ($session) use ($item) {
                $session = (array) $session;
                $session['resolved_duration'] = $item->formattedDuration($item->inspectionSessionDurationSeconds($session));
                return $session;
            });
            $isLastFeedback = $loop->last;
        @endphp

        <section class="document-page">
            <h2 class="doc-title">REPORT ({{ $item->request_code }})</h2>
            <div class="meta">
                <div class="meta-item"><strong>Technician name :</strong> {{ $item->assignedTechnician?->name ?? ($report['technician_name'] ?? '-') }}</div>
                <div class="meta-item"><strong>Total Duration Completed :</strong> {{ data_get($report, 'duration_of_work') ?: $item->formattedDuration($item->totalInspectionDurationSeconds()) }}</div>
                @if($item->related_request_id)
                    <div class="meta-item"><strong>Related Job ID :</strong> {{ $item->relatedRequest?->request_code ?? $item->related_request_id }}</div>
                @endif
            </div>
            <div class="top-grid">
                <section class="box">
                    <h3>Client Details</h3>
                    <div class="list">
                        <div class="row"><strong>Client Name</strong><span>{{ $item->full_name }}</span></div>
                        <div class="row"><strong>Phone Number</strong><span>{{ $item->phone_number }}</span></div>
                        <div class="row"><strong>Request Type</strong><span>{{ $item->requestType?->name ?? '-' }}</span></div>
                        <div class="row"><strong>Location</strong><span>{{ $item->location?->name ?? '-' }}</span></div>
                        <div class="row"><strong>Department</strong><span>{{ $item->department?->name ?? '-' }}</span></div>
                        <div class="row"><strong>Urgency Level</strong><span>{{ $item->urgencyLabel() }}</span></div>
                        @foreach(($item->requestType->questions ?? []) as $question)
                            @php($answer = $item->answers[$question->id] ?? null)
                            <div class="row">
                                <strong>{{ $question->question_text }}</strong>
                                <div>
                                    @if($question->question_type === 'remark')
                                        {{ $answer ?: '-' }}
                                    @elseif($question->question_type === 'radio')
                                        {{ data_get($answer, 'value', '-') }}@if(data_get($answer, 'other')) - {{ data_get($answer, 'other') }}@endif
                                    @elseif($question->question_type === 'date_range')
                                        {{ $question->start_label ?: 'Start Date' }}: {{ data_get($answer, 'start', '-') }} / {{ $question->end_label ?: 'End Date' }}: {{ data_get($answer, 'end', '-') }}
                                    @else
                                        @php($itemsText = collect($answer ?? [])->map(fn ($selected) => trim((data_get($selected, 'value') ?? '-') . (data_get($selected, 'other') ? ' - ' . data_get($selected, 'other') : '')))->implode(', '))
                                        {{ $itemsText ?: '-' }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @if($clientFiles->isNotEmpty())
                            <div class="row">
                                <strong>Client Files</strong>
                                <div class="images {{ $clientFiles->count() === 1 ? 'single' : '' }}">
                                    @foreach($clientFiles as $file)
                                        @include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'File'])
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="box">
                    <h3>Technician review</h3>
                    <div class="list">
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
                        <div class="row"><strong>Inspection Remark</strong><span>{{ data_get($inspect, 'inspection_remark', '-') }}</span></div>
                        <div class="row"><strong>Safety Checked</strong><span>{{ data_get($inspect, 'safety_checked') ? 'Yes' : 'No' }}</span></div>
                        <div class="row"><strong>Quality Checked</strong><span>{{ data_get($inspect, 'quality_checked') ? 'Yes' : 'No' }}</span></div>
                        <div class="row"><strong>Customer Satisfaction Checked</strong><span>{{ data_get($inspect, 'customer_satisfaction_checked') ? 'Yes' : 'No' }}</span></div>
                        @if($inspectFiles->isNotEmpty())
                            <div class="row"><strong>Inspect Form Files</strong><div class="images {{ $inspectFiles->count() === 1 ? 'single' : '' }}">@foreach($inspectFiles as $file)@include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Attachment'])@endforeach</div></div>
                        @endif
                    </div>
                </section>
            </div>

            <section class="table-box">
                <div class="table-title">Technician daily log record</div>
                <table class="report-table">
                    <thead><tr><th style="width:12%;">Date</th><th style="width:13%;">Started at</th><th style="width:13%;">End time</th><th style="width:12%;">Duration</th><th style="width:22%;">Remark</th><th style="width:28%;">Uploaded files</th></tr></thead>
                    <tbody>
                        @forelse($dailyLogs as $session)
                            <tr>
                                <td class="cell-small">{{ $session['date_label'] ?? '-' }}</td>
                                <td class="cell-small">{{ $session['time_start'] ?? '-' }}</td>
                                <td class="cell-small">{{ $session['time_end'] ?? '-' }}</td>
                                <td class="cell-small">{{ $session['resolved_duration'] ?? '-' }}</td>
                                <td>{{ $session['remark'] ?? '-' }}</td>
                                <td>
                                    @php($files = collect($session['attachments'] ?? [])->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values())
                                    @if($files->isEmpty())
                                        <span class="muted">-</span>
                                    @else
                                        <div class="file-grid">@foreach($files as $file)@include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Attachment'])@endforeach</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="text-align:center;">No technician daily log record.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </section>

        <section class="document-page {{ $isLastFeedback ? 'last' : '' }}">
            <h2 class="doc-title">CLIENT FEEDBACK REPORT ({{ $item->request_code }})</h2>
            <div class="meta">
                <div class="meta-item"><strong>Client Name :</strong> {{ $item->full_name }}</div>
                <div class="meta-item"><strong>Phone Number :</strong> {{ $item->phone_number }}</div>
                <div class="meta-item"><strong>Request Type :</strong> {{ $item->requestType?->name ?? '-' }}</div>
                <div class="meta-item"><strong>Submitted At :</strong> {{ $item->customer_review_submitted_at ? $item->customer_review_submitted_at->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') : '-' }}</div>
            </div>
            <section class="box">
                <h3>Customer Review & Feedback</h3>
                <table class="feedback-table">
                    <thead><tr><th style="width:26%;">Section</th><th style="width:50%;">Question</th><th style="width:12%;">Rating</th><th style="width:12%;">Score</th></tr></thead>
                    <tbody>
                        @php($hasRows = false)
                        @foreach($feedbackSections as $sectionKey => $section)
                            @foreach($section['questions'] as $questionKey => $questionText)
                                @php($rating = data_get($feedback, "ratings.$sectionKey.$questionKey"))
                                @if($rating)
                                    @php($hasRows = true)
                                    <tr>
                                        <td>{{ $section['title'] }}</td>
                                        <td>{{ $questionText }}</td>
                                        <td><span class="rating-badge">{{ $rating }}/5</span></td>
                                        <td>{{ $rating }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        @endforeach
                        @if(!$hasRows)
                            <tr><td colspan="4" style="text-align:center;">No client feedback submitted.</td></tr>
                        @endif
                    </tbody>
                </table>
                <div class="feedback-summary">
                    <div class="small-card"><strong>Average Rating</strong><span>{{ $item->feedbackAverage() ? number_format($item->feedbackAverage(), 2) . ' / 5' : '-' }}</span></div>
                    <div class="small-card"><strong>Total Questions Answered</strong><span>{{ collect(data_get($feedback, 'ratings', []))->flatten()->filter(fn($value) => is_numeric($value))->count() }}</span></div>
                    <div class="small-card"><strong>Overall Percentage</strong><span>{{ $item->feedbackAverage() ? number_format(($item->feedbackAverage() / 5) * 100, 0) . '%' : '-' }}</span></div>
                </div>
                <div class="row" style="margin-top:12px;">
                    <strong>Additional Comments / Suggestions</strong>
                    <div>{{ data_get($feedback, 'additional_comments') ?: '-' }}</div>
                </div>
            </section>
        </section>
    @endforeach
</div>
@include('components.print-pdf-script')
@if($printMode)<script>window.addEventListener('load',()=>window.__maps2uPrintRenderReady?.finally(() => window.print()));</script>@endif
</body>
</html>
