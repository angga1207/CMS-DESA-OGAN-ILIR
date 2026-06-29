<?php

namespace App\Support;

use InvalidArgumentException;

final class CoordinatePair
{
    /**
     * @return array{latitude: float, longitude: float}
     */
    public static function parse(string $value): array
    {
        $parts = array_map('trim', explode(',', $value));

        if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
            throw new InvalidArgumentException('Format koordinat harus berupa latitude, longitude.');
        }

        $latitude = (float) $parts[0];
        $longitude = (float) $parts[1];

        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException('Latitude harus berada di antara -90 dan 90.');
        }

        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Longitude harus berada di antara -180 dan 180.');
        }

        return ['latitude' => $latitude, 'longitude' => $longitude];
    }

    public static function format(mixed $latitude, mixed $longitude): string
    {
        if ($latitude === null || $longitude === null) {
            return '';
        }

        return rtrim(rtrim(number_format((float) $latitude, 7, '.', ''), '0'), '.').', '
            .rtrim(rtrim(number_format((float) $longitude, 7, '.', ''), '0'), '.');
    }
}
