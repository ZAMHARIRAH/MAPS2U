@php
    $file = $file ?? [];
    $mime = strtolower($file['mime_type'] ?? '');
    $path = $file['path'] ?? null;
    $url = $path ? route('files.show', ['encodedPath' => rtrim(strtr(base64_encode($path), '+/', '-_'), '=')]) : null;
@endphp
<div class="image-card">
    @if($url && str_contains($mime, 'pdf'))
        <div class="pdf-print-stack" data-print-pdf data-pdf-url="{{ $url }}" data-pdf-name="{{ e($file['original_name'] ?? 'PDF document') }}">
            <div class="pdf-print-loading">Preparing PDF preview for print...</div>
        </div>
    @elseif($url && str_contains($mime, 'image'))
        <img src="{{ $url }}" alt="{{ $file['original_name'] ?? ($label ?? 'Attachment') }}">
    @elseif($url)
        <div class="pdf-print-loading">Preview is not available for this file type.</div>
    @endif
    <div class="label">
        {{ $label ?? ($file['original_name'] ?? 'Attachment') }}
    </div>
</div>
