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

final class VillageMapController extends Controller
{
    public function __construct(
        private readonly SidesiClient $sidesi,
    ) {}

    public function categories(string $village): JsonResponse
    {
        $villageRecord = $this->findVillage($village);

        return $this->fromSidesi(
            fn (): array => ExternalDataCache::remember('sidesi:map:categories', fn (): array => [
                'facility' => [
                    'label' => 'Fasilitas Umum',
                    'subcategories' => $this->sidesi->facilityCategories(),
                ],
                'assistance' => [
                    'label' => 'Bantuan',
                    'subcategories' => $this->sidesi->assistanceCategories(),
                ],
            ], 3600, 86400),
            $villageRecord,
        );
    }

    public function facilities(Request $request, string $village): JsonResponse
    {
        $villageRecord = $this->findVillage($village);
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'min:1'],
        ]);

        return $this->fromSidesi(
            fn (): array => ExternalDataCache::remember(
                "sidesi:map:facilities:{$villageRecord->sidesi_village_id}:{$data['category_id']}",
                fn (): array => $this->sidesi->facilities($villageRecord->sidesi_village_id, (int) $data['category_id']),
            ),
            $villageRecord,
        );
    }

    public function facilityDetail(string $village, int $listing): JsonResponse
    {
        $villageRecord = $this->findVillage($village);

        return $this->fromSidesi(
            fn (): array => ExternalDataCache::remember(
                "sidesi:map:facility-detail:{$listing}",
                fn (): array => $this->sidesi->facilityDetail($listing),
            ),
            $villageRecord,
        );
    }

    public function assistance(Request $request, string $village): JsonResponse
    {
        $villageRecord = $this->findVillage($village);
        $data = $request->validate([
            'assistance_id' => ['required', 'integer', 'min:1'],
        ]);

        return $this->fromSidesi(
            fn (): array => ExternalDataCache::remember(
                "sidesi:map:assistance:{$villageRecord->sidesi_village_id}:{$data['assistance_id']}",
                fn (): array => $this->sidesi->assistanceRecipients($villageRecord->sidesi_village_id, (int) $data['assistance_id']),
            ),
            $villageRecord,
        );
    }

    private function findVillage(string $village): object
    {
        $villageRecord = DB::table('villages')
            ->where(fn ($query) => $query->where('id', ctype_digit($village) ? (int) $village : 0)->orWhere('slug', $village))
            ->first();

        abort_unless($villageRecord, 404, 'Desa tidak ditemukan.');
        abort_unless(VillageFeatures::enabled((int) $villageRecord->id, 'maps'), 404, 'Fitur Peta Sebaran tidak aktif.');
        abort_unless($villageRecord->sidesi_village_id, 422, 'ID Desa SIDESI belum dikonfigurasi.');

        return $villageRecord;
    }

    private function fromSidesi(callable $callback, object $village): JsonResponse
    {
        try {
            return response()->json([
                'source' => 'SIDESI Ogan Ilir',
                'sidesi_village_id' => $village->sidesi_village_id,
                'data' => $callback(),
            ]);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Data Peta Sebaran dari SIDESI sedang tidak tersedia.',
            ], 502);
        }
    }
}
