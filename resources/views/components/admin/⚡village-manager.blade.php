<?php

use App\Rules\ValidCoordinates;
use App\Services\TenantResolver;
use App\Services\VillageProvisioner;
use App\Support\CoordinatePair;
use App\Support\CurrentVillage;
use App\Support\PublicSiteCache;
use App\Support\UniqueSlug;
use App\Support\VillageFeatures;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

new class extends Component
{
    public array $villages = [];

    public array $districtOptions = [];

    public array $featureDefinitions = [];

    public array $selectedFeatures = [];

    public bool $showModal = false;

    public int $activeVillageId = 1;

    public int $page = 1;

    public int $perPage = 25;

    public int $totalVillages = 0;

    public string $search = '';

    public string $districtFilter = '';

    public string $sidesiFilter = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public string $coordinates = '';

    public array $form = [
        'id' => null,
        'name' => '',
        'slug' => '',
        'district' => '',
        'regency' => '',
        'province' => '',
        'address' => '',
        'phone' => '',
        'email' => '',
        'website_url' => '',
        'public_hostname' => '',
        'api_endpoint_url' => '',
        'sidesi_village_id' => '',
        'analytics_key' => '',
        'latitude' => '',
        'longitude' => '',
        'welcome_message' => '',
        'description' => '',
        'vision' => '',
        'mission' => '',
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'developer', 403);

        $this->districtOptions = array_combine(config('regions.districts'), config('regions.districts'));
        $this->featureDefinitions = VillageFeatures::definitions();
        $this->activeVillageId = CurrentVillage::id();
        $this->loadData();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'districtFilter', 'sidesiFilter', 'sortBy', 'sortDirection'], true)) {
            $this->page = 1;
            $this->loadData();
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->districtFilter = '';
        $this->sidesiFilter = '';
        $this->sortBy = 'name';
        $this->sortDirection = 'asc';
        $this->page = 1;
        $this->loadData();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $village = DB::table('villages')->where('id', $id)->first();

        if (! $village) {
            return;
        }

        $this->form = array_merge($this->form, (array) $village);
        $this->form['province'] = config('regions.province');
        $this->form['regency'] = config('regions.regency');
        $this->coordinates = CoordinatePair::format($this->form['latitude'], $this->form['longitude']);
        $this->selectedFeatures = VillageFeatures::enabledKeys($id);
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $id = $this->form['id'];
        $oldHostname = $id ? DB::table('villages')->where('id', $id)->value('public_hostname') : null;
        $this->form['regency'] = config('regions.regency');
        $this->form['province'] = config('regions.province');
        $this->form['public_hostname'] = app(TenantResolver::class)->normalizeHostname(
            (string) ($this->form['public_hostname']
                ?: parse_url((string) $this->form['website_url'], PHP_URL_HOST)),
        );

        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.district' => ['required', 'string', Rule::in(config('regions.districts'))],
            'form.address' => ['nullable', 'string'],
            'form.phone' => ['nullable', 'string', 'max:40'],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.website_url' => ['nullable', 'url', 'max:2048'],
            'form.public_hostname' => [
                'nullable',
                'string',
                'max:253',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
                Rule::unique('villages', 'public_hostname')->ignore($id),
            ],
            'form.api_endpoint_url' => ['nullable', 'url', 'max:2048'],
            'form.sidesi_village_id' => ['nullable', 'digits_between:1,20'],
            'coordinates' => ['nullable', 'string', new ValidCoordinates],
            'form.welcome_message' => ['nullable', 'string'],
            'form.description' => ['nullable', 'string'],
            'form.vision' => ['nullable', 'string'],
            'form.mission' => ['nullable', 'string'],
        ])['form'];

        $coordinatePair = trim($this->coordinates) === ''
            ? ['latitude' => null, 'longitude' => null]
            : CoordinatePair::parse($this->coordinates);

        $payload = [...$data, 'regency' => config('regions.regency'), 'province' => config('regions.province'), 'slug' => UniqueSlug::make('villages', $data['name'], $id), 'analytics_key' => $this->form['analytics_key'] ?: Str::random(64), 'latitude' => $coordinatePair['latitude'], 'longitude' => $coordinatePair['longitude'], 'updated_at' => now()];

        if ($id) {
            DB::table('villages')->where('id', $id)->update($payload);
            $villageId = (int) $id;
        } else {
            $villageId = (int) DB::table('villages')->insertGetId([...$payload, 'created_at' => now()]);
            app(VillageProvisioner::class)->provision($villageId, auth()->id());
        }

        VillageFeatures::sync($villageId, $this->selectedFeatures);
        app(TenantResolver::class)->forget($oldHostname);
        app(TenantResolver::class)->forget($data['public_hostname'] ?? null);
        PublicSiteCache::forget($villageId);
        $this->showModal = false;
        $this->resetForm();
        $this->loadData();
        LivewireAlert::title('Tersimpan')->text('Data desa berhasil disimpan.')->success()->timer(1200)->show();
    }

    public function selectVillage(int $id): void
    {
        if (! DB::table('villages')->where('id', $id)->exists()) {
            return;
        }

        session()->put('active_village_id', $id);
        $this->activeVillageId = $id;
        $this->loadData();
        LivewireAlert::title('Aktif')->text('Pengaturan desa aktif sudah diganti.')->success()->timer(1200)->show();
    }

    public function delete(int $id): void
    {
        if ($id === $this->activeVillageId || $this->usageCount($id) > 0) {
            LivewireAlert::title('Tidak bisa dihapus')->text('Desa masih aktif atau sudah memiliki data terkait.')->warning()->timer(1800)->show();

            return;
        }

        DB::table('villages')->where('id', $id)->delete();
        PublicSiteCache::forget($id);
        $this->loadData();
        LivewireAlert::title('Terhapus')->text('Desa berhasil dihapus.')->success()->timer(1200)->show();
    }

    public function regenerateAnalyticsKey(): void
    {
        $this->form['analytics_key'] = Str::random(64);
    }

    public function previousPage(): void
    {
        $this->page = max($this->page - 1, 1);
        $this->loadData();
    }

    public function nextPage(): void
    {
        $lastPage = max((int) ceil($this->totalVillages / $this->perPage), 1);
        $this->page = min($this->page + 1, $lastPage);
        $this->loadData();
    }

    private function loadData(): void
    {
        $today = now()->toDateString();
        $query = DB::table('villages')
            ->select('villages.*')
            ->selectSub(fn ($query) => $query->from('users')->whereColumn('users.village_id', 'villages.id')->selectRaw('count(*)'), 'users_count')
            ->selectSub(fn ($query) => $query->from('posts')->whereColumn('posts.village_id', 'villages.id')->selectRaw('count(*)'), 'posts_count')
            ->selectSub(fn ($query) => $query->from('pages')->whereColumn('pages.village_id', 'villages.id')->selectRaw('count(*)'), 'pages_count')
            ->selectSub(fn ($query) => $query->from('village_features')->whereColumn('village_features.village_id', 'villages.id')->where('is_enabled', true)->selectRaw('count(*)'), 'features_count')
            ->selectSub(fn ($query) => $query->from('village_visitor_daily_stats')->whereColumn('village_visitor_daily_stats.village_id', 'villages.id')->where('visit_date', $today)->select('unique_visitors')->limit(1), 'today_unique_visitors')
            ->selectSub(fn ($query) => $query->from('village_visitor_daily_stats')->whereColumn('village_visitor_daily_stats.village_id', 'villages.id')->where('visit_date', $today)->select('total_visits')->limit(1), 'today_total_visits')
            ->when(trim($this->search) !== '', function ($query): void {
                $search = '%'.strtolower(trim($this->search)).'%';
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(villages.name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(villages.district, \'\')) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(villages.website_url, \'\')) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(villages.public_hostname, \'\')) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(villages.sidesi_village_id, \'\')) LIKE ?', [$search]);
                });
            })
            ->when($this->districtFilter !== '', fn ($query) => $query->where('villages.district', $this->districtFilter))
            ->when($this->sidesiFilter === 'configured', fn ($query) => $query->whereNotNull('villages.sidesi_village_id')->where('villages.sidesi_village_id', '!=', ''))
            ->when($this->sidesiFilter === 'empty', fn ($query) => $query->where(fn ($query) => $query->whereNull('villages.sidesi_village_id')->orWhere('villages.sidesi_village_id', '')));

        $this->totalVillages = (clone $query)->count('villages.id');
        $lastPage = max((int) ceil($this->totalVillages / $this->perPage), 1);
        $this->page = min(max($this->page, 1), $lastPage);

        $sortColumns = [
            'name' => 'villages.name',
            'district' => 'villages.district',
            'created_at' => 'villages.created_at',
            'updated_at' => 'villages.updated_at',
        ];
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $this->villages = $query
            ->orderBy($sortColumns[$this->sortBy] ?? 'villages.name', $direction)
            ->orderBy('villages.id')
            ->forPage($this->page, $this->perPage)
            ->get()
            ->map(fn (object $village): array => [
                ...(array) $village,
                'users_count' => (int) $village->users_count,
                'posts_count' => (int) $village->posts_count,
                'pages_count' => (int) $village->pages_count,
                'features_count' => (int) $village->features_count,
                'today_unique_visitors' => (int) ($village->today_unique_visitors ?? 0),
                'today_total_visits' => (int) ($village->today_total_visits ?? 0),
                'is_active_context' => (int) $village->id === $this->activeVillageId,
            ])
            ->all();
    }

    private function resetForm(): void
    {
        $this->form = [
            'id' => null,
            'name' => '',
            'slug' => '',
            'district' => '',
            'regency' => config('regions.regency'),
            'province' => config('regions.province'),
            'address' => '',
            'phone' => '',
            'email' => '',
            'website_url' => '',
            'public_hostname' => '',
            'api_endpoint_url' => '',
            'sidesi_village_id' => '',
            'analytics_key' => Str::random(64),
            'latitude' => '',
            'longitude' => '',
            'welcome_message' => '',
            'description' => '',
            'vision' => '',
            'mission' => '',
        ];
        $this->coordinates = '';
        $this->selectedFeatures = VillageFeatures::keys();
    }

    private function usageCount(int $villageId): int
    {
        return collect(['users', 'posts', 'pages', 'hero_banners', 'gallery_albums', 'downloadable_files'])->sum(fn (string $table): int => DB::table($table)->where('village_id', $villageId)->count());
    }
};
?>

