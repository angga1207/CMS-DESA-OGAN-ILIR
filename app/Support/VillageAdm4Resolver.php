<?php

declare(strict_types=1);

namespace App\Support;

final class VillageAdm4Resolver
{
    public static function resolve(string $villageName, string $districtName = ''): ?array
    {
        $path = public_path('data/wilayah_ogan_ilir_2022.json');

        if (! is_file($path)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload)) {
            return null;
        }

        $normalizedVillage = self::normalize($villageName);
        $normalizedDistrict = self::normalize($districtName);

        foreach (($payload['kecamatan'] ?? []) as $district) {
            if ($normalizedDistrict !== '' && self::normalize((string) ($district['nama'] ?? '')) !== $normalizedDistrict) {
                continue;
            }

            foreach (($district['wilayah'] ?? []) as $area) {
                if (self::normalize((string) ($area['nama'] ?? '')) !== $normalizedVillage) {
                    continue;
                }

                return [
                    'adm4' => (string) $area['kode'],
                    'village' => (string) $area['nama'],
                    'district' => (string) ($district['nama'] ?? ''),
                ];
            }
        }

        return null;
    }

    private static function normalize(string $value): string
    {
        return Str($value)
            ->lower()
            ->replaceMatches('/\b(desa|kelurahan|kabupaten|kab\.|kota|kec\.|kecamatan)\b/u', '')
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->toString();
    }
}
