<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PublicVillageSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class VillagePublicSiteController extends Controller
{
    public function __construct(
        private readonly PublicVillageSite $site,
    ) {}

    public function show(string $village): JsonResponse
    {
        $record = DB::table('villages')
            ->where(fn ($query) => $query->where('id', ctype_digit($village) ? (int) $village : 0)->orWhere('slug', $village))
            ->first();

        abort_unless($record, 404, 'Desa tidak ditemukan.');

        return response()->json(['data' => $this->site->get($record)]);
    }
}
