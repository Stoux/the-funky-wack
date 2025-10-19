<?php

namespace App\Jobs;

use App\Models\Edition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

class ResizePostersJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param string $path Path relative to the public disk root, e.g. "posters/123.jpg"
     */
    public function __construct(
        private Edition $edition,
    )
    {
        $this->queue = 'long-running-queue';
    }

    public function uniqueId(): string
        {
            return 'edition:' . ($this->edition->id ?? 'unknown');
        }

        public function handle(): void
    {
        $disk = \Storage::disk('public');

        $path = $this->edition->poster_path;

        if (!$disk->exists($path)) {
            Log::warning('ResizePostersJob: file does not exist on public disk', [
                'path' => $path,
            ]);
            return;
        }

        $absolute = $disk->path($path);
        $info = @getimagesize($absolute);

        if ($info === false || !isset($info['mime'])) {
            Log::warning('ResizePostersJob: unable to read image info', [
                'path' => $path,
            ]);
            return;
        }

        $mime = $info['mime'];

        // Skip SVGs (vector) – not supported by GD for rasterization.
        if ($mime === 'image/svg+xml') {
            Log::info('ResizePostersJob: skipping SVG poster (not rasterized)', [
                'path' => $path,
            ]);
            return;
        }

        $origWidth = $info[0] ?? null;
        $origHeight = $info[1] ?? null;

        if (!$origWidth || !$origHeight) {
            Log::warning('ResizePostersJob: could not determine width/height', [
                'path' => $path,
            ]);
            return;
        }

        $sizes = [250, 500, 1000, 1500, 2000];
        $pathInfo = pathinfo($path);
        $base = $pathInfo['dirname'] !== '.' ? $pathInfo['dirname'].'/'.$pathInfo['filename'] : $pathInfo['filename'];
        $ext = strtolower($pathInfo['extension'] ?? '');

        $isWebpOriginal = ($mime === 'image/webp' || $ext === 'webp');

        // Ensure a full-size webp exists (converted from the original if needed)
        $originalWebpPath = $isWebpOriginal ? $path : $base.'.webp';

        // Compute a deterministic version hash based on original file bytes (first 10 chars of sha1)
        $version = substr(sha1_file($absolute) ?: sha1((string)file_get_contents($absolute)), 0, 10);

        try {
            if (!$isWebpOriginal) {
                // Convert original to webp at original dimensions
                if (!$disk->exists($originalWebpPath)) {
                    $im = $this->createImageFromPath($absolute, $mime);
                    if (!$im) {
                        Log::warning('ResizePostersJob: failed to create image resource for conversion', [
                            'path' => $path,
                            'mime' => $mime,
                        ]);
                        return;
                    }
                    // For PNG/GIF ensure alpha preserved
                    $this->prepareAlpha($im, $mime);

                    $targetAbs = $disk->path($originalWebpPath);
                    $this->ensureDirectory($targetAbs);

                    if (!imagewebp($im, $targetAbs, 80)) {
                        imagedestroy($im);
                        Log::warning('ResizePostersJob: failed to save original webp', [
                            'target' => $originalWebpPath,
                        ]);
                        return;
                    }
                    imagedestroy($im);
                }
            }

            $generated = [];
            // Include the full-size webp if it exists (and is not the original non-webp)
            if ($disk->exists($originalWebpPath)) {
                $generated[] = ['path' => $originalWebpPath, 'width' => (int)$origWidth, 'version' => $version];
            }

            // Generate resized webps from the original source (prefer the original file to avoid re-compression chain)
            foreach ($sizes as $targetWidth) {
                if ($origWidth < $targetWidth) {
                    // Skip sizes larger than source
                    continue;
                }

                $resizedPath = $base.'-'.$targetWidth.'.webp';
                if ($disk->exists($resizedPath)) {
                    // already exists; collect it into srcset
                    $generated[] = ['path' => $resizedPath, 'width' => (int)$targetWidth, 'version' => $version];
                    continue; // idempotent
                }

                // Load from original file (even if webp) to resize
                $sourceIm = $this->createImageFromPath($absolute, $mime);
                if (!$sourceIm) {
                    Log::warning('ResizePostersJob: failed to create image for resize', [
                        'path' => $path,
                        'mime' => $mime,
                    ]);
                    break; // don't attempt further sizes
                }

                $ratio = $origHeight / $origWidth;
                $targetHeight = (int)round($targetWidth * $ratio);

                $resampled = imagecreatetruecolor($targetWidth, $targetHeight);
                // Preserve transparency when needed
                $this->prepareAlphaForDestination($resampled, $mime);

                imagecopyresampled($resampled, $sourceIm, 0, 0, 0, 0, $targetWidth, $targetHeight, $origWidth, $origHeight);

                $targetAbs = $disk->path($resizedPath);
                $this->ensureDirectory($targetAbs);

                if (!imagewebp($resampled, $targetAbs, 80)) {
                    Log::warning('ResizePostersJob: failed to save resized webp', [
                        'target' => $resizedPath,
                    ]);
                    // continue to try other sizes
                } else {
                    $generated[] = ['path' => $resizedPath, 'width' => (int)$targetWidth, 'version' => $version];
                }

                imagedestroy($sourceIm);
                imagedestroy($resampled);
            }

            // Save srcset on the model as array of objects with path and width
            $this->edition->poster_srcset = collect($generated)
                ->unique('path')
                ->sortBy('width')
                ->values();
            $this->edition->save();
        } catch (\Throwable $e) {
            Log::error('ResizePostersJob: unexpected error', [
                'path' => $path,
                'e' => $e,
            ]);
            throw $e;
        }
    }

    private function createImageFromPath(string $absolutePath, string $mime): \GdImage|false
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($absolutePath),
            'image/png' => @imagecreatefrompng($absolutePath),
            'image/gif' => @imagecreatefromgif($absolutePath),
            'image/webp' => @imagecreatefromwebp($absolutePath),
            default => false,
        };
    }

    private function prepareAlpha(\GdImage $im, string $mime): void
    {
        if (in_array($mime, ['image/png', 'image/gif'], true)) {
            imagealphablending($im, false);
            imagesavealpha($im, true);
        }
    }

    private function prepareAlphaForDestination(\GdImage $im, string $sourceMime): void
    {
        // Create a fully transparent background for formats that support transparency
        if (in_array($sourceMime, ['image/png', 'image/gif'], true)) {
            imagealphablending($im, false);
            $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $transparent);
            imagesavealpha($im, true);
        }
    }

    private function ensureDirectory(string $absoluteTargetPath): void
    {
        $dir = dirname($absoluteTargetPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}
