<?php

namespace App\Http\Controllers;

use App\Support\VillageFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class VillageVisitorController extends Controller
{
    public function show(string $village): JsonResponse
    {
        $villageRecord = $this->findVillage($village);

        return response()->json([
            'data' => [
                'id' => $villageRecord->id,
                'name' => $villageRecord->name,
                'slug' => $villageRecord->slug,
                'district' => $villageRecord->district,
                'regency' => $villageRecord->regency,
                'province' => $villageRecord->province,
                'address' => $villageRecord->address,
                'phone' => $villageRecord->phone,
                'email' => $villageRecord->email,
                'website_url' => $villageRecord->website_url,
                'api_endpoint_url' => $villageRecord->api_endpoint_url,
                'latitude' => $villageRecord->latitude,
                'longitude' => $villageRecord->longitude,
                'logo_url' => $villageRecord->logo_url,
                'favicon_url' => $villageRecord->favicon_url,
                'welcome_message' => $villageRecord->welcome_message,
                'description' => $villageRecord->description,
                'vision' => $villageRecord->vision,
                'mission' => $villageRecord->mission,
                'features' => VillageFeatures::enabledKeys((int) $villageRecord->id),
                'widgets_url' => route('api.villages.widgets.index', $villageRecord->slug),
                'officials_today_url' => route('api.villages.officials.today', $villageRecord->slug),
                'budget_url' => route('api.villages.budget.show', $villageRecord->slug),
                'statistics_url' => route('api.villages.statistics.show', $villageRecord->slug),
                'map_urls' => [
                    'categories' => route('api.villages.map.categories', $villageRecord->slug),
                    'facilities' => route('api.villages.map.facilities', $villageRecord->slug),
                    'facility_detail' => str_replace(
                        '__LISTING_ID__',
                        '{listing_id}',
                        route('api.villages.map.facilities.show', [$villageRecord->slug, '__LISTING_ID__']),
                    ),
                    'assistance' => route('api.villages.map.assistance', $villageRecord->slug),
                ],
            ],
        ]);
    }

    public function store(Request $request, string $village): JsonResponse
    {
        $villageRecord = $this->findVillage($village);

        $providedKey = (string) $request->header('X-Village-Analytics-Key');
        if ($providedKey !== '') {
            abort_unless($villageRecord->analytics_key && hash_equals($villageRecord->analytics_key, $providedKey), 401, 'Kunci analytics tidak valid.');
        }

        $data = $request->validate([
            'event_id' => ['nullable', 'uuid'],
            'visitor_id' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'url', 'max:2048'],
            'visited_at' => ['nullable', 'date'],
            'event' => ['nullable', Rule::in(['pageview'])],
        ]);

        $visitDate = isset($data['visited_at'])
            ? Carbon::parse($data['visited_at'])->timezone(config('app.timezone'))->toDateString()
            : now()->toDateString();
        $identity = $data['visitor_id'] ?: implode('|', [$request->ip(), $request->userAgent()]);
        $visitorHash = hash('sha256', $villageRecord->id.'|'.$visitDate.'|'.$identity);
        $eventId = $data['event_id'] ?? null;

        if ($eventId && ! Cache::add("visitor-event:{$villageRecord->id}:{$eventId}", true, now()->addDay())) {
            $existing = DB::table('village_visitor_daily_stats')
                ->where('village_id', $villageRecord->id)
                ->where('visit_date', $visitDate)
                ->first();

            return response()->json([
                'message' => 'Kunjungan sudah pernah dicatat.',
                'data' => [
                    'date' => $visitDate,
                    'unique_visitors' => (int) ($existing->unique_visitors ?? 0),
                    'total_visits' => (int) ($existing->total_visits ?? 0),
                ],
            ]);
        }

        $stat = DB::transaction(function () use ($villageRecord, $visitDate, $visitorHash): object {
            $isUnique = DB::table('village_visitor_identities')->insertOrIgnore([
                'village_id' => $villageRecord->id,
                'visit_date' => $visitDate,
                'visitor_hash' => $visitorHash,
                'created_at' => now(),
                'updated_at' => now(),
            ]) === 1;

            DB::table('village_visitor_daily_stats')->insertOrIgnore([
                'village_id' => $villageRecord->id,
                'visit_date' => $visitDate,
                'total_visits' => 0,
                'unique_visitors' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $stat = DB::table('village_visitor_daily_stats')
                ->where('village_id', $villageRecord->id)
                ->where('visit_date', $visitDate)
                ->lockForUpdate()
                ->first();

            DB::table('village_visitor_daily_stats')->where('id', $stat->id)->update([
                'total_visits' => $stat->total_visits + 1,
                'unique_visitors' => $stat->unique_visitors + ($isUnique ? 1 : 0),
                'updated_at' => now(),
            ]);

            return DB::table('village_visitor_daily_stats')
                ->where('village_id', $villageRecord->id)
                ->where('visit_date', $visitDate)
                ->first();
        });

        return response()->json([
            'message' => 'Kunjungan berhasil dicatat.',
            'data' => [
                'date' => $stat->visit_date,
                'unique_visitors' => $stat->unique_visitors,
                'total_visits' => $stat->total_visits,
            ],
        ], 201);
    }

    private function findVillage(string $village): object
    {
        $villageRecord = DB::table('villages')
            ->where(fn ($query) => $query->where('id', ctype_digit($village) ? (int) $village : 0)->orWhere('slug', $village))
            ->first();

        abort_unless($villageRecord, 404, 'Desa tidak ditemukan.');

        return $villageRecord;
    }
}
