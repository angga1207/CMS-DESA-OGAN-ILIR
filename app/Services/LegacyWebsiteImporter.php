<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\PublicSiteCache;
use App\Support\UniqueSlug;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class LegacyWebsiteImporter
{
    private string $sourceUrl;

    private int $villageId;

    private int $runId;

    public function import(int $villageId, string $sourceUrl, array $types, ?int $userId = null): array
    {
        $this->sourceUrl = rtrim($this->validateSourceUrl($sourceUrl), '/');
        $this->villageId = $villageId;
        $types = array_values(array_intersect($types, array_keys(config('legacy-import.types', []))));

        if ($types === []) {
            throw new RuntimeException('Pilih minimal satu jenis data yang akan dimigrasikan.');
        }

        $this->probe();
        $this->runId = DB::table('legacy_import_runs')->insertGetId([
            'village_id' => $villageId,
            'started_by' => $userId,
            'source_url' => $this->sourceUrl,
            'status' => 'running',
            'selected_types' => json_encode($types),
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = [];

        try {
            foreach ($types as $type) {
                $summary[$type] = $this->importType($type);
            }

            DB::table('legacy_import_runs')->where('id', $this->runId)->update([
                'status' => 'completed',
                'summary' => json_encode($summary),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            DB::table('legacy_import_runs')->where('id', $this->runId)->update([
                'status' => 'failed',
                'summary' => json_encode($summary),
                'error_message' => Str::limit($exception->getMessage(), 4000),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);

            throw $exception;
        }

        PublicSiteCache::forget($villageId);

        return $summary;
    }

    private function importType(string $type): array
    {
        $result = ['total' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0];

        foreach ($this->fetchAll($type) as $record) {
            $result['total']++;

            try {
                $status = DB::transaction(fn (): string => $this->persist($type, $record));
                $result[$status]++;
            } catch (Throwable $exception) {
                $result['failed']++;
                $this->recordItem($type, $record, $this->targetTable($type), null, 'failed', $exception->getMessage());
            }
        }

        return $result;
    }

    private function persist(string $type, array $record): string
    {
        return match ($type) {
            'pages' => $this->persistPage($record),
            'banners' => $this->persistBanner($record),
            'galleries' => $this->persistGallery($record),
            'videos' => $this->persistVideo($record),
            'downloads' => $this->persistDownload($record),
            'umkms' => $this->persistBusiness($record),
            'bumdes' => $this->persistBumdes($record),
            'developments' => $this->persistDevelopment($record),
            default => throw new RuntimeException("Jenis impor [{$type}] belum didukung."),
        };
    }

    private function persistPage(array $row): string
    {
        $body = $this->localizeHtmlAssets((string) ($row['content'] ?? ''), 'pages');
        $payload = [
            'village_id' => $this->villageId,
            'title' => $row['title'] ?? 'Halaman tanpa judul',
            'excerpt' => Str::limit(trim(strip_tags($body)), 500),
            'body' => $body,
            'featured_image_url' => $this->downloadAsset($row['thumbnail'] ?? null, 'pages'),
            'status' => $this->published($row['status'] ?? null) ? 'published' : 'draft',
            'published_at' => $this->date($row['created_at'] ?? null),
        ];

        return $this->upsert('pages', 'pages', $row, $payload);
    }

    private function persistBanner(array $row): string
    {
        $payload = [
            'village_id' => $this->villageId,
            'title' => $row['title'] ?? 'Banner',
            'subtitle' => $row['caption'] ?? null,
            'image_url' => $this->downloadAsset($row['filename'] ?? null, 'banners', true),
            'sort_order' => (int) ($row['id'] ?? 0),
            'is_active' => $this->published($row['status'] ?? null),
        ];

        return $this->upsert('hero_banners', 'banners', $row, $payload, false);
    }

    private function persistGallery(array $row): string
    {
        $cover = $this->downloadAsset($row['image'] ?? null, 'galleries');
        $status = $this->upsert('gallery_albums', 'galleries', $row, [
            'village_id' => $this->villageId,
            'title' => $row['title'] ?? 'Galeri',
            'description' => $row['content'] ?? null,
            'cover_image_url' => $cover,
            'album_date' => $this->date($row['created_at'] ?? null, 'Y-m-d'),
        ]);
        $item = $this->existingItem('galleries', $row);
        $albumId = (int) $item->target_id;
        DB::table('gallery_photos')->where('village_id', $this->villageId)->where('album_id', $albumId)->delete();

        foreach (array_values($row['photos'] ?? []) as $index => $photo) {
            $url = $this->downloadAsset($photo['image'] ?? null, 'galleries');
            if ($url) {
                DB::table('gallery_photos')->insert([
                    'village_id' => $this->villageId,
                    'album_id' => $albumId,
                    'title' => $row['title'] ?? null,
                    'image_url' => $url,
                    'sort_order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $status;
    }

    private function persistVideo(array $row): string
    {
        $youtubeId = $row['youtube_id'] ?? null;

        return $this->upsert('videos', 'videos', $row, [
            'village_id' => $this->villageId,
            'title' => $row['title'] ?? 'Video',
            'description' => $row['description'] ?? null,
            'video_url' => $youtubeId ? "https://www.youtube.com/watch?v={$youtubeId}" : null,
            'thumbnail_url' => $youtubeId ? "https://i.ytimg.com/vi/{$youtubeId}/hqdefault.jpg" : null,
            'published_at' => $this->date($row['created_at'] ?? null),
            'is_published' => $this->published($row['status'] ?? null),
        ]);
    }

    private function persistDownload(array $row): string
    {
        $googleId = trim((string) ($row['google_id'] ?? ''));

        return $this->upsert('downloadable_files', 'downloads', $row, [
            'village_id' => $this->villageId,
            'title' => $row['title'] ?? 'Dokumen',
            'description' => $row['description'] ?? null,
            'file_url' => $googleId !== '' ? "https://drive.google.com/uc?export=download&id={$googleId}" : '#',
            'published_at' => $this->date($row['created_at'] ?? null, 'Y-m-d'),
            'download_count' => (int) ($row['downloaded'] ?? 0),
            'is_published' => $this->published($row['status'] ?? null),
        ]);
    }

    private function persistBusiness(array $row): string
    {
        $categories = is_array($row['category'] ?? null) ? $row['category'] : [$row['category'] ?? 'UMKM'];
        $categoryId = $this->category('business_categories', (string) ($categories[0] ?? 'UMKM'));
        $photos = $row['photos'] ?? [];
        $featured = $this->downloadAsset($photos[0]['photo'] ?? null, 'umkms');
        $status = $this->upsert('businesses', 'umkms', $row, [
            'village_id' => $this->villageId,
            'category_id' => $categoryId,
            'name' => $row['name'] ?? 'UMKM',
            'owner_name' => $row['owner_name'] ?? null,
            'whatsapp' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
            'description' => $row['description'] ?? null,
            'featured_image_url' => $featured,
            'is_active' => $this->published($row['status'] ?? null),
        ]);
        $businessId = (int) $this->existingItem('umkms', $row)->target_id;
        DB::table('business_photos')->where('village_id', $this->villageId)->where('business_id', $businessId)->delete();

        foreach (array_values($photos) as $index => $photo) {
            $url = $this->downloadAsset($photo['photo'] ?? null, 'umkms');
            if ($url) {
                DB::table('business_photos')->insert([
                    'village_id' => $this->villageId,
                    'business_id' => $businessId,
                    'image_url' => $url,
                    'sort_order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $status;
    }

    private function persistBumdes(array $row): string
    {
        return $this->upsert('bumdes', 'bumdes', $row, [
            'village_id' => $this->villageId,
            'name' => $row['name'] ?? 'BUMDes',
            'whatsapp' => $row['phone'] ?? null,
            'instagram_url' => $this->nullableUrl($row['instagram'] ?? null),
            'facebook_url' => $this->nullableUrl($row['facebook'] ?? null),
            'tiktok_url' => $this->nullableUrl($row['tiktok'] ?? null),
            'address' => $row['address'] ?? null,
            'description' => $row['description'] ?? null,
            'featured_image_url' => $this->downloadAsset($row['image'] ?? null, 'bumdes'),
            'is_active' => $this->published($row['status'] ?? null),
        ]);
    }

    private function persistDevelopment(array $row): string
    {
        $images = $row['images'] ?? [];
        $images = is_string($images) ? (json_decode($images, true) ?: []) : $images;

        return $this->upsert('development_projects', 'developments', $row, [
            'village_id' => $this->villageId,
            'title' => $row['name'] ?? 'Pembangunan',
            'year' => max(1900, min(2100, (int) ($row['tahun'] ?? date('Y')))),
            'location' => $row['address'] ?? null,
            'source_fund' => $row['sumber_dana'] ?? null,
            'budget_amount' => abs((float) ($row['anggaran'] ?? 0)),
            'volume' => $row['volume'] ?? null,
            'progress_percentage' => 100,
            'status' => $this->published($row['status'] ?? null) ? 'completed' : 'planned',
            'description' => $row['keterangan'] ?? null,
            'image_url' => $this->downloadAsset($images[0] ?? null, 'developments'),
        ]);
    }

    private function upsert(string $table, string $type, array $row, array $payload, bool $withSlug = true): string
    {
        $hash = hash('sha256', json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $existing = $this->existingItem($type, $row);

        if ($existing && hash_equals($existing->payload_hash, $hash) && $existing->target_id) {
            $this->recordItem($type, $row, $table, (int) $existing->target_id, 'unchanged');

            return 'unchanged';
        }

        $targetId = $existing?->target_id;
        if ($withSlug) {
            $payload['slug'] = UniqueSlug::make($table, (string) ($row['slug'] ?? $payload['title'] ?? $payload['name']), $targetId ? (int) $targetId : null);
        }
        $payload['updated_at'] = $this->date($row['updated_at'] ?? null) ?? now();

        if ($targetId && DB::table($table)->where('id', $targetId)->where('village_id', $this->villageId)->exists()) {
            DB::table($table)->where('id', $targetId)->where('village_id', $this->villageId)->update($payload);
            $status = 'updated';
        } else {
            $targetId = DB::table($table)->insertGetId([...$payload, 'created_at' => $this->date($row['created_at'] ?? null) ?? now()]);
            $status = 'created';
        }

        $this->recordItem($type, $row, $table, (int) $targetId, $status);

        return $status;
    }

    private function fetchAll(string $type): array
    {
        $items = [];
        $page = 1;

        do {
            $response = $this->client()->get("{$this->sourceUrl}/{$type}", ['page' => $page, 'per_page' => 100]);
            $response->throw();
            $payload = $response->json();
            $batch = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $items = [...$items, ...$batch];
            $lastPage = max(1, (int) ($payload['meta']['last_page'] ?? 1));
            $page++;
        } while ($page <= $lastPage);

        return $items;
    }

    private function probe(): void
    {
        $response = $this->client()->get($this->sourceUrl);
        $response->throw();

        if (! is_array($response->json('data.endpoints'))) {
            throw new RuntimeException('URL bukan endpoint API publik desa yang didukung.');
        }
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('legacy-import.timeout', 30))
            ->retry(2, 300, throw: false)
            ->withUserAgent('CMS-Desa-Ogan-Ilir-Legacy-Importer/1.0');
    }

    private function downloadAsset(mixed $value, string $type, bool $required = false): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-' || $value === 'default.png' || $value === '.png') {
            if ($required) {
                throw new RuntimeException("Aset {$type} tidak tersedia pada payload lama.");
            }

            return null;
        }

        $urls = filter_var($value, FILTER_VALIDATE_URL)
            ? [$value]
            : (str_starts_with(ltrim($value, '/'), 'storage/')
                ? [$this->origin().'/'.ltrim($value, '/')]
                : array_map(
                    fn (string $directory): string => $this->origin().'/'.trim($directory, '/').'/'.ltrim($value, '/'),
                    config("legacy-import.asset_directories.{$type}", ['storage/images']),
                ));

        foreach ($urls as $url) {
            $response = $this->client()->get($url);
            $contentType = strtolower((string) $response->header('Content-Type'));
            $body = $response->body();
            if ($response->successful() && $body !== '' && ! str_contains($contentType, 'text/html') && strlen($body) <= (int) config('legacy-import.max_asset_size')) {
                $extension = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
                $extension = preg_match('/^[a-z0-9]{2,5}$/i', $extension) ? strtolower($extension) : 'bin';
                $path = "legacy-imports/{$this->villageId}/{$type}/".hash('sha256', $url).".{$extension}";
                Storage::disk('public')->put($path, $body);

                return Storage::url($path);
            }
        }

        if ($required) {
            throw new RuntimeException("Aset wajib gagal diunduh: {$value}");
        }

        return null;
    }

    private function localizeHtmlAssets(string $html, string $type): string
    {
        return (string) preg_replace_callback(
            "/(<img\\b[^>]*\\bsrc=[\"'])([^\"']+)([\"'])/i",
            function (array $match) use ($type): string {
                $asset = $this->downloadAsset($match[2], $type);

                return $match[1].($asset ?: $match[2]).$match[3];
            },
            $html,
        );
    }

    private function recordItem(string $type, array $row, string $table, ?int $targetId, string $status, ?string $message = null): void
    {
        DB::table('legacy_import_items')->updateOrInsert(
            [
                'village_id' => $this->villageId,
                'source_url' => $this->sourceUrl,
                'source_type' => $type,
                'source_id' => (string) ($row['id'] ?? $row['slug'] ?? hash('sha256', json_encode($row))),
            ],
            [
                'run_id' => $this->runId,
                'target_table' => $table,
                'target_id' => $targetId,
                'payload_hash' => hash('sha256', json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                'status' => $status,
                'message' => $message ? Str::limit($message, 2000) : null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function existingItem(string $type, array $row): ?object
    {
        return DB::table('legacy_import_items')
            ->where('village_id', $this->villageId)
            ->where('source_url', $this->sourceUrl)
            ->where('source_type', $type)
            ->where('source_id', (string) ($row['id'] ?? $row['slug'] ?? hash('sha256', json_encode($row))))
            ->first();
    }

    private function category(string $table, string $name): int
    {
        $name = trim($name) ?: 'Lainnya';
        $id = DB::table($table)->where('village_id', $this->villageId)->where('name', $name)->value('id');

        return $id ? (int) $id : DB::table($table)->insertGetId([
            'village_id' => $this->villageId,
            'name' => $name,
            'slug' => UniqueSlug::make($table, $name),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function targetTable(string $type): string
    {
        return match ($type) {
            'pages' => 'pages',
            'banners' => 'hero_banners',
            'galleries' => 'gallery_albums',
            'videos' => 'videos',
            'downloads' => 'downloadable_files',
            'umkms' => 'businesses',
            'bumdes' => 'bumdes',
            'developments' => 'development_projects',
        };
    }

    private function validateSourceUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            throw new RuntimeException('Endpoint sumber wajib berupa URL HTTPS yang valid.');
        }

        return $url;
    }

    private function origin(): string
    {
        $parts = parse_url($this->sourceUrl);

        return "{$parts['scheme']}://{$parts['host']}".(isset($parts['port']) ? ":{$parts['port']}" : '');
    }

    private function published(mixed $status): bool
    {
        return in_array(Str::lower(trim((string) $status)), ['publish', 'published', 'post', 'active', 'aktif'], true);
    }

    private function date(mixed $value, string $format = 'Y-m-d H:i:s'): ?string
    {
        try {
            return $value ? CarbonImmutable::parse((string) $value)->format($format) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function nullableUrl(mixed $value): ?string
    {
        $value = trim((string) $value);

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }
}
