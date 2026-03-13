<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class ImageService
{
    /**
     * Process an image to a 9:16 aspect ratio (1080x1920).
     * 
     * @param string $sourcePath Path to the source image file
     * @param string $destPath Path to save the processed image
     * @param int $targetWidth Target width in pixels
     * @return bool
     */
    public function processTo9by16(string $sourcePath, string $destPath, int $targetWidth = 1080): bool
    {
        $targetHeight = (int)($targetWidth * 16 / 9);
        
        $info = getimagesize($sourcePath);
        if (!$info) {
            throw new RuntimeException('Formato de imagem inválido ou arquivo corrompido.');
        }

        [$width, $height, $type] = $info;

        switch ($type) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $src = imagecreatefromwebp($sourcePath);
                } else {
                    throw new RuntimeException('Extensão WebP não disponível no servidor.');
                }
                break;
            default:
                throw new RuntimeException('Apenas formatos JPG, PNG e WebP são suportados.');
        }

        if (!$src) {
            error_log("[hotspot] ImageService: failed to create image resource for type $type");
            throw new RuntimeException('Falha ao processar a imagem de origem.');
        }

        error_log("[hotspot] ImageService: processing image {$width}x{$height}, type $type");

        // Calculate crop dimensions for 9:16 ratio
        $currentRatio = $width / $height;
        $targetRatio = 9 / 16;

        if ($currentRatio > $targetRatio) {
            // Wider than 9:16 - crop sides
            $cropHeight = $height;
            $cropWidth = (int)($height * $targetRatio);
            $x = (int)(($width - $cropWidth) / 2);
            $y = 0;
        } else {
            // Taller than 9:16 - crop top/bottom
            $cropWidth = $width;
            $cropHeight = (int)($width / $targetRatio);
            $x = 0;
            $y = (int)(($height - $cropHeight) / 2);
        }

        error_log("[hotspot] ImageService: cropping to {$cropWidth}x{$cropHeight} at ($x,$y)");

        // Create canvas for resized/cropped image
        $dst = imagecreatetruecolor($targetWidth, $targetHeight);

        // Handle transparency for PNG/WebP
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        // Crop and Resize
        imagecopyresampled(
            $dst, $src,
            0, 0, $x, $y,
            $targetWidth, $targetHeight,
            $cropWidth, $cropHeight
        );

        // Save as PNG for quality maintenance and compatibility
        $result = imagepng($dst, $destPath, 8); // Compression level 8 (0-9)
        
        if (!$result) {
            error_log("[hotspot] ImageService: failed to save processed image to $destPath");
            throw new RuntimeException('Não foi possível salvar o arquivo processado no servidor. Verifique as permissões de escrita.');
        } else {
            error_log("[hotspot] ImageService: successfully saved to $destPath");
        }

        // Free memory
        imagedestroy($src);
        imagedestroy($dst);

        return $result;
    }
}
