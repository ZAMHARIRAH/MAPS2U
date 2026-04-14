<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Print {{ $submission->request_code }}</title>
<style>
:root{--line:#111827;--muted:#64748b;--soft:#f8fafc;}*{box-sizing:border-box}body{margin:0;padding:14px;background:#edf2f7;color:#111827;font-family:Arial,Helvetica,sans-serif}.toolbar{width:min(100%,1180px);margin:0 auto 16px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}.btn{padding:10px 14px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#111827;text-decoration:none;font-size:11px}.btn.primary{background:#0f172a;color:#fff;border-color:#0f172a}.page{width:min(100%,1180px);margin:0 auto;background:#fff;padding:16px;box-shadow:0 12px 35px rgba(15,23,42,.08)}h1{text-align:center;font-size:21px;margin:0 0 20px}.meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 28px;margin-bottom:18px}.meta-item{font-size:15px;line-height:1.5}.meta-item strong{display:inline-block;min-width:180px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.box{border:2px solid var(--line);border-radius:14px;padding:16px;page-break-inside:avoid;break-inside:avoid}.box h2{text-align:center;font-size:18px;margin:0 0 14px}.section-list{display:grid;gap:10px}.row{border:1px solid #d1d5db;border-radius:10px;padding:10px 12px;background:#fcfcfd}.row strong{display:block;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#374151;margin-bottom:4px}.row span,.row div{font-size:14px;line-height:1.45;word-break:break-word}.images{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.images.single{grid-template-columns:1fr}.image-card{border:1px solid #d1d5db;border-radius:10px;padding:8px;background:#fff}.image-card img{width:100%;height:auto;max-height:520px;object-fit:contain;background:#f8fafc;border-radius:8px;display:block}.image-card iframe{width:100%;height:720px;border:none;border-radius:8px;background:#fff;display:block}.pdf-print-stack{display:grid;gap:12px}.pdf-page-card{border:1px solid #d1d5db;border-radius:10px;padding:8px;background:#fff}.pdf-page-card canvas{display:block;width:100%;height:auto;border-radius:8px;background:#fff}.pdf-print-loading{padding:18px;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc;color:#475569;font-size:13px}.image-card .label{font-size:12px;color:#374151;margin-top:6px}.table-box{border:2px solid var(--line);border-radius:14px;overflow:hidden;margin-top:20px}.table-title{text-align:center;font-size:18px;padding:14px;border-bottom:2px solid var(--line)}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid var(--line);padding:10px;vertical-align:top;font-size:13px}th{text-align:center;background:#f8fafc}.signature-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}.signature-grid img{width:100%;max-height:220px;object-fit:contain;border:1px solid #d1d5db;border-radius:10px;background:#fff} .wide{grid-column:1 / -1} @page{size:A4 portrait;margin:6mm}@media print{body{background:#fff;padding:0}.toolbar{display:none}.page{width:auto;margin:0;box-shadow:none;padding:0}.box,.row,.table-box,tr,td,th{break-inside:avoid;page-break-inside:avoid}}@media (max-width:980px){.grid,.meta,.signature-grid,.images{grid-template-columns:1fr}}
</style>
</head>
<body>
@php
$fileUrl = function ($path) {
    return $path ? route('files.show', ['encodedPath' => rtrim(strtr(base64_encode($path), '+/', '-_'), '=')]) : null;
};
$approved = $submission->approvedQuotation();
$review = $submission->technician_review ?? [];
$inspect = $submission->inspect_data ?? [];
$report = $submission->customer_service_report ?? [];
$feedback = $submission->feedback ?? [];
$clientFiles = collect($submission->attachments ?? [])->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values();
$visitFiles = collect(data_get($review, 'visit_site_files', []))->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values();
$inspectFiles = collect(array_merge(data_get($inspect, 'before_files', []), data_get($inspect, 'after_files', [])))->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values();
$quotationFiles = collect([$approved['file'] ?? null])->filter()->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values();
$quotationSupportingFiles = collect(data_get($approved, 'summary_files', []))->filter(fn ($file) => !empty($file['path']) && (str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf')))->values();
$paymentHistory = collect($submission->payment_receipt_history ?? [])->values();
if ($paymentHistory->isEmpty() && !empty($submission->payment_receipt_files)) {
    $paymentHistory = collect([[ 'payment_type' => $submission->payment_type, 'uploaded_label' => optional($submission->updated_at)->format('d M Y H:i:s'), 'files' => $submission->payment_receipt_files ]]);
}
$dailyLogs = collect($submission->inspection_sessions ?? [])->values();
$hasTechnicianReview = !empty(array_filter($review, fn ($value) => !is_array($value) ? filled($value) : !empty($value)));
$hasInspect = !empty(array_filter($inspect, fn ($value) => !is_array($value) ? filled($value) : !empty($value)));
$hasCustomerService = !empty($report);
$hasFeedback = !empty($feedback);
$hasBooleanInspect = collect(['safety_checked', 'quality_checked', 'customer_satisfaction_checked', 'add_related_job'])->contains(fn ($key) => array_key_exists($key, $inspect));
$approvalRemarks = collect($submission->approvalPrintableRemarks());
$sharedRemarks = collect($submission->adminTechnicianRemarkLines());
@endphp
<div class="toolbar">
    <a href="{{ route('admin.incoming-requests.show', $submission) }}" class="btn">Back</a>
    <button class="btn primary" onclick="window.print()">Print</button>
</div>
<div class="page">
    <h1>ADMIN JOB PRINT ({{ $submission->request_code }})</h1>
    <div class="meta">
        <div class="meta-item"><strong>Client Name :</strong> {{ $submission->full_name }}</div>
        <div class="meta-item"><strong>Phone Number :</strong> {{ $submission->phone_number }}</div>
        <div class="meta-item"><strong>Request Type :</strong> {{ $submission->requestType?->name ?? '-' }}</div>
        <div class="meta-item"><strong>Status :</strong> {{ $submission->status }}</div>
        <div class="meta-item"><strong>Location :</strong> {{ $submission->location?->name ?? '-' }}</div>
        <div class="meta-item"><strong>Assigned Technician :</strong> {{ $submission->assignedTechnician?->name ?? '-' }}</div>
    </div>
    <div class="grid">
        <section class="box">
            <h2>Client Details / Request Form</h2>
            <div class="section-list">
                <div class="row"><strong>Role</strong><span>{{ $submission->user?->roleLabel() ?? '-' }}</span></div>
                @if($submission->department)<div class="row"><strong>Department</strong><span>{{ $submission->department?->name }}</span></div>@endif
                <div class="row"><strong>Urgency Level</strong><span>{{ $submission->urgencyLabel() }}</span></div>
                @foreach($submission->requestType->questions as $question)
                    @php($answer = $submission->answers[$question->id] ?? null)
                    @php($answerText = match ($question->question_type) {
                        'remark' => is_string($answer) ? trim($answer) : null,
                        'radio', 'task_title' => trim((string) data_get($answer, 'value', '')) . (data_get($answer, 'other') ? ' - ' . data_get($answer, 'other') : ''),
                        'date_range' => (data_get($answer, 'start') || data_get($answer, 'end')) ? (($question->start_label ?: 'Start Date') . ': ' . (data_get($answer, 'start') ?: '-') . ' / ' . ($question->end_label ?: 'End Date') . ': ' . (data_get($answer, 'end') ?: '-')) : null,
                        default => collect($answer ?? [])->map(fn ($selected) => trim((string) data_get($selected, 'value', '') . (data_get($selected, 'other') ? ' - ' . data_get($selected, 'other') : '')))->filter()->implode(', '),
                    })
                    @if(filled($answerText))
                    <div class="row">
                        <strong>{{ $question->question_text }}</strong>
                        <div>{{ $answerText }}</div>
                    </div>
                    @endif
                @endforeach
                @foreach($approvalRemarks as $remark)
                    <div class="row"><strong>{{ $remark['label'] }}</strong><span>{!! nl2br(e($remark['value'])) !!}</span></div>
                @endforeach
                @if($clientFiles->isNotEmpty())
                    <div class="row"><strong>Client Attachments</strong><div class="images {{ $clientFiles->count() === 1 ? 'single' : '' }}">@foreach($clientFiles as $file)@include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Attachment'])@endforeach</div></div>
                @endif
            </div>
        </section>

        @if($hasTechnicianReview || $approved || $hasInspect)
        <section class="box">
            <h2>Technician Form / Quotation / Inspect</h2>
            <div class="section-list">
                @if($hasTechnicianReview)
                    @if(data_get($review, 'clarification_level'))<div class="row"><strong>Clarification</strong><span>{{ ucfirst(str_replace('_', ' ', data_get($review, 'clarification_level'))) }}</span></div>@endif
                    @if(data_get($review, 'repair_channel'))<div class="row"><strong>Repair Channel</strong><span>{{ ucfirst(str_replace('_', ' ', data_get($review, 'repair_channel'))) }}</span></div>@endif
                    @if(data_get($review, 'repair_scale'))<div class="row"><strong>Repair Scale</strong><span>{{ ucfirst(str_replace('_', ' ', data_get($review, 'repair_scale'))) }}</span></div>@endif
                    @if(data_get($review, 'processing_type'))<div class="row"><strong>Processing Type</strong><span>{{ ucfirst(str_replace('_', ' ', data_get($review, 'processing_type'))) }}</span></div>@endif
                    @if(array_key_exists('visit_site', $review))<div class="row"><strong>Visit Site</strong><span>{{ ucfirst((string) data_get($review, 'visit_site', 'no')) }}</span></div>@endif
                    @if(data_get($review, 'visit_site_remark'))<div class="row"><strong>Visit Site Remark</strong><span>{{ data_get($review, 'visit_site_remark') }}</span></div>@endif
                    @if($visitFiles->isNotEmpty())<div class="row"><strong>Visit Site Files</strong><div class="images {{ $visitFiles->count() === 1 ? 'single' : '' }}">@foreach($visitFiles as $file)@include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Attachment'])@endforeach</div></div>@endif
                @endif
                @if($sharedRemarks->isNotEmpty())<div class="row"><strong>Admin ↔ Technician Remarks</strong>@foreach($sharedRemarks as $remark)<div style="margin-top:8px;"><strong style="font-size:12px;">{{ $remark['header'] }}</strong><div>{!! nl2br(e($remark['remark'])) !!}</div></div>@endforeach</div>@endif
                @if($approved)
                    @if(data_get($approved, 'company_name'))<div class="row"><strong>Approved Quotation Company</strong><span>{{ data_get($approved, 'company_name') }}</span></div>@endif
                    @if(data_get($approved, 'amount') !== null && data_get($approved, 'amount') !== '')<div class="row"><strong>Approved Quotation Amount</strong><span>{{ 'RM ' . number_format((float) data_get($approved, 'amount'), 2) }}</span></div>@endif
                    @if(data_get($approved, 'admin_signed_at'))<div class="row"><strong>Admin Approved At</strong><span>{{ data_get($approved, 'admin_signed_at') }}</span></div>@endif
                    @if($quotationFiles->isNotEmpty())<div class="row"><strong>Approved Quotation File</strong><div class="images single">@foreach($quotationFiles as $file)@include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Attachment'])@endforeach</div></div>@endif
                    @if($quotationSupportingFiles->isNotEmpty())<div class="row"><strong>Quotation Supporting Files</strong><div class="images {{ $quotationSupportingFiles->count() === 1 ? 'single' : '' }}">@foreach($quotationSupportingFiles as $file)@include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Supporting file'])@endforeach</div></div>@endif
                @endif
                @if($submission->scheduled_date || $submission->scheduled_time || $submission->payment_type)
                    <div class="row"><strong>Payment Type</strong><span>{{ $submission->payment_type ? ucfirst(str_replace('_', ' ', $submission->payment_type)) : '-' }}</span></div>
                    <div class="row"><strong>Schedule</strong><span>{{ optional($submission->scheduled_date)->format('d M Y') ?: '-' }} {{ $submission->scheduled_time ?: '' }}</span></div>
                @endif
                @foreach($paymentHistory as $history)
                    <div class="row"><strong>Receipt Upload</strong><div>{{ ucfirst(str_replace('_', ' ', $history['payment_type'] ?? '-')) }} @ {{ $history['uploaded_label'] ?? ($history['uploaded_at'] ?? '-') }}</div>@if(!empty($history['files']))<div class="images {{ count($history['files']) === 1 ? 'single' : '' }}" style="margin-top:10px;">@foreach($history['files'] as $file)@include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Attachment'])@endforeach</div>@endif</div>
                @endforeach
                @if($hasInspect)
                    @if(data_get($inspect, 'inspection_remark'))<div class="row"><strong>Inspection Remark</strong><span>{{ data_get($inspect, 'inspection_remark') }}</span></div>@endif
                    @if(array_key_exists('safety_checked', $inspect))<div class="row"><strong>Safety Checked</strong><span>{{ data_get($inspect, 'safety_checked') ? 'Yes' : 'No' }}</span></div>@endif
                    @if(array_key_exists('quality_checked', $inspect))<div class="row"><strong>Quality Checked</strong><span>{{ data_get($inspect, 'quality_checked') ? 'Yes' : 'No' }}</span></div>@endif
                    @if(array_key_exists('customer_satisfaction_checked', $inspect))<div class="row"><strong>Customer Satisfaction Checked</strong><span>{{ data_get($inspect, 'customer_satisfaction_checked') ? 'Yes' : 'No' }}</span></div>@endif
                    @if(array_key_exists('add_related_job', $inspect))<div class="row"><strong>Add Related Job</strong><span>{{ data_get($inspect, 'add_related_job') ? 'Yes' : 'No' }}</span></div>@endif
                    @if($inspectFiles->isNotEmpty())<div class="row"><strong>Inspect Files</strong><div class="images {{ $inspectFiles->count() === 1 ? 'single' : '' }}">@foreach($inspectFiles as $file)@include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Attachment'])@endforeach</div></div>@endif
                @endif
            </div>
        </section>
        @endif
    </div>

    @if($dailyLogs->isNotEmpty())
    <section class="table-box">
        <div class="table-title">Technician Daily Log Record</div>
        <table>
            <thead><tr><th style="width:12%;">Date</th><th style="width:12%;">Start</th><th style="width:12%;">End</th><th style="width:12%;">Duration</th><th style="width:20%;">Remark</th><th style="width:32%;">Attachment / Signatures</th></tr></thead>
            <tbody>
                @foreach($dailyLogs as $session)
                    <tr>
                        <td>{{ $session['date_label'] ?? '-' }}</td>
                        <td>{{ $session['time_start'] ?? '-' }}</td>
                        <td>{{ $session['time_end'] ?? '-' }}</td>
                        <td>{{ $session['duration_label'] ?? '-' }}</td>
                        <td>{{ $session['remark'] ?? '-' }}@if(!empty($session['verify_by_signed_at_label']))<div class="muted" style="margin-top:6px;">Verify signed: {{ $session['verify_by_signed_at_label'] }}</div>@endif</td>
                        <td>
                            <div><strong>PIC:</strong> {{ $session['person_in_charge'] ? 'Signed' : '-' }}</div>
                            <div><strong>Verify:</strong> {{ $session['verify_by'] ? 'Signed' : '-' }}</div>
                            @if(!empty($session['verify_by']))<div class="images single" style="margin-top:10px;">@include('components.print-media-card', ['file' => ['path' => null, 'data_url' => $session['verify_by'], 'mime_type' => 'image/png', 'original_name' => 'Verify Signature'], 'label' => 'Verify Signature'])</div>@endif
                            @php($sessionFiles = collect($session['attachments'] ?? [])->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values())
                            @if($sessionFiles->isNotEmpty())
                                <div class="images {{ $sessionFiles->count() === 1 ? 'single' : '' }}" style="margin-top:10px;">
                                    @foreach($sessionFiles as $file)
                                        @include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Attachment'])
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
    @endif

    @if($hasCustomerService)
    <section class="table-box">
        <div class="table-title">Customer Service Report</div>
        <div style="padding:16px;display:grid;gap:12px;">
            <div class="row"><strong>Technician</strong><span>{{ data_get($report, 'technician_name', '-') }}</span></div>
            <div class="row"><strong>Date Inspection</strong><span>{{ data_get($report, 'date_inspection', '-') }}</span></div>
            <div class="row"><strong>Duration Of Work</strong><span>{{ data_get($report, 'duration_of_work', '-') }}</span></div>
            <div class="row"><strong>Description Of Work</strong><div>{!! nl2br(e(data_get($report, 'description_of_work', '-'))) !!}</div></div>
            @if(!empty(data_get($report, 'description_entries', [])))<div class="row"><strong>Description Remark Evidence</strong><div style="display:grid;gap:10px;">@foreach(data_get($report, 'description_entries', []) as $entry)<div style="border:1px solid #dbe7f5;border-radius:10px;padding:10px;"><div><strong>{{ $entry['date_label'] ?? '-' }}</strong> • {{ $entry['time_range'] ?? '-' }} • {{ $entry['duration_label'] ?? '-' }}</div><div style="margin-top:6px;">{{ $entry['remark'] ?? '-' }}</div><div class="muted" style="margin-top:6px;">Verify signed: {{ $entry['verify_by_signed_at_label'] ?? '-' }}</div>@if(!empty($entry['verify_by_signature']))<div class="images single" style="margin-top:8px;">@include('components.print-media-card', ['file' => ['path' => null, 'data_url' => $entry['verify_by_signature'], 'mime_type' => 'image/png', 'original_name' => 'CSR Verify Signature'], 'label' => 'CSR Verify Signature'])</div>@endif</div>@endforeach</div></div>@endif
            <div class="row"><strong>Suggestion / Recommendation</strong><div>{{ data_get($report, 'suggestion_recommendation', '-') ?: '-' }}</div></div>
            @php($csrFiles = collect(data_get($report, 'attachments', []))->filter(fn ($file) => str_contains(strtolower($file['mime_type'] ?? ''), 'image') || str_contains(strtolower($file['mime_type'] ?? ''), 'pdf'))->values())
            @if($csrFiles->isNotEmpty())<div class="row"><strong>CSR Attachments</strong><div class="images {{ $csrFiles->count() === 1 ? 'single' : '' }}">@foreach($csrFiles as $file)@include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Attachment'])@endforeach</div></div>@endif
            @if(data_get($report, 'person_in_charge_signature') || data_get($report, 'verify_by_signature'))<div class="signature-grid">@if(data_get($report, 'person_in_charge_signature'))<div><strong>Person In Charge Signature</strong><img src="{{ data_get($report, 'person_in_charge_signature') }}" alt="PIC signature"></div>@endif @if(data_get($report, 'verify_by_signature'))<div><strong>Verify By Signature</strong><img src="{{ data_get($report, 'verify_by_signature') }}" alt="Verify signature"></div>@endif</div>@endif
        </div>
    </section>
    @endif

    @if($hasFeedback)
    <section class="table-box">
        <div class="table-title">Client Feedback</div>
        <div style="padding:16px;display:grid;gap:12px;">
            <div class="row"><strong>Submission Mode</strong><span>{{ data_get($feedback, 'submission_mode') === 'agree_all' ? 'Agree All (Scale ' . (data_get($feedback, 'agree_all_scale') ?: '-') . ')' : 'Manual Form' }}</span></div>
            <div class="row"><strong>Average Rating</strong><span>{{ $submission->feedbackAverage() ? number_format($submission->feedbackAverage(), 2) . ' / 5' : '-' }}</span></div>
            <div class="row"><strong>Remark</strong><span>{{ data_get($feedback, 'additional_comments', '-') ?: '-' }}</span></div>
        </div>
    </section>
    @endif
</div>
@include('components.print-pdf-script')
@if($printMode)<script>window.addEventListener('load',()=>window.__maps2uPrintRenderReady?.finally(() => window.print()));</script>@endif
</body>
</html>
