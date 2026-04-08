@php
    $file = $file ?? [];
    $mime = strtolower($file['mime_type'] ?? '');
    $path = $file['path'] ?? null;
    $encodedPath = $path ? rtrim(strtr(base64_encode($path), '+/', '-_'), '=') : null;
    $url = $encodedPath ? route('files.show', ['encodedPath' => $encodedPath]) : null;
    $downloadUrl = $encodedPath ? route('files.show', ['encodedPath' => $encodedPath, 'download' => 1]) : null;
@endphp

<div class="file-preview-card">
    <div class="file-preview-head">
        <strong>{{ $label ?? ($file['original_name'] ?? 'Attachment') }}</strong>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @if($url)
                <a href="{{ $url }}" target="_blank">Open file</a>
            @endif
            @if($downloadUrl)
                <a href="{{ $downloadUrl }}" target="_blank">Download</a>
            @endif
        </div>
    </div>

    @if($url && str_contains($mime, 'image'))
        <img src="{{ $url }}" alt="{{ $file['original_name'] ?? 'Preview' }}" class="inline-preview-image">
    @elseif($url && str_contains($mime, 'pdf'))
        <iframe src="{{ $url }}" class="inline-preview-frame" title="{{ $file['original_name'] ?? 'PDF Preview' }}"></iframe>
    @else
        <p class="helper-text">Preview is not available inline for this file type.</p>
    @endif
</div>
