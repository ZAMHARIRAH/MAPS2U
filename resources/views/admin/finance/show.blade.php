@extends('layouts.app', ['title' => 'Finance Form'])
@section('content')
@php
    $finance = $submission->finance_form ?? [];
    $documentSuffix = strtoupper(now()->format('M')) . now()->format('Y');
    $referenceCode = 'LCISB/FIN/PRF/V4/' . $documentSuffix;
    $templateUrl = asset('finance-template-fillable.pdf');
    $filledPdfPath = data_get($finance, 'filled_pdf_path');
    $savedPdfUrl = $filledPdfPath ? route('files.show', ['encodedPath' => rtrim(strtr(base64_encode($filledPdfPath), '+/', '-_'), '=')]) : null;
    $invoiceFiles = $submission->invoice_files ?? [];
@endphp

<div class="page-header no-print">
    <div>
        <h1>Finance Form</h1>
        <p>Review the finance PDF together with the invoice evidence below. When the final finance PDF is uploaded and submitted, this job will be marked as completed automatically.</p>
    </div>
    <div class="actions-inline">
        <a class="btn ghost" href="{{ route('admin.finance.index') }}">Back</a>
        @if($savedPdfUrl)
            <a class="btn ghost" href="{{ $savedPdfUrl }}" target="_blank" rel="noopener">Open Saved PDF</a>
            <button class="btn accent" type="button" onclick="window.open('{{ $savedPdfUrl }}', '_blank')">Print Saved PDF</button>
        @endif
    </div>
</div>

<section class="panel no-print finance-pdf-panel-direct">
    <div class="panel-head"><h3>Finance PDF Container</h3></div>
    <div class="finance-code-banner finance-code-banner-inline">
        <span class="finance-code-prefix">Reference Code</span>
        <span class="finance-code-value">{{ $referenceCode }}</span>
    </div>
    <p class="helper-text" style="margin-top:10px;">The live PDF below is prepared with the current month-year code. Use the PDF viewer controls directly for download or print if needed.</p>

    <div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(320px,1fr);gap:18px;align-items:start;margin-top:14px;">
        <div>
            <div class="finance-pdf-shell">
                <iframe id="finance-pdf-preview" class="finance-pdf-preview-frame finance-pdf-preview-frame-large" title="Finance PDF preview"></iframe>
            </div>
            <span id="finance-preview-status" class="helper-text" style="display:block;margin-top:10px;">Preparing finance PDF...</span>
        </div>

        <div class="panel" style="margin:0;background:#fbfcfe;">
            <div class="panel-head" style="margin-bottom:10px;">
                <h3 style="margin:0;">Invoice Evidence</h3>
            </div>

            @if(count($invoiceFiles))
                <div style="display:grid;gap:14px;">
                    @foreach($invoiceFiles as $index => $file)
                        @php
                            $mime = strtolower($file['mime_type'] ?? '');
                            $path = $file['path'] ?? null;
                            $encodedPath = $path ? rtrim(strtr(base64_encode($path), '+/', '-_'), '=') : null;
                            $url = $encodedPath ? route('files.show', ['encodedPath' => $encodedPath]) : null;
                            $label = $file['original_name'] ?? ('Invoice ' . ($index + 1));
                        @endphp
                        <div class="file-preview-card" style="margin:0;">
                            <div class="file-preview-head" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                                <strong>{{ $label }}</strong>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                                    @if($url)
                                        <a class="btn ghost small" href="{{ $url }}" target="_blank" rel="noopener">Open</a>
                                        <button class="btn accent small" type="button" onclick="printInvoice(@js($url), @js(str_contains($mime, 'image')))">Print Invoice</button>
                                    @endif
                                </div>
                            </div>

                            @if($url && str_contains($mime, 'image'))
                                <img src="{{ $url }}" alt="{{ $label }}" class="inline-preview-image">
                            @elseif($url && str_contains($mime, 'pdf'))
                                <iframe src="{{ $url }}#toolbar=1&navpanes=0&view=FitH" class="inline-preview-frame" title="{{ $label }}"></iframe>
                            @else
                                <p class="helper-text">Preview is not available inline for this file type.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="helper-text">No invoice uploaded yet.</p>
            @endif
        </div>
    </div>
