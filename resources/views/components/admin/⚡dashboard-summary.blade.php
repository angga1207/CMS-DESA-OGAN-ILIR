<?php

use App\Support\CurrentVillage;
use App\Support\VillageFeatures;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public array $village = [];
    public array $contentMetrics = [];
    public array $visitorSummary = [];
    public array $visitorChart = [];
    public array $latestPosts = [];
    public int $villageId = 1;
    public bool $articlesEnabled = true;

    public function mount(): void
    {
        $this->villageId = CurrentVillage::id();
        $this->village = (array) DB::table('villages')->where('id', $this->villageId)->first();
        $this->articlesEnabled = VillageFeatures::enabled($this->villageId, 'articles', auth()->user());

        $this->loadContentMetrics();
        $this->loadVisitorStatistics();
        $this->loadLatestPosts();
    }

    private function loadContentMetrics(): void
    {
        $metrics = [
            ['label' => 'Artikel', 'helper' => 'konten tersimpan', 'value' => DB::table('posts')->where('village_id', $this->villageId)->count(), 'href' => route('admin.posts.index'), 'icon' => 'fa-newspaper', 'feature' => 'articles', 'tone' => 'emerald'],
            ['label' => 'Halaman', 'helper' => 'halaman informasi', 'value' => DB::table('pages')->where('village_id', $this->villageId)->count(), 'href' => route('admin.pages.index'), 'icon' => 'fa-file-lines', 'feature' => 'pages', 'tone' => 'sky'],
            ['label' => 'UMKM', 'helper' => 'usaha terdata', 'value' => DB::table('businesses')->where('village_id', $this->villageId)->count(), 'href' => route('admin.module', 'businesses'), 'icon' => 'fa-store', 'feature' => 'businesses', 'tone' => 'amber'],
            ['label' => 'BUMDES', 'helper' => 'unit usaha desa', 'value' => DB::table('bumdes')->where('village_id', $this->villageId)->count(), 'href' => route('admin.module', 'bumdes'), 'icon' => 'fa-building-user', 'feature' => 'bumdes', 'tone' => 'teal'],
            ['label' => 'Pembangunan', 'helper' => 'kegiatan desa', 'value' => DB::table('development_projects')->where('village_id', $this->villageId)->count(), 'href' => route('admin.module', 'projects'), 'icon' => 'fa-person-digging', 'feature' => 'projects', 'tone' => 'violet'],
        ];

        $this->contentMetrics = collect($metrics)
            ->filter(fn (array $metric): bool => VillageFeatures::enabled($this->villageId, $metric['feature'], auth()->user()))
            ->values()
            ->all();
    }

    private function loadVisitorStatistics(): void
    {
        $start = now()->startOfDay()->subDays(29);
        $stats = DB::table('village_visitor_daily_stats')
            ->where('village_id', $this->villageId)
            ->whereDate('visit_date', '>=', $start->toDateString())
            ->orderBy('visit_date')
            ->get()
            ->keyBy(fn (object $row): string => Carbon::parse($row->visit_date)->toDateString());

        $currentSeven = collect(range(0, 6))->sum(function (int $offset) use ($stats): int {
            return (int) ($stats->get(now()->subDays($offset)->toDateString())?->total_visits ?? 0);
        });
        $previousSeven = collect(range(7, 13))->sum(function (int $offset) use ($stats): int {
            return (int) ($stats->get(now()->subDays($offset)->toDateString())?->total_visits ?? 0);
        });
        $trend = $previousSeven > 0
            ? (int) round((($currentSeven - $previousSeven) / $previousSeven) * 100)
            : ($currentSeven > 0 ? 100 : 0);

        $today = $stats->get(now()->toDateString());
        $this->visitorSummary = [
            'today' => (int) ($today?->total_visits ?? 0),
            'unique_30_days' => (int) $stats->sum('unique_visitors'),
            'visits_30_days' => (int) $stats->sum('total_visits'),
            'average_daily' => (int) round($stats->sum('total_visits') / 30),
            'trend' => $trend,
        ];

        $chartDays = collect(range(13, 0))->map(fn (int $offset): Carbon => now()->startOfDay()->subDays($offset));
        $this->visitorChart = [
            'labels' => $chartDays->map(fn (Carbon $date): string => $date->translatedFormat('d M'))->all(),
            'visits' => $chartDays->map(fn (Carbon $date): int => (int) ($stats->get($date->toDateString())?->total_visits ?? 0))->all(),
            'unique' => $chartDays->map(fn (Carbon $date): int => (int) ($stats->get($date->toDateString())?->unique_visitors ?? 0))->all(),
        ];
    }

    private function loadLatestPosts(): void
    {
        if (! $this->articlesEnabled) {
            return;
        }

        $this->latestPosts = DB::table('posts')
            ->leftJoin('content_categories', 'posts.category_id', '=', 'content_categories.id')
            ->where('posts.village_id', $this->villageId)
            ->select('posts.id', 'posts.title', 'posts.status', 'posts.updated_at', 'content_categories.name as category_name')
            ->orderByDesc('posts.updated_at')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }
};
?>

