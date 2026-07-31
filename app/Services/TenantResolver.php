<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class TenantResolver
{
    public function resolve(string $hostname): ?object
    {
        $hostname = $this->normalizeHostname($hostname);

        if ($hostname === null) {
            return null;
        }

        $data = Cache::remember(
            $this->cacheKey($hostname),
            now()->addMinutes(10),
            fn (): ?array => ($village = DB::table('villages')
                ->where('public_hostname', $hostname)
                ->first(['id', 'slug', 'name', 'public_hostname']))
                ? (array) $village
                : null,
        );

        return $data ? (object) $data : null;
    }

    public function normalizeHostname(string $hostname): ?string
    {
        $hostname = trim(explode(',', $hostname)[0]);
        $hostname = preg_replace('~^https?://~i', '', $hostname);
        $hostname = strtolower(explode('/', $hostname)[0]);
        $hostname = preg_replace('/:\d+$/', '', $hostname);
        $hostname = preg_replace('/^www\./', '', rtrim($hostname, '.'));

        if (! is_string($hostname)
            || $hostname === ''
            || strlen($hostname) > 253
            || ! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $hostname)) {
            return null;
        }

        return $hostname;
    }

    public function forget(?string $hostname): void
    {
        if ($hostname = $this->normalizeHostname((string) $hostname)) {
            Cache::forget($this->cacheKey($hostname));
        }
    }

    private function cacheKey(string $hostname): string
    {
        return 'public-tenant-host:'.hash('sha256', $hostname);
    }
}