<div class="space-y-5">
    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-zinc-200 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-black">Modul Desa</h2>
                <p class="text-sm text-zinc-500">Developer dapat menambah desa baru dan memilih desa aktif untuk
                    dikelola.</p>
            </div>
            <button type="button" wire:click="create"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-black text-white"><i class="fa-solid fa-plus"></i>Tambah Desa</button>
        </div>

        <div class="grid gap-3 border-b border-zinc-200 p-5 md:grid-cols-2 xl:grid-cols-6">
            <x-admin.input label="Cari Desa" model="search" placeholder="Nama, kecamatan, website, atau SIDESI" class="xl:col-span-2" />
            <x-admin.select label="Kecamatan" model="districtFilter" :options="['' => 'Semua kecamatan', ...$districtOptions]" />
            <x-admin.select label="SIDESI" model="sidesiFilter" :options="['' => 'Semua data', 'configured' => 'Sudah diatur', 'empty' => 'Belum diatur']" />
            <div class="grid grid-cols-[1fr_120px] gap-2">
                <x-admin.select label="Urutkan" model="sortBy" :options="['name' => 'Nama', 'district' => 'Kecamatan', 'created_at' => 'Dibuat', 'updated_at' => 'Diubah']" />
                <x-admin.select label="Arah" model="sortDirection" :options="['asc' => 'A-Z', 'desc' => 'Z-A']" />
            </div>
            <button type="button" wire:click="resetFilters" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-zinc-300 px-3 text-sm font-bold text-zinc-700 md:col-span-2 xl:col-span-1 xl:self-end">
                <i class="fa-solid fa-rotate-left"></i>
                Reset
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="px-5 py-3">ID Desa</th>
                        <th class="px-5 py-3">Desa</th>
                        <th class="px-5 py-3">Wilayah</th>
                        <th class="px-5 py-3">Website / SIDESI</th>
                        <th class="px-5 py-3">Pengunjung Hari Ini</th>
                        <th class="px-5 py-3">Data</th>
                        <th class="w-64 px-5 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @forelse($villages as $village)
                        <tr>
                            <td class="px-5 py-4 font-mono text-xs text-zinc-500">
                                <span class="text-emerald-600 text-lg font-bold">{{ $village['id'] }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="mt-1 text-xs font-semibold text-zinc-500">{{ $village['name'] }}</div>
                                @if ($village['is_active_context'])
                                    <span
                                        class="mt-2 inline-flex rounded bg-emerald-50 px-2 py-1 text-xs font-black text-emerald-700">Aktif</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div>{{ $village['district'] ?: '-' }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ collect([$village['regency'], $village['province']])->filter()->implode(', ') ?:'-' }}
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                @if($village['website_url'])
                                    <a href="{{ $village['website_url'] }}" target="_blank" rel="noopener" class="font-semibold text-emerald-700 hover:underline">
                                        {{ parse_url($village['website_url'], PHP_URL_HOST) ?: $village['website_url'] }}
                                    </a>
                                @else
                                    <span class="text-zinc-400">Belum diatur</span>
                                @endif
                                <div class="mt-1 max-w-64 truncate font-mono text-xs text-sky-700">{{ $village['public_hostname'] ?: 'Hostname publik belum diatur' }}</div>
                                <div class="mt-1 max-w-64 truncate text-xs text-zinc-500">{{ $village['api_endpoint_url'] ?: 'Endpoint belum diatur' }}</div>
                                <div class="mt-2 font-mono text-xs font-bold {{ $village['sidesi_village_id'] ? 'text-emerald-700' : 'text-red-600' }}">
                                    SIDESI: {{ $village['sidesi_village_id'] ?: 'belum diatur' }}
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-black">{{ number_format($village['today_total_visits'], 0, ',', '.') }} kunjungan</div>
                                <div class="text-xs text-zinc-500">{{ number_format($village['today_unique_visitors'], 0, ',', '.') }} pengunjung unik</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2 text-xs font-black">
                                    <span class="rounded bg-zinc-100 px-2 py-1">{{ $village['users_count'] }}
                                        user</span>
                                    <span class="rounded bg-zinc-100 px-2 py-1">{{ $village['posts_count'] }}
                                        artikel</span>
                                    <span class="rounded bg-zinc-100 px-2 py-1">{{ $village['pages_count'] }}
                                        page</span>
                                    <span class="rounded bg-emerald-50 px-2 py-1 text-emerald-700">{{ $village['features_count'] }}
                                        fitur</span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" wire:click="selectVillage({{ $village['id'] }})"
                                        class="inline-flex items-center gap-2 rounded bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700"><i class="fa-solid fa-location-dot"></i>Pilih</button>
                                    <button type="button" wire:click="edit({{ $village['id'] }})"
                                        class="inline-flex items-center gap-2 rounded bg-zinc-100 px-3 py-2 text-xs font-bold"><i class="fa-solid fa-pen"></i>Edit</button>
                                    <button type="button" wire:click="delete({{ $village['id'] }})"
                                        wire:confirm="Hapus desa ini?"
                                        class="inline-flex items-center gap-2 rounded bg-red-50 px-3 py-2 text-xs font-bold text-red-700"><i class="fa-solid fa-trash"></i>Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-zinc-500">Belum ada desa.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between gap-4 border-t border-zinc-200 px-5 py-4 text-sm">
            <span class="font-semibold text-zinc-500">Halaman {{ $page }} dari {{ max((int) ceil($totalVillages / $perPage), 1) }} · {{ $totalVillages }} desa</span>
            <div class="flex gap-2">
                <button type="button" wire:click="previousPage" @disabled($page <= 1) class="rounded-md border border-zinc-200 px-3 py-2 font-bold disabled:cursor-not-allowed disabled:opacity-40">Sebelumnya</button>
                <button type="button" wire:click="nextPage" @disabled($page >= max((int) ceil($totalVillages / $perPage), 1)) class="rounded-md border border-zinc-200 px-3 py-2 font-bold disabled:cursor-not-allowed disabled:opacity-40">Berikutnya</button>
            </div>
        </div>
    </section>

    @if ($showModal)
        <div x-data @click.self="$wire.closeModal()" @keydown.escape.window="$wire.closeModal()"
            class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 p-4" role="dialog"
            aria-modal="true">
            <div class="max-h-[90dvh] w-full max-w-5xl overflow-y-auto rounded-lg bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                    <div>
                        <h3 class="text-lg font-black">{{ $form['id'] ? 'Edit' : 'Tambah' }} Desa</h3>
                        <p class="text-sm text-zinc-500">Lengkapi informasi dasar desa untuk tenant CMS.</p>
                    </div>
                    <button type="button" wire:click="closeModal"
                        class="grid size-11 place-items-center rounded-md border border-zinc-300"
                        aria-label="Tutup modal">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit="save" class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    <x-admin.input label="Nama Desa" model="form.name" />
                    <x-admin.select label="Kecamatan" model="form.district" :options="$districtOptions" />
                    <x-admin.input label="Kabupaten" model="form.regency" readonly />
                    <x-admin.input label="Provinsi" model="form.province" readonly />
                    <x-admin.input label="Telepon" model="form.phone" />
                    <x-admin.input label="Email" model="form.email" type="email" />
                    <x-admin.input label="URL Website" model="form.website_url" type="url" placeholder="https://desa.example.go.id" class="lg:col-span-2" />
                    <x-admin.input label="Hostname Publik" model="form.public_hostname" placeholder="desa.example.go.id" />
                    <x-admin.input label="Endpoint API Website" model="form.api_endpoint_url" type="url" placeholder="https://desa.example.go.id/api" />
                    <p class="-mt-2 text-xs leading-5 text-zinc-500 lg:col-span-3">Hostname harus unik dan tanpa <code>https://</code>, path, atau port. Nilai ini menentukan desa yang ditampilkan oleh frontend multi-tenant.</p>
                    <div class="lg:col-span-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                        <x-admin.input label="ID Desa SIDESI" model="form.sidesi_village_id" inputmode="numeric" placeholder="Contoh: 1610022013" />
                        <p class="mt-2 text-xs leading-5 text-emerald-800">Satu-satunya konfigurasi Peta Sebaran yang perlu diisi. Fasilitas Umum dan Bantuan akan ditarik langsung dari SIDESI Ogan Ilir.</p>
                    </div>
                    <x-admin.input label="Koordinat" model="coordinates" placeholder="-3.238421, 104.715834" class="lg:col-span-2" />
                    <div class="lg:col-span-3">
                        <label class="flex items-center gap-2 text-sm font-bold"><i class="fa-solid fa-key text-amber-600"></i>Kunci Analytics</label>
                        <div class="mt-1 flex gap-2">
                            <input type="text" wire:model="form.analytics_key" readonly class="min-w-0 flex-1 rounded-md border border-zinc-300 bg-zinc-100 px-3 py-2 font-mono text-xs text-zinc-600">
                            <button type="button" wire:click="regenerateAnalyticsKey" class="inline-flex min-h-11 items-center gap-2 rounded-md border border-zinc-300 px-3 text-sm font-bold">
                                <i class="fa-solid fa-rotate"></i>
                                Buat Ulang
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-zinc-500">Gunakan pada header <code>X-Village-Analytics-Key</code> untuk endpoint statistik.</p>
                    </div>
                    <x-admin.textarea label="Alamat" model="form.address" class="lg:col-span-3" />
                    <x-admin.textarea label="Pesan Sambutan" model="form.welcome_message" class="lg:col-span-3" />
                    <x-admin.textarea label="Deskripsi" model="form.description" class="lg:col-span-3" />
                    <x-admin.textarea label="Visi" model="form.vision" />
                    <x-admin.textarea label="Misi" model="form.mission" class="lg:col-span-2" />

                    <fieldset class="lg:col-span-3">
                        <div class="border-t border-zinc-200 pt-5">
                            <legend class="font-black">Fitur Desa</legend>
                            <p class="mt-1 text-sm text-zinc-500">Pilih modul yang dapat digunakan oleh pengelola desa ini.</p>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($featureDefinitions as $featureKey => $feature)
                                <label class="flex min-h-20 cursor-pointer items-start gap-3 rounded-md border border-zinc-200 p-3 transition hover:border-emerald-300 hover:bg-emerald-50/50">
                                    <input type="checkbox" wire:model="selectedFeatures" value="{{ $featureKey }}" class="mt-1 rounded border-zinc-300 text-emerald-600">
                                    <span>
                                        <span class="flex items-center gap-2 text-sm font-black">
                                            <i class="fa-solid {{ $feature['icon'] }} text-emerald-700"></i>
                                            {{ $feature['label'] }}
                                        </span>
                                        <span class="mt-1 block text-xs leading-5 text-zinc-500">{{ $feature['description'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="flex justify-end gap-2 border-t border-zinc-200 pt-5 sm:col-span-2 lg:col-span-3">
                        <button type="button" wire:click="closeModal"
                            class="inline-flex min-h-11 items-center gap-2 rounded-md border border-zinc-300 px-4 text-sm font-bold"><i class="fa-solid fa-xmark"></i>Batal</button>
                        <button
                            class="inline-flex min-h-11 items-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-black text-white"><i class="fa-solid fa-floppy-disk"></i>Simpan
                            Desa</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
