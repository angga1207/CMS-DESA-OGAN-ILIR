<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ApplicationVersions;
use App\Support\VillageFeatures;
use App\Support\WidgetCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class PublicVillageSite
{
    public function get(object $village): array
    {
        $frontendVersion = (string) (ApplicationVersions::frontend()['current_version'] ?? 'unknown');

        return Cache::flexible(
            $this->cacheKey((int) $village->id, $this->revision((int) $village->id), $frontendVersion),
            [
                max((int) config('public-site.cache_ttl'), 1),
                max((int) config('public-site.cache_stale_ttl'), 2),
            ],
            fn (): array => $this->build($village),
            ['seconds' => 30],
        );
    }

    public function forget(int $villageId): void
    {
        $revision = $this->revision($villageId);
        $frontendVersion = (string) (ApplicationVersions::frontend()['current_version'] ?? 'unknown');
        Cache::increment($this->revisionKey($villageId));
        Cache::forget($this->cacheKey($villageId, $revision, $frontendVersion));
    }

    public function revision(int $villageId): int
    {
        return (int) Cache::rememberForever($this->revisionKey($villageId), fn (): int => 1);
    }

    public function contentHtml(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return preg_replace_callback(
            '~\b(src|href)=(["\'])(/storage/[^"\']+)\2~i',
            fn (array $match): string => $match[1].'='.$match[2].url($match[3]).$match[2],
            $html,
        );
    }

    private function build(object $village): array
    {
        $villageId = (int) $village->id;
        $settings = DB::table('site_settings')->where('village_id', $villageId)->pluck('value', 'key')->all();
        $shortcuts = json_decode((string) ($settings['home_shortcuts'] ?? '[]'), true);
        $shortcutsEnabled = ! in_array(strtolower((string) ($settings['home_shortcuts_enabled'] ?? '1')), ['0', 'false', 'off', 'no'], true);

        return [
            'village' => [
                'id' => $villageId,
                'name' => $village->name,
                'slug' => $village->slug,
                'district' => $village->district,
                'regency' => $village->regency,
                'province' => $village->province,
                'address' => $village->address,
                'phone' => $village->phone,
                'email' => $village->email,
                'website_url' => $village->website_url,
                'public_hostname' => $village->public_hostname,
                'latitude' => $village->latitude,
                'longitude' => $village->longitude,
                'logo_url' => $village->logo_url,
                'favicon_url' => $village->favicon_url,
                'welcome_message' => $village->welcome_message,
                'description' => $village->description,
                'vision' => $village->vision,
                'mission' => $village->mission,
            ],
            'theme' => ($settings['site_theme'] ?? 'modern-style-1') === 'tanjung-lubuk' ? 'modern-style-1' : $settings['site_theme'],
            'application_version' => [
                'frontend' => ApplicationVersions::frontend()['current_version'] ?? null,
            ],
            'settings' => $settings,
            'theme_config' => [
                'primary' => $settings['theme_primary'] ?? '#8f1d2c',
                'secondary' => $settings['theme_secondary'] ?? '#102f28',
                'tertiary' => $settings['theme_tertiary'] ?? '#d8e8a5',
                'surface' => $settings['theme_surface'] ?? '#f7f7f2',
                'text' => $settings['theme_text'] ?? '#17221f',
                'font_style' => $settings['font_style'] ?? 'classic',
            ],
            'shortcuts' => $shortcutsEnabled && is_array($shortcuts) ? array_slice(array_values($shortcuts), 0, 4) : [],
            'features' => VillageFeatures::enabledKeys($villageId),
            'navigation' => $this->navigation($villageId),
            'banners' => $this->rows('hero_banners', $villageId, fn ($query) => $query->where('is_active', true)->orderBy('sort_order')),
            'posts' => DB::table('posts')
                ->leftJoin('content_categories', 'posts.category_id', '=', 'content_categories.id')
                ->where('posts.village_id', $villageId)
                ->where('posts.status', 'published')
                ->where(fn ($query) => $query->whereNull('posts.published_at')->orWhere('posts.published_at', '<=', now()))
                ->orderByDesc('posts.published_at')
                ->limit(max((int) config('public-site.article_limit'), 1))
                ->get(['posts.*', 'content_categories.name as category_name'])
                ->map(fn (object $row): array => $this->contentRow((array) $row))
                ->all(),
            'pages' => array_map(
                fn (array $row): array => $this->contentRow($row),
                $this->rows('pages', $villageId, fn ($query) => $query->where('status', 'published')->orderBy('title')),
            ),
            'galleries' => $this->galleries($villageId),
            'businesses' => $this->businesses($villageId),
            'bumdes' => $this->bumdes($villageId),
            'projects' => $this->rows('development_projects', $villageId, fn ($query) => $query->orderByDesc('year')->orderByDesc('updated_at')),
            'downloads' => $this->rows('downloadable_files', $villageId, fn ($query) => $query->where('is_published', true)->orderByDesc('published_at')),
            'desa_cantik' => $this->desaCantik($villageId),
            'widgets' => DB::table('village_widgets')
                ->where('village_id', $villageId)
                ->where('is_active', true)
                ->whereIn('widget_type', array_keys(WidgetCatalog::all()))
                ->orderBy('placement')
                ->orderBy('sort_order')
                ->get()
                ->map(function (object $widget): array {
                    $row = (array) $widget;
                    $row['placement'] = WidgetCatalog::normalizePlacement($widget->widget_type, $widget->placement);
                    $row['config'] = json_decode($widget->config ?: '{}', true) ?: [];

                    return $row;
                })
                ->groupBy('placement')
                ->map(fn (Collection $widgets): array => $widgets->values()->all())
                ->all(),
            'endpoints' => [
                'widgets' => route('api.villages.widgets.index', $village->slug),
                'officials_today' => route('api.villages.officials.today', $village->slug),
                'budget' => route('api.villages.budget.show', $village->slug),
                'statistics' => route('api.villages.statistics.show', $village->slug),
                'maps' => [
                    'categories' => route('api.villages.map.categories', $village->slug),
                    'facilities' => route('api.villages.map.facilities', $village->slug),
                    'assistance' => route('api.villages.map.assistance', $village->slug),
                ],
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function navigation(int $villageId): array
    {
        $items = DB::table('navigation_items')
            ->leftJoin('pages', 'navigation_items.page_id', '=', 'pages.id')
            ->where('navigation_items.village_id', $villageId)
            ->where('navigation_items.is_active', true)
            ->orderBy('navigation_items.sort_order')
            ->get(['navigation_items.*', 'pages.slug as page_slug'])
            ->map(fn (object $row): array => (array) $row);

        return $items->whereNull('parent_id')->map(function (array $item) use ($items): array {
            $item['children'] = $items->where('parent_id', $item['id'])->values()->all();

            return $item;
        })->values()->all();
    }

    private function galleries(int $villageId): array
    {
        $photos = DB::table('gallery_photos')->where('village_id', $villageId)->orderBy('sort_order')->get()->groupBy('album_id');
        $videos = DB::table('gallery_videos')->where('village_id', $villageId)->orderBy('sort_order')->get()->groupBy('album_id');

        return DB::table('gallery_albums')->where('village_id', $villageId)->orderByDesc('album_date')->get()
            ->map(function (object $album) use ($photos, $videos): array {
                $row = (array) $album;
                $albumPhotos = $photos->get($album->id, collect())
                    ->map(fn (object $photo): array => [...(array) $photo, 'type' => 'photo'])
                    ->values();
                $albumVideos = $videos->get($album->id, collect())
                    ->map(fn (object $video): array => [...(array) $video, 'type' => 'video'])
                    ->values();

                $row['photos'] = $albumPhotos->map(fn (array $photo): array => collect($photo)->except('type')->all())->all();
                $row['videos'] = $albumVideos->map(fn (array $video): array => collect($video)->except('type')->all())->all();
                $row['media'] = $albumPhotos
                    ->merge($albumVideos)
                    ->sortBy([
                        ['sort_order', 'asc'],
                        ['created_at', 'asc'],
                    ])
                    ->values()
                    ->all();

                return $row;
            })->all();
    }

    private function desaCantik(int $villageId): array
    {
        $categories = DB::table('desa_cantik_categories')
            ->where('village_id', $villageId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $items = DB::table('desa_cantik_posts')
            ->join('desa_cantik_categories', 'desa_cantik_posts.category_id', '=', 'desa_cantik_categories.id')
            ->where('desa_cantik_posts.village_id', $villageId)
            ->where('desa_cantik_posts.is_published', true)
            ->where('desa_cantik_categories.is_active', true)
            ->where(fn ($query) => $query->whereNull('desa_cantik_posts.published_at')->orWhere('desa_cantik_posts.published_at', '<=', now()))
            ->orderByDesc('desa_cantik_posts.published_at')
            ->orderByDesc('desa_cantik_posts.updated_at')
            ->get([
                'desa_cantik_posts.*',
                'desa_cantik_categories.name as category_name',
                'desa_cantik_categories.slug as category_slug',
                'desa_cantik_categories.type as category_type',
            ])
            ->map(fn (object $row): array => (array) $row)
            ->all();

        return [
            'categories' => $categories,
            'items' => $items,
        ];
    }

    private function businesses(int $villageId): array
    {
        $photos = DB::table('business_photos')
            ->where('village_id', $villageId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('business_id');

        return DB::table('businesses')
            ->where('village_id', $villageId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (object $business) use ($photos): array {
                $row = (array) $business;
                $row['photos'] = $photos
                    ->get($business->id, collect())
                    ->map(fn (object $photo): array => (array) $photo)
                    ->values()
                    ->all();

                return $row;
            })
            ->all();
    }

    private function bumdes(int $villageId): array
    {
        $photos = DB::table('bumdes_photos')
            ->where('village_id', $villageId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('bumdes_id');

        return DB::table('bumdes')
            ->where('village_id', $villageId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (object $bumdes) use ($photos): array {
                $row = (array) $bumdes;
                $row['photos'] = $photos
                    ->get($bumdes->id, collect())
                    ->map(fn (object $photo): array => (array) $photo)
                    ->values()
                    ->all();

                return $row;
            })
            ->all();
    }

    private function rows(string $table, int $villageId, callable $scope): array
    {
        return $scope(DB::table($table)->where('village_id', $villageId))->get()->map(fn (object $row): array => (array) $row)->all();
    }

    private function contentRow(array $row): array
    {
        if (array_key_exists('body', $row)) {
            $row['body'] = $this->contentHtml($row['body']);
        }

        return $row;
    }

    private function cacheKey(int $villageId, int $revision, string $frontendVersion): string
    {
        return "public-site:v7:village:{$villageId}:revision:{$revision}:frontend:{$frontendVersion}";
    }

    private function revisionKey(int $villageId): string
    {
        return "public-site:revision:village:{$villageId}";
    }
}
