<?php

declare(strict_types=1);

namespace App\Support;

use JsonException;
use RuntimeException;

final class ApplicationVersions
{
    /**
     * @return array{backend: array<string, mixed>, frontend: array<string, mixed>}
     */
    public static function all(): array
    {
        return [
            'backend' => self::read('cms-backend.json'),
            'frontend' => self::read('public-frontend.json'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function backend(): array
    {
        return self::read('cms-backend.json');
    }

    /**
     * @return array<string, mixed>
     */
    public static function frontend(): array
    {
        return self::read('public-frontend.json');
    }

    /**
     * @return array<string, mixed>
     */
    private static function read(string $filename): array
    {
        $path = resource_path("data/application-versions/{$filename}");
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException("File versi aplikasi [{$filename}] tidak ditemukan.");
        }

        try {
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                "Format JSON versi aplikasi [{$filename}] tidak valid: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        if (! is_array($data)) {
            throw new RuntimeException("Isi file versi aplikasi [{$filename}] harus berupa object JSON.");
        }

        return $data;
    }
}
