<?php

namespace App\Services;

use RuntimeException;

class QuotationSignatureService
{
    public function embed(string $storedRelativePath, string $signatureDataUrl): array
    {
        $inputPath = storage_path('app/public/' . ltrim($storedRelativePath, '/'));
        if (!is_file($inputPath)) {
            throw new RuntimeException('Approved quotation source file was not found.');
        }

        $signatureBinary = $this->decodeSignatureDataUrl($signatureDataUrl);
        $extension = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
        $outputRelativePath = 'approved-quotation-signed/' . date('Y/m') . '/' . uniqid('quotation_', true) . '.' . $extension;
        $outputPath = storage_path('app/public/' . $outputRelativePath);

        if (!is_dir(dirname($outputPath)) && !mkdir(dirname($outputPath), 0775, true) && !is_dir(dirname($outputPath))) {
            throw new RuntimeException('Approved quotation destination folder could not be created.');
        }

        match ($extension) {
            'jpg', 'jpeg', 'png', 'webp' => $this->embedIntoImage($inputPath, $signatureBinary, $outputPath, $extension),
            'pdf' => $this->embedIntoPdf($inputPath, $signatureBinary, $outputPath),
            default => throw new RuntimeException('Approved quotation file type is not supported for signature embedding.'),
        };

        if (!is_file($outputPath)) {
            throw new RuntimeException('Failed to generate the signed quotation file.');
        }

        $mimeType = match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => mime_content_type($outputPath) ?: 'application/octet-stream',
        };

        return [
            'original_name' => pathinfo($storedRelativePath, PATHINFO_BASENAME),
            'path' => $outputRelativePath,
            'mime_type' => $mimeType,
            'size' => filesize($outputPath) ?: null,
            'signature_embedded' => true,
        ];
    }

    private function decodeSignatureDataUrl(string $signatureDataUrl): string
    {
        if (!preg_match('/^data:image\/(png|jpe?g);base64,(.+)$/i', $signatureDataUrl, $matches)) {
            throw new RuntimeException('Approval signature format is invalid.');
        }

        $signatureBinary = base64_decode($matches[2], true);
        if ($signatureBinary === false) {
            throw new RuntimeException('Approval signature could not be decoded.');
        }

        return $signatureBinary;
    }

    private function embedIntoImage(string $inputPath, string $signatureBinary, string $outputPath, string $extension): void
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new RuntimeException('GD image support is required to sign quotation images.');
        }

        $baseImage = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($inputPath),
            'png' => @imagecreatefrompng($inputPath),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($inputPath) : false,
        };

        if (!$baseImage) {
            $baseImage = @imagecreatefromstring((string) file_get_contents($inputPath));
        }

        $signatureImage = @imagecreatefromstring($signatureBinary);

        if (!$baseImage || !$signatureImage) {
            throw new RuntimeException('Quotation image or signature image could not be opened.');
        }

        imagealphablending($baseImage, true);
        imagesavealpha($baseImage, true);
        imagealphablending($signatureImage, true);
        imagesavealpha($signatureImage, true);

        [$signatureWidth, $signatureHeight] = $this->calculateScaledSize(
            imagesx($signatureImage),
            imagesy($signatureImage),
            imagesx($baseImage) * 0.22,
            imagesy($baseImage) * 0.18,
            420,
            160
        );

        $marginX = max(24, (int) round(imagesx($baseImage) * 0.03));
        $marginY = max(24, (int) round(imagesy($baseImage) * 0.03));
        $targetX = imagesx($baseImage) - $signatureWidth - $marginX;
        $targetY = imagesy($baseImage) - $signatureHeight - $marginY;

        imagecopyresampled(
            $baseImage,
            $signatureImage,
            $targetX,
            $targetY,
            0,
            0,
            $signatureWidth,
            $signatureHeight,
            imagesx($signatureImage),
            imagesy($signatureImage)
        );

        $saved = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($baseImage, $outputPath, 92),
            'png' => imagepng($baseImage, $outputPath, 6),
            'webp' => function_exists('imagewebp') ? imagewebp($baseImage, $outputPath, 92) : false,
        };

        imagedestroy($baseImage);
        imagedestroy($signatureImage);

        if (!$saved) {
            throw new RuntimeException('Signed quotation image could not be saved.');
        }
    }

    private function embedIntoPdf(string $inputPath, string $signatureBinary, string $outputPath): void
    {
        if (!class_exists('setasign\\Fpdi\\Fpdi')) {
            throw new RuntimeException('PDF signing requires the setasign/fpdf and setasign/fpdi Composer packages.');
        }

        $tempSignaturePath = storage_path('app/tmp-signature-' . uniqid('', true) . '.png');
        if (file_put_contents($tempSignaturePath, $signatureBinary) === false) {
            throw new RuntimeException('Approval signature temporary file could not be created.');
        }

        try {
            $pdf = new \setasign\Fpdi\Fpdi();
            $pageCount = $pdf->setSourceFile($inputPath);

            [$signatureWidth, $signatureHeight] = $this->signatureSizeForPdf($tempSignaturePath);

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $size = $pdf->getTemplateSize($templateId);
                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';

                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                if ($pageNumber === $pageCount) {
                    $marginX = max(10, $size['width'] * 0.04);
                    $marginY = max(10, $size['height'] * 0.04);
                    $x = $size['width'] - $signatureWidth - $marginX;
                    $y = $size['height'] - $signatureHeight - $marginY;
                    $pdf->Image($tempSignaturePath, $x, $y, $signatureWidth, $signatureHeight, 'PNG');
                }
            }

            $pdf->Output('F', $outputPath);
        } catch (\Throwable $e) {
            throw new RuntimeException('Signed quotation PDF could not be created: ' . $e->getMessage(), 0, $e);
        } finally {
            @unlink($tempSignaturePath);
        }
    }

    private function calculateScaledSize(int $originalWidth, int $originalHeight, float $maxWidth, float $maxHeight, int $fallbackWidth, int $fallbackHeight): array
    {
        if ($originalWidth <= 0 || $originalHeight <= 0) {
            return [$fallbackWidth, $fallbackHeight];
        }

        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight, 1);
        $targetWidth = max(120, (int) round($originalWidth * $ratio));
        $targetHeight = max(50, (int) round($originalHeight * $ratio));

        return [$targetWidth, $targetHeight];
    }

    private function signatureSizeForPdf(string $signaturePath): array
    {
        $dimensions = @getimagesize($signaturePath);
        if (!$dimensions) {
            return [45, 18];
        }

        [$width, $height] = $dimensions;
        if ($width <= 0 || $height <= 0) {
            return [45, 18];
        }

        $targetWidth = 45.0;
        $targetHeight = $targetWidth * ($height / $width);
        if ($targetHeight > 22) {
            $targetHeight = 22.0;
            $targetWidth = $targetHeight * ($width / $height);
        }

        return [round($targetWidth, 2), round($targetHeight, 2)];
    }
}
