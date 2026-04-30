@extends('layouts.app', ['title' => ($pageTitle ?? 'Finance Form')])
@section('content')
@php
    $finance = $submission->finance_form ?? [];
    $documentSuffix = strtoupper(now('Asia/Kuala_Lumpur')->format('M')) . now('Asia/Kuala_Lumpur')->format('Y');
    $referenceCode = 'LCISB/FIN/PRF/V4/' . $documentSuffix;
    $templateUrl = asset('finance-template-fillable.pdf');
    $filledPdfPath = data_get($finance, 'filled_pdf_path');
    $savedPdfUrl = $filledPdfPath ? route('files.show', ['encodedPath' => rtrim(strtr(base64_encode($filledPdfPath), '+/', '-_'), '=')]) : null;
    $approvedQuotation = $submission->approvedQuotation();
    $quotationFile = data_get($approvedQuotation, 'file');
    $quotationSupportingFiles = collect(data_get($approvedQuotation, 'summary_files', []))->filter()->values();
    $receiptHistory = collect($submission->payment_receipt_history ?? [])->values();
    if ($receiptHistory->isEmpty() && !empty($submission->payment_receipt_files)) {
        $receiptHistory = collect([[
            'payment_type' => $submission->payment_type,
            'uploaded_at' => optional($submission->updated_at)->timezone('Asia/Kuala_Lumpur')->format('d M Y h:i A'),
            'uploaded_label' => optional($submission->updated_at)->timezone('Asia/Kuala_Lumpur')->format('d M Y h:i A'),
            'files' => $submission->payment_receipt_files,
        ]]);
    }
    $receiptHistory = $receiptHistory->map(function ($history) {
        $history['files'] = collect($history['files'] ?? [])->filter()->values()->all();
        return $history;
    })->values();
    $vendor = collect(data_get($approvedQuotation, 'vendor_snapshot', []))->filter(fn ($value) => filled($value));
    $vendorLabelMap = [
        'company_name' => 'Vendor Company',
        'vendor_name' => 'Vendor Name',
        'registration_name' => 'Registration Name',
        'ssm_number' => 'SSM Number',
        'phone_number' => 'Phone Number',
        'office_phone' => 'Office Phone',
        'email' => 'Email',
        'person_in_charge' => 'Person In Charge',
        'office_address' => 'Office Address',
        'address' => 'Address',
        'bank_name' => 'Bank Name',
        'bank_account_number' => 'Bank Account Number',
        'bank_account_name' => 'Bank Account Name',
    ];
    $vendorDetails = $vendor->map(function ($value, $key) use ($vendorLabelMap) {
        $label = $vendorLabelMap[$key] ?? ucwords(str_replace('_', ' ', (string) $key));
        return ['label' => $label, 'value' => is_array($value) ? implode(', ', array_filter($value)) : $value];
    })->values();
    $isViewer = $isViewer ?? false;
@endphp

<style>
@media print{
    .no-print{display:none !important;}
    .print-finance-page{display:block !important;margin:0 !important;padding:0 !important;}
    .print-finance-section{break-inside:avoid;page-break-inside:avoid;margin:0 0 8px 0;}
    .print-page-break-before{break-before:page;page-break-before:always;}
    .print-finance-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;}
    .print-finance-images{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;align-items:start;}
    .print-finance-images.single{grid-template-columns:1fr;}
    .print-finance-card{border:1px solid #cbd5e1;border-radius:8px;padding:8px;background:#fff;}
    .print-finance-card h3{margin:0 0 6px;font-size:12px;}
    .print-finance-card p{margin:0;font-size:9.5px;line-height:1.3;}
    .finance-pdf-preview-frame,.finance-pdf-shell iframe{display:none !important;}
    .panel{border:none !important;box-shadow:none !important;padding:0 !important;}
    .summary-card{padding:6px !important;border-radius:8px !important;}
    .summary-card strong{font-size:9.5px !important;}
    .summary-card span{font-size:10.5px !important;line-height:1.25 !important;}
    .file-preview-head a,.inline-preview-frame,.inline-preview-image{display:none !important;}
}
</style>

