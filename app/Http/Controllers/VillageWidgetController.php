<?php

namespace App\Http\Controllers;

use App\Services\PublicVillageSite;
use App\Services\SidesiClient;
use App\Support\ExternalDataCache;
use App\Support\SidesiStatisticNormalizer;
use App\Support\VillageFeatures;
use App\Support\WidgetCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class VillageWidgetController extends Controller
{
    public function __construct(
        private readonly SidesiClient $sidesi,
        private readonly PublicVillageSite $site,
    ) {}

    public function index(string $village): JsonResponse
    {
        $villageRecord = DB::table('villages')
            ->where(fn ($query) => $query->where('id', ctype_digit($village) ? (int) $village : 0)->orWhere('slug', $village))
            ->first();

        abort_unless($villageRecord, 404, 'Desa tidak ditemukan.');

        if (! VillageFeatures::enabled((int) $villageRecord->id, 'widgets')) {
            return response()->json(['data' => []]);
        }

        $definitions = Cache::flexible(
            "public-widgets:v4:village:{$villageRecord->id}:revision:{$this->site->revision((int) $villageRecord->id)}",
            [
                max((int) config('public-site.cache_ttl'), 1),
                max((int) config('public-site.cache_stale_ttl'), 2),
            ],
            fn () => DB::table('village_widgets')
                ->where('village_id', $villageRecord->id)
                ->where('is_active', true)
                ->whereIn('widget_type', array_keys(WidgetCatalog::all()))
                ->orderBy('placement')
                ->orderBy('sort_order')
                ->get()
                ->map(function (object $widget): array {
                    $definition = WidgetCatalog::get($widget->widget_type);
                    $config = json_decode($widget->config ?: '{}', true) ?: [];

                    return [
                        'id' => $widget->id,
                        'type' => $widget->widget_type,
                        'title' => $widget->title,
                        'placement' => WidgetCatalog::normalizePlacement($widget->widget_type, $widget->placement),
                        'sort_order' => $widget->sort_order,
                        'icon' => $definition['icon'] ?? 'fa-solid fa-puzzle-piece',
                        'config' => $config,
                    ];
                })
                ->all(),
            ['seconds' => 30],
        );

        $widgets = collect($definitions)
            ->map(function (array $widget) use ($villageRecord): array {
                $widget['data'] = $this->dynamicData($villageRecord, $widget['type'], $widget['config']);

                return $widget;
            })
            ->groupBy('placement')
            ->map(fn ($items): array => $items->values()->all())
            ->all();

        return response()->json(['data' => $widgets]);
    }

    private function dynamicData(object $village, string $type, array $config): ?array
    {
        $villageId = (int) $village->id;

        return match ($type) {
            'visitor_statistics' => $this->visitorStatistics($villageId, (int) ($config['period_days'] ?? 30)),
            'population_summary' => $this->populationSummary($villageId),
            'village_officials' => $this->villageOfficials($village, $config),
            'prayer_schedule' => $this->prayerSchedule($config),
            'weather_information' => $this->weatherInformation($village, $config),
            'village_statistics' => $this->villageStatistics($villageId, $config),
            'village_budget' => $this->villageBudget($villageId, $config),
            'latest_articles' => $this->latestArticles($villageId, (int) ($config['limit'] ?? 5)),
            default => null,
        };
    }

    private function visitorStatistics(int $villageId, int $period): array
    {
        $stats = DB::table('village_visitor_daily_stats')
            ->where('village_id', $villageId)
            ->whereDate('visit_date', '>=', now()->subDays(max($period, 1) - 1)->toDateString())
            ->get();

        return [
            'period_days' => $period,
            'unique_visitors' => (int) $stats->sum('unique_visitors'),
            'total_visits' => (int) $stats->sum('total_visits'),
        ];
    }

    private function populationSummary(int $villageId): ?array
    {
        $sidesiVillageId = DB::table('villages')->where('id', $villageId)->value('sidesi_village_id');

        if (! $sidesiVillageId || ! VillageFeatures::enabled($villageId, 'statistics')) {
            return null;
        }

        try {
            return ExternalDataCache::remember(
                "sidesi:population:{$sidesiVillageId}",
                fn (): array => SidesiStatisticNormalizer::population(
                    $this->sidesi->populationStatistics((string) $sidesiVillageId),
                ),
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return null;
        }
    }

    private function villageOfficials(object $village, array $config): ?array
    {
        $villageId = (int) $village->id;
        $sidesiVillageId = $village->sidesi_village_id;

        if (! $sidesiVillageId || ! VillageFeatures::enabled($villageId, 'officials')) {
            return null;
        }

        try {
            $payload = ExternalDataCache::remember(
                "sidesi:officials:{$sidesiVillageId}:".now()->toDateString(),
                fn (): array => $this->sidesi->todayAttendance((string) $sidesiVillageId),
                60,
                600,
            );
            $records = collect($this->extractOfficialRows($payload))
                ->map(fn (array $row): array => $this->normalizeOfficial($row, $village->slug))
                ->filter(fn (array $row): bool => $row['name'] !== '')
                ->values();
            $showAll = ($config['limit'] ?? 6) === 'all';
            $limit = min(max((int) ($config['limit'] ?? 6), 1), 12);

            return [
                'source' => 'SIDESI Ogan Ilir',
                'date' => now()->toDateString(),
                'total' => $records->count(),
                'present' => $records->filter(fn (array $row): bool => strtolower($row['attendance_status']) === 'hadir')->count(),
                'items' => ($showAll ? $records : $records->take($limit))->all(),
            ];
        } catch (RuntimeException $exception) {
            report($exception);

            return null;
        }
    }

    private function prayerSchedule(array $config): ?array
    {
        try {
            $month = min(max((int) ($config['bulan'] ?? now()->month), 1), 12);
            $year = min(max((int) ($config['tahun'] ?? now()->year), 2000), 2100);
            $province = (string) ($config['provinsi'] ?? 'Sumatera Selatan');
            $regency = (string) ($config['kabkota'] ?? 'Kab. Ogan ILIR');
            $data = ExternalDataCache::remember(
                'prayer:'.md5("{$province}|{$regency}|{$month}|{$year}"),
                function () use ($province, $regency, $month, $year): array {
                    $response = Http::acceptJson()
                        ->asJson()
                        ->timeout(15)
                        ->retry(2, 250, throw: false)
                        ->post('https://equran.id/api/v2/shalat', [
                            'provinsi' => $province,
                            'kabkota' => $regency,
                            'bulan' => $month,
                            'tahun' => $year,
                        ]);

                    if (! $response->successful()) {
                        throw new RuntimeException("eQuran mengembalikan HTTP {$response->status()}.");
                    }

                    $payload = $response->json('data');

                    if (! is_array($payload)) {
                        throw new RuntimeException('Respons jadwal salat eQuran tidak valid.');
                    }

                    return $payload;
                },
                3600,
                86400,
            );

            if (! is_array($data)) {
                throw new RuntimeException('Respons jadwal salat eQuran tidak valid.');
            }

            $schedule = collect(is_array($data['jadwal'] ?? null) ? $data['jadwal'] : []);
            $today = now()->toDateString();
            $selected = $schedule->first(fn (array $row): bool => ($row['tanggal_lengkap'] ?? null) === $today)
                ?? $schedule->firstWhere('tanggal', now()->day)
                ?? $schedule->first();

            return [
                'source' => 'eQuran',
                'location' => trim(($data['kabkota'] ?? ($config['kabkota'] ?? 'Kab. Ogan ILIR')).', '.($data['provinsi'] ?? ($config['provinsi'] ?? 'Sumatera Selatan')), ', '),
                'month' => (int) ($data['bulan'] ?? $month),
                'month_name' => (string) ($data['bulan_nama'] ?? ''),
                'year' => (int) ($data['tahun'] ?? $year),
                'today' => is_array($selected) ? $selected : null,
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function weatherInformation(object $village, array $config): ?array
    {
        try {
            $adm4 = trim((string) ($config['adm4'] ?? ''));

            if ($adm4 === '') {
                return null;
            }

            $payload = ExternalDataCache::remember(
                "weather:{$adm4}",
                function () use ($adm4): array {
                    $response = Http::acceptJson()
                        ->timeout(15)
                        ->retry(2, 250, throw: false)
                        ->get('https://api.bmkg.go.id/publik/prakiraan-cuaca', ['adm4' => $adm4]);

                    if (! $response->successful()) {
                        throw new RuntimeException("BMKG mengembalikan HTTP {$response->status()}.");
                    }

                    $data = $response->json();

                    if (! is_array($data)) {
                        throw new RuntimeException('Respons prakiraan cuaca BMKG tidak valid.');
                    }

                    return $data;
                },
                900,
                3600,
            );

            $forecasts = $this->flattenWeatherRows($payload);
            $limit = min(max((int) ($config['forecast_days'] ?? 3), 1), 7) * 8;

            return [
                'source' => 'BMKG',
                'adm4' => $adm4,
                'village' => (string) ($payload['lokasi']['desa'] ?? $village->name),
                'district' => (string) ($payload['lokasi']['kecamatan'] ?? $village->district ?? ''),
                'current' => $forecasts[0] ?? null,
                'forecasts' => array_slice($forecasts, 0, $limit),
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function villageStatistics(int $villageId, array $config): ?array
    {
        $sidesiVillageId = DB::table('villages')->where('id', $villageId)->value('sidesi_village_id');

        if (! $sidesiVillageId || ! VillageFeatures::enabled($villageId, 'statistics')) {
            return null;
        }

        try {
            $data = ExternalDataCache::remember(
                'sidesi:statistics:'.$sidesiVillageId.':'.md5(json_encode([
                    'show_occupations' => (bool) ($config['show_occupations'] ?? true),
                    'show_education' => (bool) ($config['show_education'] ?? true),
                    'show_ages' => (bool) ($config['show_ages'] ?? true),
                ])),
                function () use ($sidesiVillageId, $config): array {
                    $data = [
                        'population' => SidesiStatisticNormalizer::population(
                            $this->sidesi->populationStatistics((string) $sidesiVillageId),
                        ),
                    ];

                    if ($config['show_occupations'] ?? true) {
                        $data['occupations'] = SidesiStatisticNormalizer::distribution(
                            $this->sidesi->occupationStatistics((string) $sidesiVillageId),
                        );
                    }

                    if ($config['show_education'] ?? true) {
                        $data['education'] = SidesiStatisticNormalizer::distribution(
                            $this->sidesi->educationStatistics((string) $sidesiVillageId),
                        );
                    }

                    if ($config['show_ages'] ?? true) {
                        $data['ages'] = SidesiStatisticNormalizer::distribution(
                            $this->sidesi->ageStatistics((string) $sidesiVillageId),
                        );
                    }

                    return $data;
                },
            );

            return collect($data)
                ->reject(fn (mixed $value, string $key): bool => match ($key) {
                    'occupations' => ! ($config['show_occupations'] ?? true),
                    'education' => ! ($config['show_education'] ?? true),
                    'ages' => ! ($config['show_ages'] ?? true),
                    default => false,
                })
                ->all();
        } catch (RuntimeException $exception) {
            report($exception);

            return null;
        }
    }

    private function villageBudget(int $villageId, array $config): ?array
    {
        $sidesiVillageId = DB::table('villages')->where('id', $villageId)->value('sidesi_village_id');

        if (! $sidesiVillageId || ! VillageFeatures::enabled($villageId, 'budgets')) {
            return null;
        }

        try {
            $year = min(max((int) ($config['year'] ?? now()->year), 2000), 2100);
            $response = ExternalDataCache::remember(
                "sidesi:budget:{$sidesiVillageId}:{$year}",
                fn (): array => $this->sidesi->villageBudget((string) $sidesiVillageId, $year),
                300,
                3600,
            );
            $groups = is_array($response['data'] ?? null) ? $response['data'] : [];
            $data = ['year' => $year];

            foreach ([
                'Pelaksanaan' => 'show_pelaksanaan',
                'Pembelanjaan' => 'show_pembelanjaan',
                'Pendapatan' => 'show_pendapatan',
            ] as $group => $flag) {
                if ($config[$flag] ?? true) {
                    $data[$group] = is_array($groups[$group] ?? null) ? $groups[$group] : [];
                }
            }

            return $data;
        } catch (RuntimeException $exception) {
            report($exception);

            return null;
        }
    }

    private function latestArticles(int $villageId, int $limit): array
    {
        return DB::table('posts')
            ->where('village_id', $villageId)
            ->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->orderByDesc('published_at')
            ->limit(min(max($limit, 1), 10))
            ->get(['title', 'slug', 'excerpt', 'featured_image_url', 'published_at'])
            ->map(fn (object $post): array => (array) $post)
            ->all();
    }

    private function extractOfficialRows(array $payload): array
    {
        if (array_is_list($payload)) {
            return $payload;
        }

        foreach (['data', 'result', 'absensi', 'pegawai', 'perangkat_desa'] as $key) {
            if (! is_array($payload[$key] ?? null)) {
                continue;
            }

            $value = $payload[$key];

            if (array_is_list($value)) {
                return $value;
            }

            $nestedRows = $this->extractOfficialRows($value);

            if ($nestedRows !== []) {
                return $nestedRows;
            }
        }

        return $payload === [] ? [] : [$payload];
    }

    private function normalizeOfficial(array $row, string $villageSlug): array
    {
        $photoUrl = (string) $this->firstValue($row, ['foto_pegawai', 'foto', 'photo_url'], '');

        return [
            'id' => (string) $this->firstValue($row, ['id', 'id_pegawai', 'nik'], ''),
            'name' => (string) $this->firstValue($row, ['nama_lengkap', 'nama', 'name'], ''),
            'position' => (string) $this->firstValue($row, ['jabatan', 'position'], ''),
            'attendance_status' => (string) $this->firstValue($row, ['status_kehadiran', 'status_absensi', 'status'], ''),
            'photo_url' => $photoUrl === '' ? '' : route('api.villages.officials.photo', ['village' => $villageSlug, 'url' => $photoUrl]),
        ];
    }

    private function flattenWeatherRows(array $payload): array
    {
        $rows = [];

        foreach (($payload['data'] ?? []) as $item) {
            foreach (($item['cuaca'] ?? []) as $group) {
                foreach ($group as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $rows[] = [
                        'datetime' => $row['local_datetime'] ?? $row['datetime'] ?? null,
                        'temperature' => isset($row['t']) ? (int) $row['t'] : null,
                        'humidity' => isset($row['hu']) ? (int) $row['hu'] : null,
                        'weather' => $row['weather_desc'] ?? null,
                        'wind_direction' => $row['wd'] ?? null,
                        'wind_speed' => isset($row['ws']) ? (float) $row['ws'] : null,
                        'visibility' => $row['vs_text'] ?? null,
                        'image' => $row['image'] ?? null,
                    ];
                }
            }
        }

        usort($rows, fn (array $a, array $b): int => strcmp((string) ($a['datetime'] ?? ''), (string) ($b['datetime'] ?? '')));

        return $rows;
    }

    private function firstValue(array $row, array $keys, mixed $fallback = null): mixed
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return $fallback;
    }
}
