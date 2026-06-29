<?php

return [
    'cache_ttl' => (int) env('PUBLIC_SITE_CACHE_TTL', 300),
    'cache_stale_ttl' => (int) env('PUBLIC_SITE_CACHE_STALE_TTL', 1800),
    'external_cache_fresh' => (int) env('EXTERNAL_DATA_CACHE_FRESH', 120),
    'external_cache_stale' => (int) env('EXTERNAL_DATA_CACHE_STALE', 1800),
    'article_limit' => (int) env('PUBLIC_SITE_ARTICLE_LIMIT', 12),
];
