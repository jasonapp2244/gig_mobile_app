<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Small JPEG copies of marketplace photos for list screens
 * (public disk: list_images/abc.png -> list_thumbs/abc.jpg).
 * The original photo is never changed. If a thumbnail can't be made, image_url is used instead.
 */
class ListImageThumbnail
{
    public const DIR      = 'list_thumbs';
    public const MAX_SIDE = 480;
    public const QUALITY  = 75;

    public static function pathFor(?string $imagePath): ?string
    {
        if (! $imagePath) {
            return null;
        }

        return self::DIR . '/' . pathinfo($imagePath, PATHINFO_FILENAME) . '.jpg';
    }

    /** Creates the thumbnail; returns its path, or null when the photo can't be read. */
    public static function make(?string $imagePath): ?string
    {
        $thumbPath = self::pathFor($imagePath);
        $disk      = Storage::disk('public');

        if (! $thumbPath || ! $disk->exists($imagePath) || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $source = $disk->path($imagePath);

            // A PHP out-of-memory error can't be caught, so check the pixel size first
            // and skip the thumbnail (thumb_url then falls back to the full image).
            $info = @getimagesize($source);
            if (! $info || ! self::fitsInMemory((int) $info[0], (int) $info[1])) {
                return null;
            }

            $image  = @imagecreatefromstring((string) file_get_contents($source));
            if (! $image) {
                return null;
            }

            $width  = imagesx($image);
            $height = imagesy($image);
            $scale  = min(1, self::MAX_SIDE / max($width, $height));
            $newW   = max(1, (int) round($width * $scale));
            $newH   = max(1, (int) round($height * $scale));

            $thumb = imagecreatetruecolor($newW, $newH);
            // white background so transparent PNGs don't turn black
            imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
            imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
            unset($image); // free the full-size copy before rotating

            // rotate the small copy, not the full photo (much less memory)
            $thumb = self::applyExifOrientation($thumb, $source);

            ob_start();
            imagejpeg($thumb, null, self::QUALITY);
            $disk->put($thumbPath, (string) ob_get_clean());

            return $thumbPath;
        } catch (\Throwable $e) {
            Log::warning('List image thumbnail failed: ' . $e->getMessage(), ['path' => $imagePath]);

            return null;
        }
    }

    /** About 5 bytes per pixel for the decoded photo plus the small copy, with 16 MB to spare. */
    private static function fitsInMemory(int $width, int $height): bool
    {
        $limit = self::memoryLimitBytes();
        if ($limit <= 0) {
            return true; // no limit
        }

        $needed = ($width * $height * 5) + (self::MAX_SIDE * self::MAX_SIDE * 5 * 2);

        return memory_get_usage(true) + $needed + 16 * 1024 * 1024 < $limit;
    }

    private static function memoryLimitBytes(): int
    {
        $value = trim((string) ini_get('memory_limit'));
        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => $number,
        };
    }

    public static function delete(?string $imagePath): void
    {
        $thumbPath = self::pathFor($imagePath);
        if ($thumbPath) {
            Storage::disk('public')->delete($thumbPath);
        }
    }

    /** Phone photos are often stored sideways with an EXIF "rotate me" flag; GD ignores it. */
    private static function applyExifOrientation(\GdImage $image, string $file): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3       => 180,
            6       => -90,
            8       => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        return $rotated ?: $image;
    }
}
