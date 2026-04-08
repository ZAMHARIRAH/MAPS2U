<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function show(Request $request, string $encodedPath): StreamedResponse
    {
        abort_unless(auth()->check(), 403);

        $path = base64_decode(strtr($encodedPath, '-_', '+/'), true);
        abort_unless(is_string($path) && $path !== '', 404);
        abort_unless(!str_contains($path, '..'), 404);

        $resolvedPath = $this->resolvePublicPath($path);
        abort_unless($resolvedPath !== null, 404);

        $mime = Storage::disk('public')->mimeType($resolvedPath) ?: 'application/octet-stream';
        $filename = basename($resolvedPath);

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response()->stream(function () use ($resolvedPath) {
            $stream = Storage::disk('public')->readStream($resolvedPath);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    private function resolvePublicPath(string $path): ?string
    {
        $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');
        $candidates = array_values(array_unique(array_filter([
            $normalized,
            preg_replace('#^storage/#', '', $normalized),
            preg_replace('#^public/#', '', $normalized),
            'uploads/' . basename($normalized),
        ])));

        foreach ($candidates as $candidate) {
            if ($candidate && Storage::disk('public')->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
