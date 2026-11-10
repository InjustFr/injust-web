<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Shared\ImageResizer;
use App\Application\Shared\ResizedImage;
use App\Domain\Shared\Exception\UnsupportedImage;

final readonly class GdImageResizer implements ImageResizer
{
    private const int QUALITY = 86;

    public function resize(string $path, int $maxSide = self::MAX_SIDE): ResizedImage
    {
        $source = $this->open($path);
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxSide / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if (false === $target) {
            throw new UnsupportedImage();
        }
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        $written = imagewebp($target, null, self::QUALITY);
        $bytes = (string) ob_get_clean();
        if (!$written || '' === $bytes) {
            throw new UnsupportedImage();
        }

        return new ResizedImage($bytes, $targetWidth, $targetHeight);
    }

    private function open(string $path): \GdImage
    {
        $size = @getimagesize($path);
        $image = match (false === $size ? null : $size[2]) {
            \IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            \IMAGETYPE_PNG => @imagecreatefrompng($path),
            \IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            \IMAGETYPE_AVIF => \function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : false,
            default => false,
        };
        if (!$image instanceof \GdImage) {
            throw new UnsupportedImage();
        }
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }
}
