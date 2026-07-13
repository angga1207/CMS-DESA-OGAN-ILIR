<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class VillageFeatures
{
    public static function definitions(): array
    {
        return [
            'articles' => ['label' => 'Artikel & Berita', 'description' => 'Artikel, kategori berita, dan sumber artikel.', 'icon' => 'fa-newspaper'],
            'pages' => ['label' => 'Halaman Khusus', 'description' => 'Custom page dan konten halaman publik.', 'icon' => 'fa-file-lines'],
            'banners' => ['label' => 'Banner Hero', 'description' => 'Banner carousel pada website desa.', 'icon' => 'fa-panorama'],
            'gallery' => ['label' => 'Galeri', 'description' => 'Album dan foto kegiatan desa.', 'icon' => 'fa-images'],
            'menus' => ['label' => 'Menu Dinamis', 'description' => 'Struktur navbar dan submenu website.', 'icon' => 'fa-bars-staggered'],
            'downloads' => ['label' => 'Unduhan', 'description' => 'Berkas dan dokumen publik.', 'icon' => 'fa-download'],
            'desa_cantik' => ['label' => 'Desa Cantik', 'description' => 'Publikasi, infografis, dan dokumen data statistik desa.', 'icon' => 'fa-chart-simple'],
            'officials' => ['label' => 'Perangkat Desa', 'description' => 'Absensi perangkat desa hari ini yang ditarik dari SIDESI.', 'icon' => 'fa-id-badge'],
            'businesses' => ['label' => 'UMKM', 'description' => 'Data usaha dan produk unggulan desa.', 'icon' => 'fa-store'],
            'bumdes' => ['label' => 'BUMDES', 'description' => 'Data badan usaha milik desa dan unit layanannya.', 'icon' => 'fa-building-user'],
            'projects' => ['label' => 'Pembangunan', 'description' => 'Progres dan dokumentasi pembangunan.', 'icon' => 'fa-person-digging'],
            'maps' => ['label' => 'Peta Sebaran', 'description' => 'Fasilitas umum dan bantuan yang ditarik dari SIDESI.', 'icon' => 'fa-map-location-dot'],
            'budgets' => ['label' => 'Anggaran', 'description' => 'Transparansi APBDes dan realisasi yang ditarik dari SIDESI.', 'icon' => 'fa-chart-pie'],
            'statistics' => ['label' => 'Statistik Desa', 'description' => 'Statistik penduduk, pekerjaan, pendidikan, dan usia dari SIDESI.', 'icon' => 'fa-chart-column'],
            'widgets' => ['label' => 'Widget Website', 'description' => 'Fitur mini yang dapat dipasang pada website desa.', 'icon' => 'fa-puzzle-piece'],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    public static function enabled(int $villageId, string $feature, ?User $user = null): bool
    {
        if ($user?->role === 'developer') {
            return true;
        }

        $value = DB::table('village_features')
            ->where('village_id', $villageId)
            ->where('feature_key', $feature)
            ->value('is_enabled');

        return $value === null || (bool) $value;
    }

    public static function enabledKeys(int $villageId): array
    {
        $stored = DB::table('village_features')
            ->where('village_id', $villageId)
            ->pluck('is_enabled', 'feature_key');

        return collect(self::keys())
            ->filter(fn (string $key): bool => ! $stored->has($key) || (bool) $stored[$key])
            ->values()
            ->all();
    }

    public static function sync(int $villageId, array $enabledFeatures): void
    {
        foreach (self::keys() as $feature) {
            DB::table('village_features')->updateOrInsert(
                ['village_id' => $villageId, 'feature_key' => $feature],
                ['is_enabled' => in_array($feature, $enabledFeatures, true), 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public static function syncDefaults(int $villageId): void
    {
        self::sync($villageId, self::keys());
    }

    public static function forModule(string $module): ?string
    {
        return [
            'menus' => 'menus',
            'officials' => 'officials',
            'businesses' => 'businesses',
            'bumdes' => 'bumdes',
            'projects' => 'projects',
            'maps' => 'maps',
            'files' => 'downloads',
            'desa-cantik' => 'desa_cantik',
            'budgets' => 'budgets',
            'demographics' => 'statistics',
        ][$module] ?? null;
    }

    public static function forReference(string $reference): ?string
    {
        return [
            'content-categories' => 'articles',
            'business-categories' => 'businesses',
            'bumdes-categories' => 'bumdes',
        ][$reference] ?? null;
    }
}
