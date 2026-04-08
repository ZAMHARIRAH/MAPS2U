<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Feedback Report {{ $job->request_code }}</title>
<style>
:root{--line:#111827;--muted:#64748b;--soft:#f8fafc;}
*{box-sizing:border-box}
html,body{margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;color:#111827;background:#edf2f7}
body{padding:20px}
.toolbar{width:min(100%,1020px);margin:0 auto 14px;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
.btn{padding:9px 13px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#111827;text-decoration:none;font-size:13px;line-height:1.2}.btn.primary{background:#0f172a;color:#fff;border-color:#0f172a}
.page{width:min(100%,1020px);margin:0 auto;background:#fff;padding:20px;box-shadow:0 10px 28px rgba(15,23,42,.08);border-radius:14px}
.report-title{text-align:center;font-size:22px;font-weight:700;margin:0 0 16px}
.meta{display:grid;grid-template-columns:1fr 1fr;gap:8px 20px;margin-bottom:14px}.meta-item{font-size:14px;line-height:1.4}.meta-item strong{display:inline-block;min-width:180px}
.box{border:1.6px solid var(--line);border-radius:12px;padding:12px;margin-bottom:14px}.box h2{text-align:center;font-size:15px;margin:0 0 10px}
.section-list{display:grid;gap:8px}.row{border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;background:#fcfcfd}.row strong{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#374151;margin-bottom:4px}.row span,.row div{font-size:12px;line-height:1.4;word-break:break-word}
.feedback-table{width:100%;border-collapse:collapse;table-layout:fixed}.feedback-table th,.feedback-table td{border:1px solid #111827;padding:8px;font-size:12px;vertical-align:top;line-height:1.35}.feedback-table th{background:#f1f5f9;text-align:left}.rating-badge{display:inline-flex;align-items:center;justify-content:center;min-width:42px;min-height:30px;padding:4px 10px;border-radius:999px;background:#e8f3ff;color:#0b5f82;font-weight:700}.summary-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:12px}.summary-card{border:1px solid #d1d5db;border-radius:10px;padding:10px;background:#fcfcfd}.summary-card strong{display:block;font-size:11px;text-transform:uppercase;color:#374151;margin-bottom:6px}.summary-card span{font-size:14px;font-weight:700}
@page{size:A4 portrait;margin:8mm}
@media print{html,body{background:#fff!important;padding:0!important;margin:0!important;font-size:10px;zoom:.9;-webkit-print-color-adjust:exact;print-color-adjust:exact}.toolbar{display:none!important}.page{width:auto!important;margin:0!important;padding:0!important;box-shadow:none!important;border-radius:0!important}.box,.row,tr,td,th{break-inside:avoid;page-break-inside:avoid}}
@media (max-width:900px){.meta,.summary-grid{grid-template-columns:1fr}.meta-item strong{display:block;min-width:0}}
</style>
</head>
<body>
@php($feedback = $job->feedback ?? [])
<div class="toolbar">
    <a href="{{ $backRoute ?? route('admin.reports.technician') }}" class="btn">Back</a>
    <a href="{{ $printRoute ?? route('admin.reports.technician.feedback-report', [$job, 'print' => 1]) }}" class="btn">Download / Save PDF</a>
    <button class="btn primary" onclick="window.print()">Print</button>
</div>
<div class="page">
    <h1 class="report-title">CLIENT FEEDBACK REPORT ({{ $job->request_code }})</h1>
    <div class="meta">
        <div class="meta-item"><strong>Client Name :</strong> {{ $job->full_name }}</div>
        <div class="meta-item"><strong>Phone Number :</strong> {{ $job->phone_number }}</div>
        <div class="meta-item"><strong>Request Type :</strong> {{ $job->requestType?->name ?? '-' }}</div>
        <div class="meta-item"><strong>Submitted At :</strong> {{ $job->customer_review_submitted_at ? $job->customer_review_submitted_at->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') : '-' }}</div>
    </div>
    <section class="box">
        <h2>Customer Review & Feedback</h2>
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
        <div class="summary-grid">
            <div class="summary-card"><strong>Average Rating</strong><span>{{ $job->feedbackAverage() ? number_format($job->feedbackAverage(), 2) . ' / 5' : '-' }}</span></div>
            <div class="summary-card"><strong>Total Questions Answered</strong><span>{{ collect(data_get($feedback, 'ratings', []))->flatten()->filter(fn($value) => is_numeric($value))->count() }}</span></div>
            <div class="summary-card"><strong>Overall Percentage</strong><span>{{ $job->feedbackAverage() ? number_format(($job->feedbackAverage() / 5) * 100, 0) . '%' : '-' }}</span></div>
            <div class="summary-card"><strong>Submission Mode</strong><span>{{ data_get($feedback, 'submission_mode') === 'agree_all' ? 'Agree All' : 'Manual Form' }}</span></div>
        </div>
        <div class="row" style="margin-top:12px;">
            <strong>Additional Comments / Suggestions</strong>
            <div>{{ data_get($feedback, 'additional_comments', '-') ?: '-' }}</div>
        </div>
    </section>
</div>
@if($printMode)<script>window.addEventListener('load',()=>window.print());</script>@endif
</body>
</html>
