<?php

namespace App\Services;

use Psr\Http\Message\UploadedFileInterface;

class LocalStorageService
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = dirname(__DIR__, 2) . '/storage/uploads/shops';
    }

    public function storeShopBranding(
        UploadedFileInterface $file,
        int $shopId,
        string $label,
        int $targetWidth,
        int $targetHeight
    ): string {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            throw new \RuntimeException('GD extension is required for image processing.');
        }

        $directory = $this->basePath . '/' . $shopId . '/branding';
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $safeLabel = preg_replace('/[^a-z0-9_-]+/', '_', strtolower($label));
        $storedName = $safeLabel . '_' . uniqid() . '.webp';
        $targetPath = $directory . '/' . $storedName;

        $stream = $file->getStream();
        $contents = $stream->getContents();
        $image = @\imagecreatefromstring($contents);
        if (!$image) {
            throw new \RuntimeException('Invalid image file.');
        }

        $resized = $this->resizeAndCrop($image, $targetWidth, $targetHeight);
        $saved = \imagewebp($resized, $targetPath, 85);
        if (!$saved || !is_file($targetPath)) {
            \imagedestroy($image);
            \imagedestroy($resized);
            throw new \RuntimeException('Failed to save WebP image. Check GD WebP support.');
        }

        \imagedestroy($image);
        \imagedestroy($resized);

        return 'shops/' . $shopId . '/branding/' . $storedName;
    }

    public function storeShopImageFit(
        UploadedFileInterface $file,
        int $shopId,
        string $label,
        int $targetWidth,
        int $targetHeight
    ): string {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            throw new \RuntimeException('GD extension is required for image processing.');
        }

        $directory = $this->basePath . '/' . $shopId . '/branding';
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $safeLabel = preg_replace('/[^a-z0-9_-]+/', '_', strtolower($label));
        $storedName = $safeLabel . '_' . uniqid() . '.webp';
        $targetPath = $directory . '/' . $storedName;

        $stream = $file->getStream();
        $contents = $stream->getContents();
        $image = @\imagecreatefromstring($contents);
        if (!$image) {
            throw new \RuntimeException('Invalid image file.');
        }

        $resized = $this->resizeToFit($image, $targetWidth, $targetHeight);
        $saved = \imagewebp($resized, $targetPath, 85);
        if (!$saved || !is_file($targetPath)) {
            \imagedestroy($image);
            \imagedestroy($resized);
            throw new \RuntimeException('Failed to save WebP image. Check GD WebP support.');
        }

        \imagedestroy($image);
        \imagedestroy($resized);

        return 'shops/' . $shopId . '/branding/' . $storedName;
    }

    public function storeShopPageImage(
        UploadedFileInterface $file,
        int $shopId,
        string $label,
        int $targetWidth,
        int $targetHeight
    ): string {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            throw new \RuntimeException('GD extension is required for image processing.');
        }

        $directory = $this->basePath . '/' . $shopId . '/pages';
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $safeLabel = preg_replace('/[^a-z0-9_-]+/', '_', strtolower($label));
        $storedName = $safeLabel . '_' . uniqid() . '.webp';
        $targetPath = $directory . '/' . $storedName;

        $stream = $file->getStream();
        $contents = $stream->getContents();
        $image = @\imagecreatefromstring($contents);
        if (!$image) {
            throw new \RuntimeException('Invalid image file.');
        }

        $resized = $this->resizeToFit($image, $targetWidth, $targetHeight);
        $saved = \imagewebp($resized, $targetPath, 85);
        if (!$saved || !is_file($targetPath)) {
            \imagedestroy($image);
            \imagedestroy($resized);
            throw new \RuntimeException('Failed to save WebP image. Check GD WebP support.');
        }

        \imagedestroy($image);
        \imagedestroy($resized);

        return 'shops/' . $shopId . '/pages/' . $storedName;
    }

    private function resizeAndCrop($image, int $targetWidth, int $targetHeight)
    {
        $sourceWidth = \imagesx($image);
        $sourceHeight = \imagesy($image);
        $scale = max($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $resizeWidth = (int)ceil($sourceWidth * $scale);
        $resizeHeight = (int)ceil($sourceHeight * $scale);

        $resized = \imagecreatetruecolor($resizeWidth, $resizeHeight);
        \imagealphablending($resized, false);
        \imagesavealpha($resized, true);
        $transparent = \imagecolorallocatealpha($resized, 0, 0, 0, 127);
        \imagefilledrectangle($resized, 0, 0, $resizeWidth, $resizeHeight, $transparent);

        \imagecopyresampled(
            $resized,
            $image,
            0,
            0,
            0,
            0,
            $resizeWidth,
            $resizeHeight,
            $sourceWidth,
            $sourceHeight
        );

        $cropX = (int)max(0, floor(($resizeWidth - $targetWidth) / 2));
        $cropY = (int)max(0, floor(($resizeHeight - $targetHeight) / 2));
        $cropped = \imagecreatetruecolor($targetWidth, $targetHeight);
        \imagealphablending($cropped, false);
        \imagesavealpha($cropped, true);
        $transparentCropped = \imagecolorallocatealpha($cropped, 0, 0, 0, 127);
        \imagefilledrectangle($cropped, 0, 0, $targetWidth, $targetHeight, $transparentCropped);

        \imagecopy(
            $cropped,
            $resized,
            0,
            0,
            $cropX,
            $cropY,
            $targetWidth,
            $targetHeight
        );

        \imagedestroy($resized);

        return $cropped;
    }

    private function resizeToFit($image, int $targetWidth, int $targetHeight)
    {
        $sourceWidth = \imagesx($image);
        $sourceHeight = \imagesy($image);
        $scale = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $resizeWidth = (int)max(1, floor($sourceWidth * $scale));
        $resizeHeight = (int)max(1, floor($sourceHeight * $scale));

        $canvas = \imagecreatetruecolor($targetWidth, $targetHeight);
        \imagealphablending($canvas, false);
        \imagesavealpha($canvas, true);
        $transparent = \imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        \imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);

        $destX = (int)max(0, floor(($targetWidth - $resizeWidth) / 2));
        $destY = (int)max(0, floor(($targetHeight - $resizeHeight) / 2));

        \imagecopyresampled(
            $canvas,
            $image,
            $destX,
            $destY,
            0,
            0,
            $resizeWidth,
            $resizeHeight,
            $sourceWidth,
            $sourceHeight
        );

        return $canvas;
    }
}