<div class="page-header no-print">
    <div>
        <h1>{{ $pageTitle ?? 'Finance Form' }}</h1>
        <p>{{ $isViewer ? 'Viewer can only view finance forms that have already been uploaded by admin.' : 'Review the signed approved quotation together with payment receipt history below.' }}</p>
    </div>
    <div class="actions-inline">
        <a class="btn ghost" href="{{ route($backRoute ?? 'admin.finance.index') }}">Back</a>
        @if($savedPdfUrl)
            <a class="btn ghost" href="{{ $savedPdfUrl }}" target="_blank" rel="noopener">Open Saved PDF</a>
        @endif
        <button class="btn accent" type="button" onclick="window.print()">Print Finance Form</button>
    </div>
</div>

<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Finance Form Overview</h3></div>
    <div class="summary-stack" style="grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;">
        <div class="summary-card compact-summary"><strong>Job ID</strong><span>{{ $submission->request_code }}</span></div>
        <div class="summary-card compact-summary"><strong>Reference Code</strong><span>{{ data_get($finance, 'reference_code') ?: $referenceCode }}</span></div>
        <div class="summary-card compact-summary"><strong>Client</strong><span>{{ $submission->full_name ?: ($submission->user?->name ?? '-') }}</span></div>
        <div class="summary-card compact-summary"><strong>Request Type</strong><span>{{ $submission->requestType?->name ?? '-' }}</span></div>
        <div class="summary-card compact-summary"><strong>Location</strong><span>{{ $submission->location?->name ?? '-' }}</span></div>
        <div class="summary-card compact-summary"><strong>Approved Amount</strong><span>RM {{ number_format((float) (data_get($finance, 'approved_amount') ?: data_get($approvedQuotation, 'amount') ?: 0), 2) }}</span></div>
        <div class="summary-card compact-summary"><strong>Technician</strong><span>{{ $submission->assignedTechnician?->name ?? '-' }}</span></div>
        <div class="summary-card compact-summary"><strong>Approved At</strong><span>{{ data_get($approvedQuotation, 'admin_signed_at') ?: '-' }}</span></div>
    </div>
</section>

