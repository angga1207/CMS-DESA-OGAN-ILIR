<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use stdClass;

final class PostRevisionHistory
{
    public const MAX_REVISIONS = 3;

    public static function hasChanged(stdClass $post, array $payload): bool
    {
        foreach (self::trackedColumns() as $column) {
            if ((string) ($post->{$column} ?? '') !== (string) ($payload[$column] ?? '')) {
                return true;
            }
        }

        return false;
    }

    public static function capture(stdClass $post, ?int $revisionAuthorId): void
    {
        DB::table('post_revisions')->insert([
            'post_id' => $post->id,
            'village_id' => $post->village_id,
            'category_id' => $post->category_id,
            'author_id' => $post->author_id,
            'revision_author_id' => $revisionAuthorId,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'featured_image_url' => $post->featured_image_url,
            'status' => $post->status,
            'published_at' => $post->published_at,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::prune((int) $post->id);
    }

    public static function prune(int $postId): void
    {
        $revisionIdsToDelete = DB::table('post_revisions')
            ->where('post_id', $postId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->slice(self::MAX_REVISIONS)
            ->values();

        if ($revisionIdsToDelete->isNotEmpty()) {
            DB::table('post_revisions')->whereIn('id', $revisionIdsToDelete)->delete();
        }
    }

    /**
     * @return array<int, string>
     */
    public static function trackedColumns(): array
    {
        return [
            'category_id',
            'title',
            'slug',
            'excerpt',
            'body',
            'featured_image_url',
            'status',
            'published_at',
        ];
    }
}
