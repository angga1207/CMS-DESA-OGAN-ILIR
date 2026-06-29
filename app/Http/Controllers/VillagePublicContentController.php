<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PublicVillageSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class VillagePublicContentController extends Controller
{
    public function __construct(
        private readonly PublicVillageSite $site,
    ) {}

    public function posts(Request $request, string $village): JsonResponse
    {
        $record = $this->village($village);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'in:village,regency'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:6,24'],
        ]);
        $cacheKey = 'public-posts:v2:'.$record->id.':revision:'.$this->site->revision((int) $record->id).':'.md5(json_encode($filters));

        $payload = Cache::flexible($cacheKey, [
            max((int) config('public-site.cache_ttl'), 1),
            max((int) config('public-site.cache_stale_ttl'), 2),
        ], function () use ($record, $filters): array {
            $query = DB::table('posts')
                ->leftJoin('content_categories', 'posts.category_id', '=', 'content_categories.id')
                ->where('posts.village_id', $record->id)
                ->where('posts.status', 'published')
                ->where(fn ($builder) => $builder->whereNull('posts.published_at')->orWhere('posts.published_at', '<=', now()))
                ->when($filters['q'] ?? null, fn ($builder, string $search) => $builder->where(fn ($nested) => $nested->whereLike('posts.title', "%{$search}%")->orWhereLike('posts.excerpt', "%{$search}%")))
                ->when($filters['category'] ?? null, fn ($builder, string $category) => $builder->where('content_categories.slug', $category))
                ->when($filters['source'] ?? null, fn ($builder, string $source) => $builder->where('posts.source_type', $source))
                ->orderByDesc('posts.published_at')
                ->select('posts.*', 'content_categories.name as category_name', 'content_categories.slug as category_slug');

            $pagination = $query->paginate((int) ($filters['per_page'] ?? 9))->toArray();
            $pagination['data'] = array_map(function (array|object $post): array {
                $post = (array) $post;
                $post['body'] = $this->site->contentHtml($post['body'] ?? null);

                return $post;
            }, $pagination['data']);

            return [
                'items' => $pagination['data'],
                'meta' => collect($pagination)->except('data', 'links')->all(),
                'filters' => [
                    'categories' => DB::table('content_categories')
                        ->where('village_id', $record->id)
                        ->orderBy('name')
                        ->get(['name', 'slug'])
                        ->map(fn (object $category): array => (array) $category)
                        ->all(),
                    'sources' => [['value' => 'village', 'label' => 'Desa'], ['value' => 'regency', 'label' => 'Kabupaten']],
                ],
            ];
        }, ['seconds' => 30]);

        return response()->json(['data' => $payload]);
    }

    public function post(string $village, string $slug): JsonResponse
    {
        $record = $this->village($village);
        $revision = $this->site->revision((int) $record->id);
        $payload = Cache::flexible("public-post:v2:{$record->id}:revision:{$revision}:{$slug}", [
            max((int) config('public-site.cache_ttl'), 1),
            max((int) config('public-site.cache_stale_ttl'), 2),
        ], function () use ($record, $slug): array {
            $post = DB::table('posts')
                ->leftJoin('content_categories', 'posts.category_id', '=', 'content_categories.id')
                ->leftJoin('users', 'posts.author_id', '=', 'users.id')
                ->where('posts.village_id', $record->id)
                ->where('posts.slug', $slug)
                ->where('posts.status', 'published')
                ->first(['posts.*', 'content_categories.name as category_name', 'content_categories.slug as category_slug', 'users.name as author_name']);

            abort_unless($post, 404, 'Artikel tidak ditemukan.');

            $postPayload = (array) $post;
            $postPayload['body'] = $this->site->contentHtml($postPayload['body'] ?? null);

            return [
                'post' => $postPayload,
                'sidebar' => [
                    'categories' => DB::table('content_categories')
                        ->where('village_id', $record->id)
                        ->orderBy('name')
                        ->get(['name', 'slug'])
                        ->map(fn (object $category): array => (array) $category)
                        ->all(),
                    'latest' => DB::table('posts')
                        ->where('village_id', $record->id)
                        ->where('status', 'published')
                        ->where('id', '!=', $post->id)
                        ->orderByDesc('published_at')
                        ->limit(5)
                        ->get(['title', 'slug', 'featured_image_url', 'published_at'])
                        ->map(fn (object $item): array => (array) $item)
                        ->all(),
                    'popular' => DB::table('posts')
                        ->where('village_id', $record->id)
                        ->where('status', 'published')
                        ->where('id', '!=', $post->id)
                        ->orderByDesc('view_count')
                        ->limit(5)
                        ->get(['title', 'slug', 'view_count'])
                        ->map(fn (object $item): array => (array) $item)
                        ->all(),
                ],
            ];
        }, ['seconds' => 30]);

        $payload['post']['view_count'] = (int) DB::table('posts')
            ->where('id', $payload['post']['id'])
            ->value('view_count');
        $payload['sidebar']['popular'] = DB::table('posts')
            ->where('village_id', $record->id)
            ->where('status', 'published')
            ->where('id', '!=', $payload['post']['id'])
            ->orderByDesc('view_count')
            ->limit(5)
            ->get(['title', 'slug', 'view_count'])
            ->map(fn (object $item): array => (array) $item)
            ->all();

        return response()->json(['data' => $payload]);
    }

    public function recordView(Request $request, string $village, string $slug): JsonResponse
    {
        $record = $this->village($village);
        $data = $request->validate([
            'visitor_id' => ['nullable', 'string', 'max:255'],
        ]);
        $post = DB::table('posts')
            ->where('village_id', $record->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first(['id', 'view_count']);

        abort_unless($post, 404, 'Artikel tidak ditemukan.');

        $identity = $data['visitor_id'] ?? implode('|', [$request->ip(), $request->userAgent()]);
        $visitorHash = hash('sha256', "{$record->id}|{$post->id}|".now()->toDateString()."|{$identity}");
        $isNewView = DB::table('post_view_identities')->insertOrIgnore([
            'village_id' => $record->id,
            'post_id' => $post->id,
            'view_date' => now()->toDateString(),
            'visitor_hash' => $visitorHash,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;

        if ($isNewView) {
            DB::table('posts')->where('id', $post->id)->increment('view_count');
        }

        return response()->json([
            'message' => $isNewView ? 'View artikel berhasil dicatat.' : 'View artikel sudah tercatat hari ini.',
            'data' => [
                'counted' => $isNewView,
                'view_count' => (int) DB::table('posts')->where('id', $post->id)->value('view_count'),
            ],
        ], $isNewView ? 201 : 200);
    }

    private function village(string $village): object
    {
        $record = DB::table('villages')->where(fn ($query) => $query->where('id', ctype_digit($village) ? (int) $village : 0)->orWhere('slug', $village))->first();
        abort_unless($record, 404, 'Desa tidak ditemukan.');

        return $record;
    }
}
