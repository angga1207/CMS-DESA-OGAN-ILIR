<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

final class OptimizedImageStorage
{
    public function store(UploadedFile $upload, string $directory, string $profile = 'content_thumbnail'): string
    {
        $settings = config("image-uploads.profiles.{$profile}");

        if (! is_array($settings)) {
            throw new RuntimeException("Profil optimasi gambar [{$profile}] tidak ditemukan.");
        }

        $image = (new ImageManager(new Driver))
            ->decodePath($upload->getRealPath())
            ->scaleDown(
                width: (int) $settings['width'],
                height: (int) $settings['height'],
            );

        $encoded = $image->encode(new WebpEncoder(
            quality: (int) $settings['quality'],
            strip: true,
        ));
        $path = trim($directory, '/').'/'.Str::uuid().'.webp';

        Storage::disk('public')->put($path, (string) $encoded);

        return Storage::url($path);
    }

    public function replace(
        UploadedFile $upload,
        string $directory,
        ?string $oldUrl,
        string $profile = 'content_thumbnail',
    ): string {
        $newUrl = $this->store($upload, $directory, $profile);
        $this->delete($oldUrl);

        return $newUrl;
    }

    public function delete(?string $url): void
    {
        if (! $url) {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, '/storage/')) {
            return;
        }

        Storage::disk('public')->delete(Str::after($path, '/storage/'));
    }
}
