<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SidesiClient;
use App\Support\ExternalDataCache;
use App\Support\VillageFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class VillageOfficialController extends Controller
{
    public function __construct(
        private readonly SidesiClient $sidesi,
    ) {}

    public function today(string $village): JsonResponse
    {
        $villageRecord = $this->findVillage($village);

        try {
            return response()->json([
                'source' => 'SIDESI Ogan Ilir',
                'date' => now()->toDateString(),
                'sidesi_village_id' => $villageRecord->sidesi_village_id,
                'data' => ExternalDataCache::remember(
                    "sidesi:officials:{$villageRecord->sidesi_village_id}:".now()->toDateString(),
                    fn (): array => $this->sidesi->todayAttendance($villageRecord->sidesi_village_id),
                    60,
                    600,
                ),
            ]);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Data absensi Perangkat Desa dari SIDESI sedang tidak tersedia.',
            ], 502);
        }
    }

    public function photo(Request $request, string $village): Response|JsonResponse
    {
        $this->findVillage($village);
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        try {
            $photo = $this->sidesi->employeePhoto($data['url']);

            return response($photo->body(), 200, [
                'Content-Type' => $photo->header('Content-Type') ?: 'image/png',
                'Cache-Control' => 'public, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Foto pegawai SIDESI sedang tidak tersedia.',
            ], 502);
        }
    }

    private function findVillage(string $village): object
    {
        $villageRecord = DB::table('villages')
            ->where(fn ($query) => $query->where('id', ctype_digit($village) ? (int) $village : 0)->orWhere('slug', $village))
            ->first();

        abort_unless($villageRecord, 404, 'Desa tidak ditemukan.');
        abort_unless(VillageFeatures::enabled((int) $villageRecord->id, 'officials'), 404, 'Fitur Perangkat Desa tidak aktif.');
        abort_unless($villageRecord->sidesi_village_id, 422, 'ID Desa SIDESI belum dikonfigurasi.');

        return $villageRecord;
    }
}
