<?php
declare(strict_types=1);

use setasign\Fpdi\Fpdi;

if (!function_exists('resident_pdf_merge_resolve_image_ext')) {
    function resident_pdf_merge_resolve_image_ext(string $imagePath, string $declaredExt = ''): string
    {
        $declaredExt = strtolower(ltrim(trim($declaredExt), '.'));
        $imageType = @exif_imagetype($imagePath);

        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return 'jpeg';
            case IMAGETYPE_PNG:
                return 'png';
        }

        if (defined('IMAGETYPE_WEBP') && $imageType === IMAGETYPE_WEBP) {
            return 'webp';
        }

        if ($declaredExt === 'jpg') {
            return 'jpeg';
        }

        if (in_array($declaredExt, ['jpeg', 'png', 'webp'], true)) {
            return $declaredExt;
        }

        return '';
    }
}

if (!function_exists('resident_pdf_merge_prepare_image')) {
    function resident_pdf_merge_prepare_image(string $imagePath, string $declaredExt = ''): array
    {
        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false || !isset($imageInfo[0], $imageInfo[1])) {
            throw new Exception('Invalid image file for PDF merge.');
        }

        $resolvedExt = resident_pdf_merge_resolve_image_ext($imagePath, $declaredExt);
        if ($resolvedExt === '') {
            throw new Exception('Unsupported image format for PDF conversion. Please upload JPG, JPEG, PNG, or PDF.');
        }

        $preparedPath = $imagePath;
        $cleanupPath = '';
        $pdfType = $resolvedExt;

        if ($resolvedExt === 'webp') {
            if (!function_exists('imagecreatefromwebp') || !function_exists('imagepng')) {
                throw new Exception('WEBP images cannot be converted to PDF on this server. Please upload JPG, JPEG, PNG, or PDF.');
            }

            $image = @imagecreatefromwebp($imagePath);
            if (!$image) {
                throw new Exception('WEBP image could not be read for PDF conversion. Please upload JPG, JPEG, PNG, or PDF.');
            }

            $tempBase = tempnam(sys_get_temp_dir(), 'pdfmerge_');
            if ($tempBase === false) {
                imagedestroy($image);
                throw new Exception('Unable to prepare the uploaded image for PDF conversion right now.');
            }

            $pngPath = $tempBase . '.png';
            @unlink($tempBase);

            imagealphablending($image, false);
            imagesavealpha($image, true);
            $saved = @imagepng($image, $pngPath);
            imagedestroy($image);

            if (!$saved || !is_file($pngPath)) {
                @unlink($pngPath);
                throw new Exception('Failed to convert WEBP image for PDF conversion. Please upload JPG, JPEG, PNG, or PDF.');
            }

            $preparedPath = $pngPath;
            $cleanupPath = $pngPath;
            $pdfType = 'png';
        }

        return [
            'path' => $preparedPath,
            'type' => $pdfType,
            'width' => (float)$imageInfo[0],
            'height' => (float)$imageInfo[1],
            'cleanup_path' => $cleanupPath,
        ];
    }
}

if (!function_exists('resident_pdf_merge_append_image_page')) {
    function resident_pdf_merge_append_image_page(Fpdi $pdf, string $imagePath, string $declaredExt = ''): void
    {
        $prepared = resident_pdf_merge_prepare_image($imagePath, $declaredExt);

        try {
            $imgW = (float)$prepared['width'];
            $imgH = (float)$prepared['height'];
            $orientation = $imgW > $imgH ? 'L' : 'P';
            $pdf->AddPage($orientation, 'A4');

            $margin = 10.0;
            $pageW = (float)$pdf->GetPageWidth();
            $pageH = (float)$pdf->GetPageHeight();
            $maxW = $pageW - ($margin * 2);
            $maxH = $pageH - ($margin * 2);

            $scale = min($maxW / $imgW, $maxH / $imgH);
            $drawW = $imgW * $scale;
            $drawH = $imgH * $scale;
            $x = ($pageW - $drawW) / 2;
            $y = ($pageH - $drawH) / 2;

            $pdf->Image((string)$prepared['path'], $x, $y, $drawW, $drawH, (string)$prepared['type']);
        } catch (Throwable $e) {
            $message = trim((string)$e->getMessage());
            if ($message === '') {
                $message = 'Failed to convert uploaded image to PDF.';
            }
            throw new Exception($message);
        } finally {
            $cleanupPath = (string)($prepared['cleanup_path'] ?? '');
            if ($cleanupPath !== '' && is_file($cleanupPath)) {
                @unlink($cleanupPath);
            }
        }
    }
}

if (!function_exists('resident_pdf_merge_append_pdf_pages')) {
    function resident_pdf_merge_append_pdf_pages(Fpdi $pdf, string $pdfPath): void
    {
        $pageCount = $pdf->setSourceFile($pdfPath);
        for ($i = 1; $i <= $pageCount; $i++) {
            $tpl = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tpl);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);
        }
    }
}
