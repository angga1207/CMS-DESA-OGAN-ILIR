<?php

use App\Support\CurrentVillage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    public int $villageId = 1;
    public string $period = '30';
    public string $groupBy = 'day';
    public array $village = [];
    public array $summary = [];
    public array $rows = [];
    public array $chart = [];
    public bool $showDeveloperDetails = false;

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['developer', 'admin_desa'], true), 403);

        $this->villageId = CurrentVillage::id();
        $this->showDeveloperDetails = auth()->user()?->role === 'developer';
        $this->loadData();
    }

    public function updatedPeriod(): void
    {
        $this->period = in_array($this->period, ['7', '30', '90', 'year'], true) ? $this->period : '30';
        $this->loadData();
    }

    public function updatedGroupBy(): void
    {
        $this->groupBy = in_array($this->groupBy, ['day', 'week', 'month'], true) ? $this->groupBy : 'day';
        $this->loadData();
    }

    public function download(): StreamedResponse
    {
        $this->loadData();
        $villageSlug = str($this->village['slug'] ?? 'desa')->slug()->toString();
        $filename = 'statistik-pengunjung-'.$villageSlug.'-'.$this->period.'-'.$this->groupBy.'.csv';

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Periode', 'Tanggal Mulai', 'Tanggal Selesai', 'Pengunjung Unik', 'Total Kunjungan']);

            foreach ($this->rows as $row) {
                fputcsv($output, [
                    $row['label'],
                    $row['start_date'],
                    $row['end_date'],
                    $row['unique_visitors'],
                    $row['total_visits'],
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function loadData(): void
    {
        $this->village = (array) DB::table('villages')->where('id', $this->villageId)->first();
        [$start, $end] = $this->dateRange();

        $dailyStats = DB::table('village_visitor_daily_stats')
            ->where('village_id', $this->villageId)
            ->whereBetween('visit_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('visit_date')
            ->get()
            ->keyBy(fn (object $row): string => Carbon::parse($row->visit_date)->toDateString());

        $rows = $this->aggregateRows($dailyStats, $start, $end);

        $this->summary = [
            'unique_visitors' => (int) $dailyStats->sum('unique_visitors'),
            'total_visits' => (int) $dailyStats->sum('total_visits'),
            'average_visits' => $dailyStats->isEmpty() ? 0 : (int) round($dailyStats->sum('total_visits') / max(1, $start->diffInDays($end) + 1)),
            'active_days' => $dailyStats->filter(fn (object $row): bool => (int) $row->total_visits > 0)->count(),
            'period_items' => $rows->count(),
        ];
        $this->chart = [
            'labels' => $rows->pluck('label')->all(),
            'unique' => $rows->pluck('unique_visitors')->all(),
            'visits' => $rows->pluck('total_visits')->all(),
        ];
        $this->rows = $rows->reverse()->values()->all();
    }

    private function dateRange(): array
    {
        $end = now()->startOfDay();
        $start = match ($this->period) {
            '7' => $end->copy()->subDays(6),
            '90' => $end->copy()->subDays(89),
            'year' => $end->copy()->startOfYear(),
            default => $end->copy()->subDays(29),
        };

        return [$start, $end];
    }

    private function aggregateRows(Collection $dailyStats, Carbon $start, Carbon $end): Collection
    {
        $rows = collect();
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $bucketStart = $cursor->copy();
            $bucketEnd = match ($this->groupBy) {
                'week' => $cursor->copy()->endOfWeek()->min($end),
                'month' => $cursor->copy()->endOfMonth()->min($end),
                default => $cursor->copy(),
            };

            $uniqueVisitors = 0;
            $totalVisits = 0;
            $day = $bucketStart->copy();

            while ($day->lte($bucketEnd)) {
                $stat = $dailyStats->get($day->toDateString());
                $uniqueVisitors += (int) ($stat?->unique_visitors ?? 0);
                $totalVisits += (int) ($stat?->total_visits ?? 0);
                $day->addDay();
            }

            $rows->push([
                'label' => $this->bucketLabel($bucketStart, $bucketEnd),
                'start_date' => $bucketStart->toDateString(),
                'end_date' => $bucketEnd->toDateString(),
                'unique_visitors' => $uniqueVisitors,
                'total_visits' => $totalVisits,
            ]);

            $cursor = $bucketEnd->copy()->addDay();
        }

        return $rows;
    }

    private function bucketLabel(Carbon $start, Carbon $end): string
    {
        if ($this->groupBy === 'month') {
            return $start->translatedFormat('M Y');
        }

        if ($this->groupBy === 'week') {
            return $start->isSameDay($end)
                ? $start->translatedFormat('d M')
                : $start->translatedFormat('d M').' - '.$end->translatedFormat('d M');
        }

        return $start->translatedFormat('d M');
    }
};
?>

<div class="space-y-5" data-visitor-statistics-chart-payload='@json($chart)'>
    <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h2 class="font-black">{{ $village['name'] ?? 'Desa aktif' }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $village['website_url'] ?? 'URL website belum diatur.' }}</p>
            </div>
            <div class="grid w-full gap-3 sm:grid-cols-[minmax(0,180px)_minmax(0,180px)_auto] xl:w-auto">
                <x-admin.select label="Periode" model="period" :options="['7' => '7 hari', '30' => '30 hari', '90' => '90 hari', 'year' => 'Tahun ini']" />
                <x-admin.select label="Penyajian" model="groupBy" :options="['day' => 'Harian', 'week' => 'Mingguan', 'month' => 'Bulanan']" />
                <button type="button" wire:click="download" class="inline-flex min-h-11 items-center justify-center gap-2 self-end rounded-lg bg-zinc-950 px-4 text-sm font-black text-white transition hover:bg-zinc-800">
                    <i class="fa-solid fa-download"></i>
                    Download CSV
                </button>
            </div>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Pengunjung Unik', $summary['unique_visitors'] ?? 0, 'fa-users'],
            ['Total Kunjungan', $summary['total_visits'] ?? 0, 'fa-arrow-pointer'],
            ['Rata-rata Harian', $summary['average_visits'] ?? 0, 'fa-chart-line'],
            ['Hari Aktif', $summary['active_days'] ?? 0, 'fa-calendar-days'],
        ] as [$label, $value, $icon])
            <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-bold text-zinc-500">{{ $label }}</div>
                    <i class="fa-solid {{ $icon }} text-emerald-700"></i>
                </div>
                <div class="mt-4 text-3xl font-black">{{ number_format($value, 0, ',', '.') }}</div>
            </div>
        @endforeach
    </div>

    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-zinc-200 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-black">Chart Kunjungan</h2>
                <p class="text-sm text-zinc-500">Grafik mengikuti periode dan penyajian data yang dipilih.</p>
            </div>
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">{{ number_format($summary['period_items'] ?? 0, 0, ',', '.') }} titik data</span>
        </div>
        <div wire:ignore class="px-3 pb-3 pt-4 sm:px-5">
            <div id="visitorStatisticsChart" class="min-h-[340px]" aria-label="Chart statistik pengunjung"></div>
        </div>
    </section>

    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
        <div class="border-b border-zinc-200 p-5">
            <h2 class="font-black">Data {{ ['day' => 'Harian', 'week' => 'Mingguan', 'month' => 'Bulanan'][$groupBy] ?? 'Harian' }}</h2>
            <p class="text-sm text-zinc-500">Data diambil dari rekap harian yang sudah tersimpan, lalu diagregasi sesuai pilihan penyajian.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="px-5 py-3">Periode</th>
                        <th class="px-5 py-3 text-right">Pengunjung Unik</th>
                        <th class="px-5 py-3 text-right">Total Kunjungan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @forelse($rows as $row)
                        <tr>
                            <td class="px-5 py-4">
                                <div class="font-semibold">{{ $row['label'] }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ \Illuminate\Support\Carbon::parse($row['start_date'])->translatedFormat('d M Y') }}
                                    @if($row['start_date'] !== $row['end_date'])
                                        - {{ \Illuminate\Support\Carbon::parse($row['end_date'])->translatedFormat('d M Y') }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4 text-right font-black">{{ number_format($row['unique_visitors'], 0, ',', '.') }}</td>
                            <td class="px-5 py-4 text-right font-black">{{ number_format($row['total_visits'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-12 text-center text-zinc-500">Belum ada statistik pengunjung pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($showDeveloperDetails)
        <section class="rounded-lg border border-emerald-200 bg-emerald-50 p-5">
            <h2 class="font-black text-emerald-950">Endpoint Pencatatan</h2>
            <code class="mt-3 block overflow-x-auto rounded-md bg-zinc-950 p-3 text-xs text-white">{{ url('/api/villages/'.($village['slug'] ?? $villageId).'/visitors') }}</code>
            <p class="mt-3 text-sm text-emerald-900">Gunakan metode POST dari proxy server frontend. Kirim <code>visitor_id</code> yang stabil dan <code>event_id</code> unik agar hitungan pengunjung serta pageview tetap akurat. Header <code>X-Village-Analytics-Key</code> dapat digunakan untuk integrasi eksternal tepercaya.</p>
        </section>
    @endif
</div>
