<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SidesiClient;
use App\Support\ExternalDataCache;
use App\Support\VillageFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class VillageBudgetController extends Controller
{
    public function __construct(
        private readonly SidesiClient $sidesi,
    ) {}

    public function show(Request $request, string $village): JsonResponse
    {
        $villageRecord = DB::table('villages')
            ->where(fn ($query) => $query->where('id', ctype_digit($village) ? (int) $village : 0)->orWhere('slug', $village))
            ->first();

        abort_unless($villageRecord, 404, 'Desa tidak ditemukan.');
        abort_unless(VillageFeatures::enabled((int) $villageRecord->id, 'budgets'), 404, 'Fitur Anggaran tidak aktif.');
        abort_unless($villageRecord->sidesi_village_id, 422, 'ID Desa SIDESI belum dikonfigurasi.');

        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);
        $year = (int) ($data['year'] ?? now()->year);

        try {
            return response()->json([
                'source' => 'SIDESI Ogan Ilir',
                'year' => $year,
                'sidesi_village_id' => $villageRecord->sidesi_village_id,
                'data' => ExternalDataCache::remember(
                    "sidesi:budget:{$villageRecord->sidesi_village_id}:{$year}",
                    fn (): array => $this->sidesi->villageBudget($villageRecord->sidesi_village_id, $year),
                    300,
                    3600,
                ),
            ]);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Data Anggaran dari SIDESI sedang tidak tersedia.',
            ], 502);
        }
    }
}
