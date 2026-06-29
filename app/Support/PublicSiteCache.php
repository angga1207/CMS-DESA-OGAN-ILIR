<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\PublicVillageSite;
use Illuminate\Support\Facades\Cache;

final class PublicSiteCache
{
    public static function forget(int $villageId): void
    {
        app(PublicVillageSite::class)->forget($villageId);
        Cache::forget("public-widgets:v4:village:{$villageId}");
    }
}
