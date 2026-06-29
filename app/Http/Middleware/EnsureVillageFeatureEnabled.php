<?php

namespace App\Http\Middleware;

use App\Support\CurrentVillage;
use App\Support\VillageFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureVillageFeatureEnabled
{
    public function handle(Request $request, Closure $next, ?string $feature = null): Response
    {
        $feature ??= VillageFeatures::forModule((string) $request->route('module'))
            ?? VillageFeatures::forReference((string) $request->route('reference'));

        abort_if(
            $feature && ! VillageFeatures::enabled(CurrentVillage::id(), $feature, $request->user()),
            403,
            'Fitur ini tidak diaktifkan untuk desa Anda.',
        );

        return $next($request);
    }
}
