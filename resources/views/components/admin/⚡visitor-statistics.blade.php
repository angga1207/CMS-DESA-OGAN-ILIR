<?php

use App\Support\CurrentVillage;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public int $villageId = 1;
    public int $period = 30;
    public array $village = [];
    public array $summary = [];
    public array $rows = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'developer', 403);

        $this->villageId = CurrentVillage::id();
        $this->loadData();
    }

    public function updatedPeriod(): void
    {
        $this->period = in_array($this->period, [7, 30, 90], true) ? $this->period : 30;
        $this->loadData();
    }

    private function loadData(): void
    {
        $this->village = (array) DB::table('villages')->where('id', $this->villageId)->first();
        $stats = DB::table('village_visitor_daily_stats')
            ->where('village_id', $this->villageId)
            ->whereDate('visit_date', '>=', now()->subDays($this->period - 1)->toDateString())
            ->orderByDesc('visit_date')
            ->get();

        $this->summary = [
            'unique_visitors' => (int) $stats->sum('unique_visitors'),
            'total_visits' => (int) $stats->sum('total_visits'),
            'average_visits' => $stats->isEmpty() ? 0 : (int) round($stats->avg('total_visits')),
            'active_days' => $stats->count(),
        ];
        $this->rows = $stats->map(fn (object $row): array => (array) $row)->all();
    }
};
?>

<div class="space-y-5">
    <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-black">{{ $village['name'] ?? 'Desa aktif' }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $village['website_url'] ?? 'URL website belum diatur.' }}</p>
            </div>
            <div class="w-full sm:w-48">
                <x-admin.select label="Periode" model="period" :options="[7 => '7 hari', 30 => '30 hari', 90 => '90 hari']" />
            </div>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Pengunjung Unik', $summary['unique_visitors'] ?? 0, 'fa-users'],
            ['Total Kunjungan', $summary['total_visits'] ?? 0, 'fa-arrow-pointer'],
            ['Rata-rata Harian', $summary['average_visits'] ?? 0, 'fa-chart-line'],
            ['Hari Terekam', $summary['active_days'] ?? 0, 'fa-calendar-days'],
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
        <div class="border-b border-zinc-200 p-5">
            <h2 class="font-black">Data Harian</h2>
            <p class="text-sm text-zinc-500">Pengunjung unik dihitung satu kali per identitas pengunjung setiap hari.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3 text-right">Pengunjung Unik</th>
                        <th class="px-5 py-3 text-right">Total Kunjungan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @forelse($rows as $row)
                        <tr>
                            <td class="px-5 py-4 font-semibold">{{ \Illuminate\Support\Carbon::parse($row['visit_date'])->translatedFormat('d F Y') }}</td>
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

    <section class="rounded-lg border border-emerald-200 bg-emerald-50 p-5">
        <h2 class="font-black text-emerald-950">Endpoint Pencatatan</h2>
        <code class="mt-3 block overflow-x-auto rounded-md bg-zinc-950 p-3 text-xs text-white">{{ url('/api/villages/'.($village['slug'] ?? $villageId).'/visitors') }}</code>
        <p class="mt-3 text-sm text-emerald-900">Gunakan metode POST dari proxy server frontend. Kirim <code>visitor_id</code> yang stabil dan <code>event_id</code> unik agar hitungan pengunjung serta pageview tetap akurat. Header <code>X-Village-Analytics-Key</code> dapat digunakan untuk integrasi eksternal tepercaya.</p>
    </section>
</div>
