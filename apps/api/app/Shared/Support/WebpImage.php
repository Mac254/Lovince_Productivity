<?php

namespace App\Shared\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Stores an uploaded photo as WebP, scaled down so its longest side fits `$maxSide`. Re-encoding
 * drops EXIF and other metadata (camera, GPS location), and a phone photo shrinks to a fraction of
 * its size. An image that cannot be decoded, or is too large to decode safely, is rejected rather
 * than stored as uploaded, so no original with its metadata is ever kept.
 */
final class WebpImage
{
    /** About 100 MB of decoded pixels: a 24-megapixel photo fits, a decompression bomb doesn't. */
    public const MAX_PIXELS = 25_000_000;

    private const QUALITY = 80;

    /**
     * @param  string  $field  the request field the upload came in, for the validation error
     * @return string the stored path, `$name` plus `.webp`
     *
     * @throws ValidationException when the image cannot be converted
     */
    public static function store(UploadedFile $file, string $directory, string $name, string $disk, int $maxSide, string $field): string
    {
        $webp = self::encode($file, $maxSide, $field);
        $path = "{$directory}/{$name}.webp";
        Storage::disk($disk)->put($path, $webp);

        return $path;
    }

    private static function encode(UploadedFile $file, int $maxSide, string $field): string
    {
        $size = @getimagesize($file->getRealPath());
        if ($size && $size[0] * $size[1] > self::MAX_PIXELS) {
            self::reject($field, 'This image is too large to process. Use one under '.intdiv(self::MAX_PIXELS, 1_000_000).' megapixels.');
        }

        $image = $size && function_exists('imagewebp') ? @imagecreatefromstring((string) file_get_contents($file->getRealPath())) : false;
        if (! $image instanceof GdImage) {
            self::reject($field, 'This image couldn’t be processed. Try saving it as a JPEG or PNG.');
        }

        // Scale first, so rotating copies the small image rather than the full-size one.
        $image = self::upright(self::scaled($image, $maxSide), $file, $size[2]);
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $written = imagewebp($image, null, self::QUALITY);
        $bytes = (string) ob_get_clean();

        if (! $written || $bytes === '') {
            self::reject($field, 'This image couldn’t be processed. Try saving it as a JPEG or PNG.');
        }

        return $bytes;
    }

    private static function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
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
