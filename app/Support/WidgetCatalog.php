<?php

namespace App\Support;

final class WidgetCatalog
{
    public static function all(): array
    {
        return config('widgets', []);
    }

    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    public static function options(): array
    {
        return collect(self::all())->mapWithKeys(
            fn (array $widget, string $type): array => [$type => $widget['label']],
        )->all();
    }

    public static function defaults(string $type): array
    {
        $widget = self::get($type);

        return collect($widget['fields'] ?? [])->mapWithKeys(
            fn (array $field, string $key): array => [$key => $field['default'] ?? ($field['type'] === 'checkbox' ? false : '')],
        )->all();
    }

    public static function rules(string $type): array
    {
        $widget = self::get($type);

        return collect($widget['fields'] ?? [])->mapWithKeys(
            fn (array $field, string $key): array => [
                "config.{$key}" => is_array($field['rule'] ?? null)
                    ? $field['rule']
                    : explode('|', $field['rule'] ?? 'nullable'),
            ],
        )->all();
    }

    public static function placements(): array
    {
        return [
            'home' => 'Beranda / Home',
            'header' => 'Header',
            'below_banner' => 'Di bawah Banner',
            'sidebar' => 'Sidebar',
            'footer' => 'Footer',
            'floating_left' => 'Melayang Kiri',
            'floating_right' => 'Melayang Kanan',
        ];
    }

    public static function allowedPlacements(string $type): array
    {
        $widget = self::get($type);

        return array_values($widget['placements'] ?? [$widget['default_placement'] ?? 'sidebar']);
    }

    public static function placementOptions(string $type): array
    {
        return collect(self::placements())
            ->only(self::allowedPlacements($type))
            ->all();
    }

    public static function normalizePlacement(string $type, string $placement): string
    {
        if (in_array($placement, self::allowedPlacements($type), true)) {
            return $placement;
        }

        return (string) (self::get($type)['default_placement'] ?? 'sidebar');
    }
}
