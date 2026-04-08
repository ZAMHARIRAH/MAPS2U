@extends('layouts.app', ['title' => 'Finance Form'])
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
        $history['files'] = collect($history['files'] ?? [])->values()->all();
        return $history;
    })->values();
    $isViewer = $isViewer ?? false;
@endphp
<div class="page-header no-print">
    <div>
        <h1>Finance Form</h1>
        <p> </p>
    </div>
    <div class="actions-inline">
        <a class="btn ghost" href="{{ route('admin.finance.index') }}">Back</a>
        @if($savedPdfUrl)
            <a class="btn ghost" href="{{ $savedPdfUrl }}" target="_blank" rel="noopener">Open Saved PDF</a>
        @endif
        <button class="btn accent" type="button" onclick="window.print()">Print Finance Form</button>
    </div>
</div>

@if(!$isViewer)
<section class="panel no-print finance-pdf-panel-direct">
    <div class="panel-head"><h3>Finance PDF Container</h3></div>
    <div class="finance-code-banner finance-code-banner-inline">
        <span class="finance-code-prefix">Reference Code</span>
        <span class="finance-code-value">{{ $referenceCode }}</span>
    </div>
    <p > </p>
    <div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(320px,1fr);gap:18px;align-items:start;margin-top:14px;">
        <div>
            <div class="finance-pdf-shell"><iframe id="finance-pdf-preview" class="finance-pdf-preview-frame finance-pdf-preview-frame-large" title="Finance PDF preview"></iframe></div>
            <span id="finance-preview-status" class="helper-text" style="display:block;margin-top:10px;">Preparing finance PDF...</span>
        </div>
        <div class="panel" style="margin:0;background:#fbfcfe;display:grid;gap:14px;">
            <div class="panel-head" style="margin-bottom:0;"><h3 style="margin:0;">Finance Evidence</h3></div>
            <div class="finance-preview-card">
                <strong>Approved Quotation</strong>
                <small class="helper-text"> </small>
                @if($quotationFile)
                    <div style="margin-top:12px;">@include('components.file-preview', ['file' => $quotationFile, 'label' => data_get($quotationFile, 'original_name', 'Approved quotation')])</div>
                @else
                    <p class="helper-text" style="margin-top:10px;">No approved quotation file is available yet.</p>
                @endif
            </div>
            <div class="finance-preview-card">
                <strong>Payment Receipt History</strong>
                <small class="helper-text"> </small>
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

<form method="POST" action="{{ route('admin.finance.store', $submission) }}" enctype="multipart/form-data" class="panel no-print" style="margin-top:20px;">
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
@else
<style>
@media print{
    .no-print{display:none !important;}
    .print-finance-page{display:block !important;margin:0 !important;padding:0 !important;}
    .print-finance-card{break-inside:avoid;page-break-inside:avoid;border:1px solid #cbd5e1;border-radius:12px;padding:14px;margin-bottom:14px;background:#fff;}
    .print-page-break-before{break-before:page;page-break-before:always;}
    .print-finance-images{display:grid;grid-template-columns:1fr !important;gap:14px;}
    .print-finance-images .image-card img,.print-finance-images .image-card iframe,.print-finance-images .pdf-page-card canvas{max-height:none !important;}
    .print-finance-images .image-card{padding:10px;}
}
</style>
<section class="panel" style="margin-top:20px;">
    <div class="panel-head"><h3>Uploaded Finance Form</h3></div>
    @if($savedPdfUrl)
        <iframe src="{{ $savedPdfUrl }}" class="finance-pdf-preview-frame no-print"></iframe>
        <div class="print-finance-page" style="display:none;margin-top:14px;">
            <div class="print-finance-card">
                <h3 style="margin:0 0 10px;">Finance Form</h3>
                <div class="print-finance-images">
                    @include('components.print-media-card', ['file' => ['path' => $filledPdfPath, 'mime_type' => 'application/pdf', 'original_name' => 'Finance Form PDF'], 'label' => 'Finance Form PDF'])
                </div>
            </div>
            <div class="print-finance-card print-page-break-before">
                <h3 style="margin:0 0 10px;">Payment Receipt History Log</h3>
                @if($receiptHistory->isNotEmpty())
                    <div style="display:grid;gap:14px;">
                        @foreach($receiptHistory as $history)
                            <div class="summary-card compact-summary span-2" style="margin:0;border:1px solid #dbe4f0;border-radius:12px;padding:14px;background:#fff;">
                                <strong>{{ ucfirst(str_replace('_', ' ', $history['payment_type'] ?? '-')) }}</strong>
                                <span>{{ $history['uploaded_label'] ?? ($history['uploaded_at'] ?? '-') }}</span>
                                @if(!empty($history['files']))
                                    <div class="print-finance-images" style="margin-top:12px;">
                                        @foreach($history['files'] as $file)
                                            @include('components.print-media-card', ['file' => $file, 'label' => ($file['original_name'] ?? 'Receipt Upload')])
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="helper-text">No payment receipt history has been uploaded yet.</p>
                @endif
            </div>
        </div>
    @else
        <p class="helper-text">Not uploaded yet.</p>
    @endif
</section>

<section class="panel no-print" style="margin-top:20px;">
    <div class="panel-head"><h3>Payment Receipt History Log</h3></div>
    @if($receiptHistory->isNotEmpty())
        <div style="display:grid;gap:12px;">
            @foreach($receiptHistory as $history)
                <div class="summary-card compact-summary span-2">
                    <strong>{{ ucfirst(str_replace('_', ' ', $history['payment_type'] ?? '-')) }}</strong>
                    <span>{{ $history['uploaded_label'] ?? ($history['uploaded_at'] ?? '-') }}</span>
                    @if(!empty($history['files']))
                        <div class="preview-grid single-column" style="margin-top:12px;">
                            @foreach($history['files'] as $file)
                                @include('components.file-preview', ['file' => $file, 'label' => ($history['uploaded_label'] ?? 'Receipt Upload') . ' - ' . ($file['original_name'] ?? 'Receipt')])
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <p class="helper-text">No payment receipt history has been uploaded yet.</p>
    @endif
</section>
@include('components.print-pdf-script')
@endif
@endsection