@php
    $toneClasses = [
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-100',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-100',
        'teal' => 'bg-teal-50 text-teal-700 ring-teal-100',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-100',
    ];
    $trend = $visitorSummary['trend'] ?? 0;
@endphp

<div class="space-y-6" data-dashboard-chart-payload='@json($visitorChart)'>
    <section class="relative overflow-hidden rounded-2xl bg-[#073c35] px-6 py-7 text-white shadow-[0_18px_45px_-24px_rgba(7,60,53,0.65)] sm:px-8 sm:py-8">
        <div class="absolute -right-20 -top-28 size-72 rounded-full border-[42px] border-white/5"></div>
        <div class="absolute -bottom-24 right-24 size-48 rounded-full bg-emerald-300/10 blur-2xl"></div>
        <div class="relative flex flex-col gap-7 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-2xl">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-emerald-200/20 bg-white/10 px-3 py-1.5 text-xs font-bold tracking-wide text-emerald-100">
                    <span class="size-2 rounded-full bg-emerald-300 shadow-[0_0_0_5px_rgba(110,231,183,0.12)]"></span>
                    Pusat informasi desa aktif
                </div>
                <h2 class="text-2xl font-black tracking-tight sm:text-3xl">Selamat datang, {{ auth()->user()->name }}</h2>
                <p class="mt-3 max-w-xl text-sm leading-6 text-emerald-50/75 sm:text-base">
                    Pantau jangkauan website dan kelola informasi {{ $village['name'] ?? 'desa' }} dari satu ruang kerja.
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                @if($articlesEnabled)
                    <a href="{{ route('admin.posts.create') }}" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-[#d6f36f] px-4 text-sm font-black text-[#173a2f] transition hover:-translate-y-0.5 hover:bg-[#e2fa8d]">
                        <i class="fa-solid fa-plus"></i> Tulis artikel
                    </a>
                @endif
                @if(! empty($village['website_url']))
                    <a href="{{ $village['website_url'] }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-4 text-sm font-bold text-white transition hover:bg-white/15">
                        Lihat website <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section aria-labelledby="content-overview-title">
        <div class="mb-3 flex items-end justify-between gap-4">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Ikhtisar konten</p>
                <h2 id="content-overview-title" class="mt-1 text-lg font-black text-zinc-950">Data yang dikelola</h2>
            </div>
            <span class="hidden text-xs font-semibold text-zinc-400 sm:block">Diperbarui {{ now()->translatedFormat('d M Y, H.i') }}</span>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($contentMetrics as $metric)
                <a href="{{ $metric['href'] }}" class="group rounded-xl border border-zinc-200/80 bg-white p-5 shadow-[0_8px_28px_-24px_rgba(24,24,27,0.5)] transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-[0_16px_30px_-22px_rgba(5,150,105,0.4)]">
                    <div class="flex items-start justify-between gap-3">
                        <div class="grid size-10 place-items-center rounded-lg ring-1 {{ $toneClasses[$metric['tone']] }}">
                            <i class="fa-solid {{ $metric['icon'] }}"></i>
                        </div>
                        <i class="fa-solid fa-arrow-right -rotate-45 text-xs text-zinc-300 transition group-hover:rotate-0 group-hover:text-emerald-600"></i>
                    </div>
                    <div class="mt-5 flex items-end gap-2">
                        <span class="text-3xl font-black tracking-tight text-zinc-950">{{ number_format($metric['value'], 0, ',', '.') }}</span>
                        <span class="pb-1 text-xs font-semibold text-zinc-400">{{ $metric['helper'] }}</span>
                    </div>
                    <div class="mt-1 text-sm font-bold text-zinc-700">{{ $metric['label'] }}</div>
                </a>
            @endforeach
        </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.65fr)_minmax(280px,0.75fr)]" aria-labelledby="visitor-statistics-title">
        <div class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-[0_12px_40px_-30px_rgba(24,24,27,0.45)]">
            <div class="flex flex-col gap-4 border-b border-zinc-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 id="visitor-statistics-title" class="text-lg font-black text-zinc-950">Denyut kunjungan website</h2>
                        <span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-700">14 hari</span>
                    </div>
                    <p class="mt-1 text-sm text-zinc-500">Perbandingan total kunjungan dan pengunjung unik.</p>
                </div>
                <div class="inline-flex items-center gap-2 text-sm font-bold {{ $trend >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                    <span class="grid size-8 place-items-center rounded-full {{ $trend >= 0 ? 'bg-emerald-50' : 'bg-rose-50' }}">
                        <i class="fa-solid {{ $trend >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }} text-xs"></i>
                    </span>
                    {{ $trend >= 0 ? '+' : '' }}{{ $trend }}% dari 7 hari sebelumnya
                </div>
            </div>
            <div class="px-3 pb-3 pt-4 sm:px-5">
                <div id="dashboardVisitorChart" class="min-h-[300px]" aria-label="Grafik statistik pengunjung website"></div>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
            @foreach([
                ['label' => 'Kunjungan hari ini', 'value' => $visitorSummary['today'] ?? 0, 'icon' => 'fa-sun', 'note' => 'aktivitas sejak pukul 00.00'],
                ['label' => 'Pengunjung unik', 'value' => $visitorSummary['unique_30_days'] ?? 0, 'icon' => 'fa-user-group', 'note' => 'akumulasi 30 hari terakhir'],
                ['label' => 'Total kunjungan', 'value' => $visitorSummary['visits_30_days'] ?? 0, 'icon' => 'fa-arrow-pointer', 'note' => 'akumulasi 30 hari terakhir'],
                ['label' => 'Rata-rata harian', 'value' => $visitorSummary['average_daily'] ?? 0, 'icon' => 'fa-chart-simple', 'note' => 'rata-rata dalam 30 hari'],
            ] as $index => $stat)
                <article class="rounded-xl border {{ $index === 0 ? 'border-[#d6f36f] bg-[#f8fde8]' : 'border-zinc-200/80 bg-white' }} p-4 shadow-[0_8px_28px_-24px_rgba(24,24,27,0.5)]">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold text-zinc-500">{{ $stat['label'] }}</p>
                            <p class="mt-1 text-2xl font-black tracking-tight text-zinc-950">{{ number_format($stat['value'], 0, ',', '.') }}</p>
                        </div>
                        <div class="grid size-10 place-items-center rounded-lg {{ $index === 0 ? 'bg-[#d6f36f] text-[#285246]' : 'bg-zinc-100 text-zinc-600' }}">
                            <i class="fa-solid {{ $stat['icon'] }}"></i>
                        </div>
                    </div>
                    <p class="mt-2 text-[11px] font-medium text-zinc-400">{{ $stat['note'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(280px,0.7fr)]">
        @if($articlesEnabled)
            <div class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-[0_12px_40px_-30px_rgba(24,24,27,0.45)]">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 px-5 py-5 sm:px-6">
                    <div>
                        <h2 class="text-lg font-black text-zinc-950">Aktivitas konten terbaru</h2>
                        <p class="mt-1 text-sm text-zinc-500">Artikel yang terakhir diperbarui.</p>
                    </div>
                    <a href="{{ route('admin.posts.index') }}" class="text-sm font-black text-emerald-700 hover:text-emerald-900">Lihat semua</a>
                </div>
                <div class="divide-y divide-zinc-100">
                    @forelse($latestPosts as $post)
                        <a href="{{ route('admin.posts.edit', $post['id']) }}" class="group flex items-center gap-4 px-5 py-4 transition hover:bg-emerald-50/40 sm:px-6">
                            <div class="grid size-10 shrink-0 place-items-center rounded-lg bg-zinc-100 text-zinc-500 group-hover:bg-emerald-100 group-hover:text-emerald-700">
                                <i class="fa-regular fa-file-lines"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-zinc-900">{{ $post['title'] }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-400">
                                    <span>{{ $post['category_name'] ?: 'Tanpa kategori' }}</span><span>•</span>
                                    <span>{{ Carbon::parse($post['updated_at'])->diffForHumans() }}</span>
                                </div>
                            </div>
                            <x-admin.pill :value="$post['status']" />
                        </a>
                    @empty
                        <div class="px-6 py-12 text-center">
                            <i class="fa-regular fa-newspaper text-3xl text-zinc-300"></i>
                            <p class="mt-3 text-sm font-bold text-zinc-600">Belum ada artikel.</p>
                            <a href="{{ route('admin.posts.create') }}" class="mt-2 inline-block text-sm font-black text-emerald-700">Tulis artikel pertama</a>
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        <aside class="rounded-2xl border border-zinc-200/80 bg-white p-5 shadow-[0_12px_40px_-30px_rgba(24,24,27,0.45)] sm:p-6">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Akses cepat</p>
            <h2 class="mt-1 text-lg font-black text-zinc-950">Pekerjaan rutin</h2>
            <div class="mt-5 space-y-2">
                @foreach([
                    ['label' => 'Atur tampilan banner', 'href' => route('admin.banners.index'), 'icon' => 'fa-panorama', 'feature' => 'banners'],
                    ['label' => 'Unggah dokumentasi', 'href' => route('admin.gallery.index'), 'icon' => 'fa-images', 'feature' => 'gallery'],
                    ['label' => 'Perbarui halaman desa', 'href' => route('admin.pages.index'), 'icon' => 'fa-file-pen', 'feature' => 'pages'],
                    ['label' => 'Pengaturan website', 'href' => route('admin.settings.index'), 'icon' => 'fa-sliders', 'roles' => ['developer', 'admin_desa']],
                ] as $action)
                    @if((! isset($action['feature']) || VillageFeatures::enabled($villageId, $action['feature'], auth()->user())) && (! isset($action['roles']) || in_array(auth()->user()?->role, $action['roles'], true)))
                        <a href="{{ $action['href'] }}" class="group flex min-h-12 items-center gap-3 rounded-lg border border-transparent px-3 text-sm font-bold text-zinc-700 transition hover:border-emerald-100 hover:bg-emerald-50 hover:text-emerald-800">
                            <span class="grid size-9 place-items-center rounded-lg bg-zinc-100 text-zinc-500 group-hover:bg-white group-hover:text-emerald-700"><i class="fa-solid {{ $action['icon'] }}"></i></span>
                            <span class="flex-1">{{ $action['label'] }}</span>
                            <i class="fa-solid fa-chevron-right text-[10px] text-zinc-300"></i>
                        </a>
                    @endif
                @endforeach
            </div>
        </aside>
    </section>
</div>
