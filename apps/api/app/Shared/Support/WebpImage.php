<?php

namespace App\Shared\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores an uploaded photo as WebP, scaled down so its longest side fits `$maxSide`. Re-encoding
 * drops EXIF and other metadata (camera, GPS location), and a phone photo shrinks to a fraction of
 * its size. If the image cannot be decoded, or is too large to decode safely, the original is kept.
 */
final class WebpImage
{
    private const MAX_PIXELS = 50_000_000;

    private const QUALITY = 80;

    /**
     * @return string the stored path, `$name` plus `.webp` (or the original extension on fallback)
     */
    public static function store(UploadedFile $file, string $directory, string $name, string $disk, int $maxSide): string
    {
        $webp = self::encode($file, $maxSide);
        if ($webp === null) {
            return $file->storeAs($directory, $name.'.'.$file->extension(), $disk);
        }

        $path = "{$directory}/{$name}.webp";
        Storage::disk($disk)->put($path, $webp);

        return $path;
    }

    private static function encode(UploadedFile $file, int $maxSide): ?string
    {
        $size = @getimagesize($file->getRealPath());
        if (! $size || $size[0] * $size[1] > self::MAX_PIXELS || ! function_exists('imagewebp')) {
            return null;
        }

        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $image instanceof GdImage) {
            return null;
        }

        $image = self::scaled(self::upright($image, $file, $size[2]), $maxSide);
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $written = imagewebp($image, null, self::QUALITY);
        $bytes = (string) ob_get_clean();

        return $written && $bytes !== '' ? $bytes : null;
    }

    /** Phone cameras store rotation in EXIF instead of the pixels; apply it before EXIF is dropped. */
    private static function upright(GdImage $image, UploadedFile $file, int $type): GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }
        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

        return $angle ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }

    private static function scaled(GdImage $image, int $maxSide): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = $maxSide / max($width, $height);
        if ($ratio >= 1) {
            return $image;
        }

        return imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)), IMG_BICUBIC) ?: $image;
    }
}