@if(!$isViewer)
<section class="panel no-print finance-pdf-panel-direct" style="margin-top:20px;">
    <div class="panel-head"><h3>Finance PDF Container</h3></div>
    <div class="finance-code-banner finance-code-banner-inline">
        <span class="finance-code-prefix">Reference Code</span>
        <span class="finance-code-value">{{ $referenceCode }}</span>
    </div>
    <p class="helper-text" style="margin-top:10px;"> </p>
    <div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(320px,1fr);gap:18px;align-items:start;margin-top:14px;">
        <div>
            <div class="finance-pdf-shell"><iframe id="finance-pdf-preview" class="finance-pdf-preview-frame finance-pdf-preview-frame-large" title="Finance PDF preview"></iframe></div>
            <span id="finance-preview-status" class="helper-text" style="display:block;margin-top:10px;">Preparing finance PDF...</span>
        </div>
        <div class="panel" style="margin:0;background:#fbfcfe;display:grid;gap:14px;">
            <div class="panel-head" style="margin-bottom:0;"><h3 style="margin:0;">Finance Evidence</h3></div>
            <div class="finance-preview-card">
                <strong>{{ ($approvedQuotation['type'] ?? null) === 'costing' ? 'Approved Costing Details' : 'Vendor Quotation Details' }}</strong>
                <small class="helper-text"> </small>
                @if($quotationFile)
                    <div style="margin-top:12px;">@include('components.file-preview', ['file' => $quotationFile, 'label' => data_get($quotationFile, 'original_name', 'Approved quotation')])</div>
                @endif
                @if($vendorDetails->isNotEmpty())
                    <div class="summary-stack" style="margin-top:12px;">
                        @foreach($vendorDetails as $detail)
                            <div class="summary-card compact-summary {{ strlen((string) $detail['value']) > 26 ? 'span-2' : '' }}"><strong>{{ $detail['label'] }}</strong><span>{{ $detail['value'] ?: '-' }}</span></div>
                        @endforeach
                    </div>
                @endif
                @if(($approvedQuotation['type'] ?? null) === 'costing' && !empty($approvedQuotation['items']))
                    <div class="summary-stack" style="margin-top:12px;">
                        @foreach($approvedQuotation['items'] as $costItem)
                            <div class="summary-card compact-summary">
                                <strong>{{ $costItem['equipment_type'] ?? '-' }}</strong>
                                <span>RM {{ number_format((float) ($costItem['equipment_price'] ?? 0), 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if($quotationSupportingFiles->isNotEmpty())
                    <div class="preview-grid single-column" style="margin-top:12px;">
                        @foreach($quotationSupportingFiles as $file)
                            @include('components.file-preview', ['file' => $file, 'label' => $file['original_name'] ?? 'Quotation supporting file'])
                        @endforeach
                    </div>
                @endif
                @if(!$quotationFile)
                    <p class="helper-text" style="margin-top:10px;">{{ ($approvedQuotation['type'] ?? null) === 'costing' ? 'No quotation file is required for costing form. Refer to costing item and receipt evidence.' : 'No approved quotation file is available yet.' }}</p>
                @endif
            </div>
            <div class="finance-preview-card">
                <strong>Payment Receipt History</strong>
                <small class="helper-text">All uploaded receipts are grouped by submission time.</small>
                @if($receiptHistory->isNotEmpty())
                    <div style="display:grid;gap:12px;margin-top:12px;">
                        @foreach($receiptHistory as $history)
                            <div class="summary-card compact-summary span-2">
                                <strong>{{ ucfirst(str_replace('_', ' ', $history['payment_type'] ?? '-')) }}</strong>
                                <span>{{ $history['uploaded_label'] ?? ($history['uploaded_at'] ?? '-') }}</span>
                                @if(!empty($history['files']))
                                    <div class="preview-grid single-column" style="margin-top:12px;">
                                        @foreach($history['files'] as $file)
                                            @include('components.file-preview', ['file' => $file, 'label' => ($file['original_name'] ?? 'Receipt')])
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="helper-text" style="margin-top:10px;">No payment receipt history has been uploaded yet.</p>
                @endif
            </div>
        </div>
    </div>
</section>

<form method="POST" action="{{ route($storeRoute ?? 'admin.finance.store', $submission) }}" enctype="multipart/form-data" class="panel no-print" style="margin-top:20px;">
    @csrf
    <div class="panel-head"><h3>Save Completed Finance PDF</h3></div>
    <input type="hidden" name="reference_code" value="{{ $referenceCode }}">
    <div class="finance-upload-grid">
        <label><span>Upload Final Filled PDF</span><input type="file" name="filled_finance_pdf" accept="application/pdf" required></label>
        <div class="helper-text" style="align-self:end;"> </div>
    </div>
    <div class="action-row" style="margin-top:16px;"><button class="btn primary" type="submit">Submit</button></div>
</form>

@if($savedPdfUrl)
<section class="panel no-print" style="margin-top:20px;"><div class="panel-head"><h3>Saved Final PDF</h3></div><iframe src="{{ $savedPdfUrl }}" class="finance-pdf-preview-frame"></iframe></section>
@endif

<script src="https://unpkg.com/pdf-lib/dist/pdf-lib.min.js"></script>
<script>
(function(){const TEMPLATE_URL=@json($templateUrl);const documentSuffix=@json($documentSuffix);const referenceCode=@json($referenceCode);const iframe=document.getElementById('finance-pdf-preview');const status=document.getElementById('finance-preview-status');if(!iframe||!status){return;}let currentBlobUrl=null;function setStatus(text,isError=false){status.textContent=text;status.style.color=isError?'#b42318':'';}function setTextSafe(form,names,value){names.forEach((name)=>{try{const field=form.getTextField(name);field.setText(value||'');}catch(e){}});}async function preparePdf(){const response=await fetch(TEMPLATE_URL,{cache:'no-store'});if(!response.ok) throw new Error('Unable to load fillable PDF template.');const bytes=await response.arrayBuffer();const pdfDoc=await PDFLib.PDFDocument.load(bytes);const form=pdfDoc.getForm();setTextSafe(form,['monthyear_suffix','monthyear','month/year','Text1'],documentSuffix);setTextSafe(form,['reference_code','full_reference_code','document_code'],referenceCode);const pdfBytes=await pdfDoc.save();if(currentBlobUrl) URL.revokeObjectURL(currentBlobUrl);currentBlobUrl=URL.createObjectURL(new Blob([pdfBytes],{type:'application/pdf'}));iframe.src=currentBlobUrl+'#toolbar=1&navpanes=0&view=FitH';setStatus('Finance PDF is ready. Complete it in the viewer, then upload the final file below.');}preparePdf().catch((error)=>{console.error(error);setStatus(error.message||'Preview failed to load.',true);});})();
</script>
@endif

@if($savedPdfUrl)
<section class="panel" style="margin-top:20px;">
    <div class="panel-head no-print"><h3>Printable Finance Package</h3></div>
    <div class="print-finance-page" style="display:none;margin-top:14px;">
        <div class="print-finance-section">
            <div class="print-finance-card">
                <h3>Client &amp; Finance Details</h3>
                <div class="print-finance-grid" style="margin-bottom:8px;">
                    <div><p><strong>Job ID:</strong> {{ $submission->request_code }}</p></div>
                    <div><p><strong>Client:</strong> {{ $submission->full_name ?: ($submission->user?->name ?? '-') }}</p></div>
                    <div><p><strong>Location:</strong> {{ $submission->location?->name ?? '-' }}</p></div>
                    <div><p><strong>State:</strong> {{ $submission->location?->state ?? '-' }}</p></div>
                    <div><p><strong>Request Type:</strong> {{ $submission->requestType?->name ?? '-' }}</p></div>
                    <div><p><strong>Technician:</strong> {{ $submission->assignedTechnician?->name ?? '-' }}</p></div>
                    <div><p><strong>Reference Code:</strong> {{ data_get($finance, 'reference_code') ?: $referenceCode }}</p></div>
                    <div><p><strong>Approved Amount:</strong> RM {{ number_format((float) (data_get($finance, 'approved_amount') ?: data_get($approvedQuotation, 'amount') ?: 0), 2) }}</p></div>
                </div>

                <h3 style="margin-top:8px;">Approved Quotation &amp; Company Details</h3>
                <div class="print-finance-grid" style="margin-bottom:8px;">
                    <div><p><strong>Approved At:</strong> {{ data_get($approvedQuotation, 'admin_signed_at') ?: '-' }}</p></div>
                    <div><p><strong>Vendor:</strong> {{ data_get($approvedQuotation, 'vendor_snapshot.company_name') ?: '-' }}</p></div>
                    @foreach($vendorDetails as $detail)
                        <div><p><strong>{{ $detail['label'] }}:</strong> {{ $detail['value'] ?: '-' }}</p></div>
                    @endforeach
                </div>
                @php($quotationPrintFiles = collect([$quotationFile])->filter()->concat($quotationSupportingFiles)->values())
                @if($quotationPrintFiles->isNotEmpty())
                    <div class="print-finance-images {{ $quotationPrintFiles->count() === 1 ? 'single' : '' }}" style="margin-bottom:8px;">
                        @foreach($quotationPrintFiles as $file)
                            @include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Approved quotation evidence'])
                        @endforeach
                    </div>
                @endif

                <h3 style="margin-top:8px;">Finance Evidence</h3>
                @if($receiptHistory->isNotEmpty())
                    @foreach($receiptHistory as $history)
                        <div style="margin-bottom:8px;">
                            <p><strong>{{ ucfirst(str_replace('_', ' ', $history['payment_type'] ?? '-')) }}</strong> — {{ $history['uploaded_label'] ?? ($history['uploaded_at'] ?? '-') }}</p>
                            @php($historyFiles = collect($history['files'] ?? [])->filter()->values())
                            @if($historyFiles->isNotEmpty())
                                <div class="print-finance-images {{ $historyFiles->count() === 1 ? 'single' : '' }}" style="margin-top:6px;">
                                    @foreach($historyFiles as $file)
                                        @include('components.print-media-card', ['file' => $file, 'label' => $file['original_name'] ?? 'Receipt Upload'])
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                @else
                    <p>No payment receipt history has been uploaded yet.</p>
                @endif
            </div>
        </div>

        <div class="print-finance-section print-page-break-before">
            <div class="print-finance-card">
                <h3>Finance Form PDF</h3>
                <div class="print-finance-images single">
                    @include('components.print-media-card', ['file' => ['path' => $filledPdfPath, 'mime_type' => 'application/pdf', 'original_name' => 'Finance Form PDF'], 'label' => 'Finance Form PDF'])
                </div>
            </div>
        </div>
    </div>
</section>
@endif

@include('components.print-pdf-script')
@endsection