</section>

<form method="POST" action="{{ route('admin.finance.store', $submission) }}" enctype="multipart/form-data" class="panel no-print" style="margin-top:20px;">
    @csrf
    <div class="panel-head"><h3>Save Completed Finance PDF</h3></div>
    <input type="hidden" name="reference_code" value="{{ $referenceCode }}">
    <div class="finance-upload-grid">
        <label>
            <span>Upload Final Filled PDF</span>
            <input type="file" name="filled_finance_pdf" accept="application/pdf" required>
        </label>
        <div class="helper-text" style="align-self:end;">Once you submit this uploaded final PDF, the job status will be marked as completed automatically.</div>
    </div>
    <div class="action-row" style="margin-top:16px;">
        <button class="btn primary" type="submit">Submit</button>
    </div>
</form>

@if($savedPdfUrl)
<section class="panel no-print" style="margin-top:20px;">
    <div class="panel-head"><h3>Saved Final PDF</h3></div>
    <iframe src="{{ $savedPdfUrl }}" class="finance-pdf-preview-frame"></iframe>
</section>
@endif

<script src="https://unpkg.com/pdf-lib/dist/pdf-lib.min.js"></script>
<script>
(function(){
  const TEMPLATE_URL = @json($templateUrl);
  const documentSuffix = @json($documentSuffix);
  const referenceCode = @json($referenceCode);
  const iframe = document.getElementById('finance-pdf-preview');
  const status = document.getElementById('finance-preview-status');
  let currentBlobUrl = null;

  function setStatus(text, isError = false) {
    status.textContent = text;
    status.style.color = isError ? '#b42318' : '';
  }

  function setTextSafe(form, names, value) {
    names.forEach((name) => {
      try {
        const field = form.getTextField(name);
        field.setText(value || '');
      } catch (e) {}
    });
  }

  async function preparePdf() {
    const response = await fetch(TEMPLATE_URL, { cache: 'no-store' });
    if (!response.ok) throw new Error('Unable to load fillable PDF template.');
    const bytes = await response.arrayBuffer();
    const pdfDoc = await PDFLib.PDFDocument.load(bytes);
    const form = pdfDoc.getForm();
    setTextSafe(form, ['monthyear_suffix', 'monthyear', 'month/year', 'Text1'], documentSuffix);
    setTextSafe(form, ['reference_code', 'full_reference_code', 'document_code'], referenceCode);
    const pdfBytes = await pdfDoc.save();
    if (currentBlobUrl) URL.revokeObjectURL(currentBlobUrl);
    currentBlobUrl = URL.createObjectURL(new Blob([pdfBytes], { type: 'application/pdf' }));
    iframe.src = currentBlobUrl + '#toolbar=1&navpanes=0&view=FitH';
    setStatus('Finance PDF is ready. Complete it in the viewer, then upload the final file below.');
  }

  preparePdf().catch((error) => {
    console.error(error);
    setStatus(error.message || 'Preview failed to load.', true);
  });

  window.printInvoice = function(url, isImage) {
    const popup = window.open('', '_blank');
    if (!popup) {
      alert('Please allow pop-ups to print the invoice.');
      return;
    }
    const safeUrl = String(url || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    if (isImage) {
      popup.document.open();
      popup.document.write(`<!doctype html><html><head><title>Print Invoice</title><style>html,body{margin:0;padding:0;background:#fff}img{width:100%;height:auto;display:block}</style></head><body><img src="${safeUrl}" onload="setTimeout(() => window.print(), 250)"></body></html>`);
      popup.document.close();
      return;
    }
    popup.document.open();
    popup.document.write(`<!doctype html><html><head><title>Print Invoice</title><style>html,body{margin:0;height:100%}iframe{border:0;width:100%;height:100%}</style></head><body><iframe src="${safeUrl}#toolbar=0&navpanes=0"></iframe><script>const frame=document.querySelector('iframe');frame.addEventListener('load',()=>setTimeout(()=>window.print(),500));<\/script></body></html>`);
    popup.document.close();
  };
})();
</script>
@endsection
