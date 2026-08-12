<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuestionImageService
{
    private const DISK = 'public';

    public function replace(?string $existingPath, ?UploadedFile $file, bool $remove, string $directory): ?string
    {
        if (! $file && ! $remove) {
            return $existingPath;
        }

        if (! $file) {
            $this->delete($existingPath);

            return null;
        }

        $path = $this->convertToWebp($file, $directory);
        $this->delete($existingPath);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        return $path ? Storage::disk(self::DISK)->url($path) : null;
    }

    private function convertToWebp(UploadedFile $file, string $directory): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw new \RuntimeException('Question image uploads require PHP GD with WebP support enabled.');
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new \RuntimeException('The uploaded image could not be decoded.');
        }

        imagealphablending($source, false);
        imagesavealpha($source, true);
        ob_start();
        $converted = imagewebp($source, null, 85);
        $contents = ob_get_clean();
        if (! $converted) {
            imagedestroy($source);
            throw new \RuntimeException('The uploaded image could not be converted to WebP.');
        }

        imagedestroy($source);

        if ($contents === false) {
            throw new \RuntimeException('The converted WebP image could not be read.');
        }

        $path = trim($directory, '/').'/'.Str::ulid().'.webp';
        if (! Storage::disk(self::DISK)->put($path, $contents)) {
            throw new \RuntimeException('The converted WebP image could not be stored.');
        }

        return $path;
    }
}
