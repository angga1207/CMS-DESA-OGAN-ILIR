<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class ExternalDataCache
{
    public static function remember(
        string $key,
        callable $callback,
        ?int $freshSeconds = null,
        ?int $staleSeconds = null,
    ): mixed {
        $fresh = max($freshSeconds ?? (int) config('public-site.external_cache_fresh'), 1);
        $stale = max($staleSeconds ?? (int) config('public-site.external_cache_stale'), $fresh + 1);

        return Cache::flexible(
            "external-data:v1:{$key}",
            [$fresh, $stale],
            $callback,
            ['seconds' => min($stale, 60)],
        );
    }
}
