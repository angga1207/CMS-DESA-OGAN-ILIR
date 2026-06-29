<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SidesiClient;
use App\Support\ExternalDataCache;
use App\Support\SidesiStatisticNormalizer;
use App\Support\VillageFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class VillageStatisticController extends Controller
{
    public function __construct(
        private readonly SidesiClient $sidesi,
    ) {}

    public function show(string $village): JsonResponse
    {
        $villageRecord = DB::table('villages')
            ->where(fn ($query) => $query->where('id', ctype_digit($village) ? (int) $village : 0)->orWhere('slug', $village))
            ->first();

        abort_unless($villageRecord, 404, 'Desa tidak ditemukan.');
        abort_unless(VillageFeatures::enabled((int) $villageRecord->id, 'statistics'), 404, 'Fitur Statistik tidak aktif.');
        abort_unless($villageRecord->sidesi_village_id, 422, 'ID Desa SIDESI belum dikonfigurasi.');

        try {
            $payload = ExternalDataCache::remember(
                "sidesi:statistics-raw:{$villageRecord->sidesi_village_id}",
                fn (): array => [
                    'population' => $this->sidesi->populationStatistics($villageRecord->sidesi_village_id),
                    'occupations' => $this->sidesi->occupationStatistics($villageRecord->sidesi_village_id),
                    'education' => $this->sidesi->educationStatistics($villageRecord->sidesi_village_id),
                    'ages' => $this->sidesi->ageStatistics($villageRecord->sidesi_village_id),
                ],
            );

            return response()->json([
                'source' => 'SIDESI Ogan Ilir',
                'sidesi_village_id' => $villageRecord->sidesi_village_id,
                'data' => [
                    'population' => SidesiStatisticNormalizer::population($payload['population']),
                    'occupations' => SidesiStatisticNormalizer::distribution($payload['occupations']),
                    'education' => SidesiStatisticNormalizer::distribution($payload['education']),
                    'ages' => SidesiStatisticNormalizer::distribution($payload['ages']),
                ],
                'raw' => $payload,
            ]);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Data Statistik dari SIDESI sedang tidak tersedia.',
            ], 502);
        }
    }
}
