<?php

declare(strict_types=1);

namespace App\Support;

final class SidesiStatisticNormalizer
{
    public static function population(array $response): array
    {
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        return [
            'total_population' => (int) ($data['total_penduduk'] ?? 0),
            'male_population' => (int) ($data['total_laki_laki'] ?? 0),
            'female_population' => (int) ($data['total_perempuan'] ?? 0),
            'total_families' => (int) ($data['total_kepala_keluarga'] ?? 0),
            'unknown_population' => (int) ($data['total_tidak_diketahui'] ?? 0),
        ];
    }

    public static function distribution(array $response): array
    {
        $rows = is_array($response['data'] ?? null) ? $response['data'] : [];

        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'label' => (string) ($row['kelompok'] ?? ''),
                'total' => (int) ($row['jumlah_jiwa'] ?? 0),
                'percentage' => self::percentage($row['persen'] ?? 0),
                'male' => (int) ($row['laki_laki_jiwa'] ?? 0),
                'male_percentage' => self::percentage($row['persen_laki'] ?? 0),
                'female' => (int) ($row['perempuan_jiwa'] ?? 0),
                'female_percentage' => self::percentage($row['persen_perempuan'] ?? 0),
            ])
            ->values()
            ->all();
    }

    private static function percentage(mixed $value): float
    {
        return (float) str_replace(['%', ','], ['', '.'], trim((string) $value));
    }
}
