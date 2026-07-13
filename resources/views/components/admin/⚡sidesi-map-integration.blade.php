<?php

use App\Services\SidesiClient;
use App\Support\CurrentVillage;
use App\Support\ExternalDataCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public ?object $village = null;
    public string $kind = 'facility';
    public string $categoryId = '';
    public string $search = '';
    public int $page = 1;
    public int $perPage = 10;
    public array $facilityCategories = [];
    public array $assistanceCategories = [];
    public array $rows = [];
    public int $totalRows = 0;
    public int $coordinateRows = 0;
    public string $error = '';

    public function mount(): void
    {
        $this->village = DB::table('villages')->where('id', CurrentVillage::id())->first();
        $this->loadCategories();
        $this->loadRows();
    }

    public function updatedKind(): void
    {
        if (!in_array($this->kind, ['facility', 'assistance'], true)) {
            $this->kind = 'facility';
        }

        $this->categoryId = '';
        $this->search = '';
        $this->page = 1;
        $this->setDefaultCategory();
        $this->loadRows();
    }

    public function updatedCategoryId(): void
    {
        $this->page = 1;
        $this->loadRows();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->loadRows();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;
        $this->page = 1;
        $this->loadRows();
    }

    public function reloadData(): void
    {
        $this->error = '';
        Cache::forget('external-data:v1:sidesi:map:categories');

        if ($this->village?->sidesi_village_id && $this->categoryId !== '') {
            $villageId = (string) $this->village->sidesi_village_id;
            $categoryId = (int) $this->categoryId;
            $dataKey = $this->kind === 'facility' ? "sidesi:map:facilities:{$villageId}:{$categoryId}" : "sidesi:map:assistance:{$villageId}:{$categoryId}";

            Cache::forget("external-data:v1:{$dataKey}");
        }

        $this->loadCategories();
        $this->loadRows();
    }

    public function previousPage(): void
    {
        $this->page = max($this->page - 1, 1);
        $this->loadRows();
    }

    public function nextPage(): void
    {
        $this->page = min($this->page + 1, $this->lastPage());
        $this->loadRows();
    }

    public function lastPage(): int
    {
        return max((int) ceil($this->totalRows / max($this->perPage, 1)), 1);
    }

    public function currentCategories(): array
    {
        return $this->kind === 'facility' ? $this->facilityCategories : $this->assistanceCategories;
    }

    public function categoryLabel(): string
    {
        foreach ($this->currentCategories() as $category) {
            if ((string) $category['id'] === (string) $this->categoryId) {
                return (string) $category['label'];
            }
        }

        return 'Belum ada kategori';
    }

    public function loadRows(): void
    {
        $this->rows = [];
        $this->totalRows = 0;
        $this->coordinateRows = 0;

        if (!$this->village?->sidesi_village_id || $this->categoryId === '') {
            return;
        }

        try {
            $this->error = '';
            $records = $this->filterRows($this->recordsForCurrentSelection());
            $this->totalRows = count($records);
            $this->coordinateRows = collect($records)->filter(fn(array $row): bool => $this->hasCoordinates($row))->count();
            $this->page = min($this->page, $this->lastPage());
            $offset = ($this->page - 1) * $this->perPage;
            $this->rows = array_slice($records, $offset, $this->perPage);
        } catch (\RuntimeException $exception) {
            report($exception);
            $this->error = 'Data Peta Sebaran dari SIDESI sedang tidak tersedia.';
        }
    }

    public function value(array $row, array|string $keys, string $fallback = '-'): string
    {
        foreach ((array) $keys as $key) {
            $value = data_get($row, $key);

            if (is_array($value)) {
                $value = collect($value)->filter()->implode(', ');
            }

            if ($value !== null && trim((string) $value) !== '' && trim((string) $value) !== '-') {
                return (string) $value;
            }
        }

        return $fallback;
    }

    public function coordinateLabel(array $row): string
    {
        $latitude = $this->value($row, 'latitude', '');
        $longitude = $this->value($row, 'longitude', '');

        if ($latitude === '' || $longitude === '') {
            return '-';
        }

        return "{$latitude}, {$longitude}";
    }

    public function rowKey(array $row, int $index): string
    {
        return $this->kind . '-' . $this->value($row, ['id_listing', 'id_kartu_keluarga', 'id_keluarga', 'id'], (string) $index);
    }

    private function loadCategories(): void
    {
        if (!$this->village?->sidesi_village_id) {
            return;
        }

        try {
            $payload = ExternalDataCache::remember(
                'sidesi:map:categories',
                fn(): array => [
                    'facility' => [
                        'label' => 'Fasilitas Umum',
                        'subcategories' => app(SidesiClient::class)->facilityCategories(),
                    ],
                    'assistance' => [
                        'label' => 'Bantuan',
                        'subcategories' => app(SidesiClient::class)->assistanceCategories(),
                    ],
                ],
                3600,
                86400,
            );

            $this->facilityCategories = $this->normalizeCategories($payload['facility']['subcategories'] ?? [], 'facility');
            $this->assistanceCategories = $this->normalizeCategories($payload['assistance']['subcategories'] ?? [], 'assistance');
            $this->setDefaultCategory();
        } catch (\Throwable $exception) {
            report($exception);
            $this->error = 'Kategori Peta Sebaran dari SIDESI sedang tidak tersedia.';
        }
    }

    private function setDefaultCategory(): void
    {
        $categories = $this->currentCategories();

        if ($categories === []) {
            $this->categoryId = '';

            return;
        }

        $exists = collect($categories)->contains(fn(array $category): bool => (string) $category['id'] === (string) $this->categoryId);

        if ($exists) {
            return;
        }

        $preferred = collect($categories)->first(fn(array $category): bool => str($category['label'])->lower()->toString() === 'fasilitas umum');
        $this->categoryId = (string) ($preferred ?? $categories[0])['id'];
    }

    private function normalizeCategories(array $payload, string $kind): array
    {
        return collect($this->payloadRows($payload))
            ->map(function (array $row) use ($kind): ?array {
                $id = $this->value($row, $kind === 'facility' ? ['id_kategori_listing', 'id_kategori', 'id'] : ['id_bantuan', 'id']);
                $label = $this->value($row, $kind === 'facility' ? ['nama_kategori_listing', 'nama_kategori', 'nama'] : ['nama_bantuan', 'nama']);

                if ($id === '-' || $label === '-') {
                    return null;
                }

                return [
                    'id' => (string) $id,
                    'label' => (string) $label,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function recordsForCurrentSelection(): array
    {
        $villageId = (string) $this->village->sidesi_village_id;
        $categoryId = (int) $this->categoryId;

        $payload = $this->kind === 'facility' ? ExternalDataCache::remember("sidesi:map:facilities:{$villageId}:{$categoryId}", fn(): array => app(SidesiClient::class)->facilities($villageId, $categoryId)) : ExternalDataCache::remember("sidesi:map:assistance:{$villageId}:{$categoryId}", fn(): array => app(SidesiClient::class)->assistanceRecipients($villageId, $categoryId));

        return $this->payloadRows($payload);
    }

    private function payloadRows(array $payload): array
    {
        $rows = $payload['data'] ?? $payload;

        if (!is_array($rows)) {
            return [];
        }

        if ($rows === []) {
            return [];
        }

        if (!array_is_list($rows)) {
            return [array_map(fn($value) => is_array($value) ? $value : $value, $rows)];
        }

        return collect($rows)->filter(fn($row): bool => is_array($row))->values()->all();
    }

    private function filterRows(array $rows): array
    {
        $search = str($this->search)->lower()->trim()->toString();

        if ($search === '') {
            return $rows;
        }

        return collect($rows)
            ->filter(function (array $row) use ($search): bool {
                $haystack = str(collect($row)->flatten()->filter()->implode(' '))
                    ->lower()
                    ->toString();

                return str_contains($haystack, $search);
            })
            ->values()
            ->all();
    }

    public function hasCoordinates(array $row): bool
    {
        $latitude = data_get($row, 'latitude');
        $longitude = data_get($row, 'longitude');

        return is_numeric($latitude) && is_numeric($longitude);
    }
};
?>

<div class="space-y-5">
    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-zinc-200 p-5 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h2 class="font-black">Data Peta Sebaran</h2>
                <p class="mt-1 text-sm text-zinc-500">Sumber SIDESI Ogan Ilir · {{ $this->categoryLabel() }} ·
                    {{ number_format($totalRows, 0, ',', '.') }} data ditemukan.</p>
            </div>

            <div class="grid gap-3 md:grid-cols-[160px_minmax(220px,1fr)_140px_auto]">
                <div>
                    <label class="text-xs font-black uppercase text-zinc-500">Jenis Data</label>
                    <select wire:model.live="kind"
                        class="mt-1 min-h-11 w-full rounded-md border border-zinc-300 px-3 text-sm font-bold focus:border-emerald-600 focus:outline-none">
                        <option value="facility">Fasilitas</option>
                        <option value="assistance">Bantuan</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-black uppercase text-zinc-500">Kategori</label>
                    <select wire:model.live="categoryId"
                        class="mt-1 min-h-11 w-full rounded-md border border-zinc-300 px-3 text-sm font-bold focus:border-emerald-600 focus:outline-none">
                        @forelse($this->currentCategories() as $category)
                            <option value="{{ $category['id'] }}">{{ $category['label'] }}</option>
                        @empty
                            <option value="">Kategori belum tersedia</option>
                        @endforelse
                    </select>
                </div>
                <div>
                    <label class="text-xs font-black uppercase text-zinc-500">Per Halaman</label>
                    <select wire:model.live="perPage"
                        class="mt-1 min-h-11 w-full rounded-md border border-zinc-300 px-3 text-sm font-bold focus:border-emerald-600 focus:outline-none">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <button type="button" wire:click="reloadData"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-black text-white md:self-end">
                    <i class="fa-solid fa-rotate"></i>
                    Muat Ulang
                </button>
            </div>
        </div>

        @if (!$village?->sidesi_village_id)
            <div class="m-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                ID Desa SIDESI belum dikonfigurasi. Developer dapat mengisinya melalui menu Manajemen Desa.
            </div>
        @endif

        @if ($error)
            <div class="m-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700">
                {{ $error }}
            </div>
        @endif

        <div class="grid gap-3 border-b border-zinc-200 p-5 md:grid-cols-[1fr_auto_auto] md:items-end">
            <x-admin.input label="Cari Data" model="search" placeholder="Nama, alamat, bantuan, kontak, atau wilayah"
                icon="fa-magnifying-glass" />
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3">
                <div class="text-xs font-black uppercase text-zinc-500">Bertitik Koordinat</div>
                <div class="mt-1 text-xl font-black text-emerald-700">{{ number_format($coordinateRows, 0, ',', '.') }}
                </div>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3">
                <div class="text-xs font-black uppercase text-zinc-500">Halaman</div>
                <div class="mt-1 text-xl font-black text-zinc-950">{{ $page }} / {{ $this->lastPage() }}</div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                    @if ($kind === 'facility')
                        <tr>
                            <th class="px-5 py-3">Fasilitas</th>
                            <th class="px-5 py-3">Pengelola</th>
                            <th class="px-5 py-3">Kontak</th>
                            <th class="px-5 py-3">Alamat</th>
                            <th class="px-5 py-3">Koordinat</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    @else
                        <tr>
                            <th class="px-5 py-3">Kepala Keluarga</th>
                            <th class="px-5 py-3">Program Bantuan</th>
                            <th class="px-5 py-3">Wilayah</th>
                            <th class="px-5 py-3">Alamat</th>
                            <th class="px-5 py-3">Koordinat</th>
                            <th class="px-5 py-3">Foto</th>
                        </tr>
                    @endif
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @forelse($rows as $index => $row)
                        @if ($kind === 'facility')
                            <tr wire:key="{{ $this->rowKey($row, $index) }}">
                                <td class="px-5 py-4">
                                    <div class="font-black text-zinc-950">
                                        {{ $this->value($row, ['nama_listing', 'nama']) }}</div>
                                    <div class="mt-1 text-xs text-zinc-500">
                                        {{ $this->value($row, ['nama_kategori_listing', 'kategori'], $this->categoryLabel()) }}
                                    </div>
                                    @if ($this->value($row, 'website', '') !== '')
                                        <a href="{{ $this->value($row, 'website') }}" target="_blank" rel="noopener"
                                            class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-emerald-700 hover:underline">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                            Website
                                        </a>
                                    @endif
                                </td>
                                <td class="px-5 py-4">{{ $this->value($row, 'nama_pengelola') }}</td>
                                <td class="px-5 py-4">
                                    <div>{{ $this->value($row, 'telepon') }}</div>
                                    <div class="mt-1 text-xs text-zinc-500">{{ $this->value($row, 'email') }}</div>
                                </td>
                                <td class="max-w-xs px-5 py-4">
                                    <div class="line-clamp-3">{{ $this->value($row, 'alamat') }}</div>
                                </td>
                                <td class="px-5 py-4 font-mono text-xs">
                                    @if ($this->hasCoordinates($row))
                                        <a href="https://www.google.com/maps?q={{ $this->coordinateLabel($row) }}"
                                            target="_blank" rel="noopener"
                                            class="font-bold text-emerald-700 hover:underline">
                                            {{ $this->coordinateLabel($row) }}
                                        </a>
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-black text-zinc-700">{{ $this->value($row, 'status') }}</span>
                                </td>
                            </tr>
                        @else
                            <tr wire:key="{{ $this->rowKey($row, $index) }}">
                                <td class="px-5 py-4">
                                    <div class="font-black text-zinc-950">
                                        {{ $this->value($row, ['nama_kepala_keluarga', 'kepala_keluarga', 'nama']) }}
                                    </div>
                                    <div class="mt-1 text-xs text-zinc-500">KK: {{ $this->value($row, ['no_kk']) }}
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="max-w-xs line-clamp-3">
                                        {{ $this->value($row, ['name_bantuan', 'nama_bantuan'], $this->categoryLabel()) }}
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div>{{ $this->value($row, 'desa') }}</div>
                                    <div class="mt-1 text-xs text-zinc-500">{{ $this->value($row, 'kecamatan') }}</div>
                                    <div class="mt-1 text-xs font-bold text-zinc-500">RT/RW
                                        {{ $this->value($row, 'no_rt') }} / {{ $this->value($row, 'no_rw') }}</div>
                                </td>
                                <td class="max-w-xs px-5 py-4">
                                    <div class="line-clamp-3">{{ $this->value($row, 'alamat') }}</div>
                                    <div class="mt-1 text-xs text-zinc-500">Kode Pos
                                        {{ $this->value($row, 'kode_pos') }}</div>
                                </td>
                                <td class="px-5 py-4 font-mono text-xs">
                                    @if ($this->hasCoordinates($row))
                                        <a href="https://www.google.com/maps?q={{ $this->coordinateLabel($row) }}"
                                            target="_blank" rel="noopener"
                                            class="font-bold text-emerald-700 hover:underline">
                                            {{ $this->coordinateLabel($row) }}
                                        </a>
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    @if ($this->value($row, 'file_foto_rumah', '') !== '')
                                        <a href="{{ $this->value($row, 'file_foto_rumah') }}" target="_blank"
                                            rel="noopener"
                                            class="inline-flex items-center gap-2 rounded bg-zinc-100 px-3 py-2 text-xs font-bold">
                                            <i class="fa-solid fa-image"></i>
                                            Lihat
                                        </a>
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-zinc-500">
                                Belum ada data untuk kategori ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div
            class="flex flex-col gap-3 border-t border-zinc-200 px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between">
            <span class="font-semibold text-zinc-500">
                Menampilkan {{ number_format(count($rows), 0, ',', '.') }} dari
                {{ number_format($totalRows, 0, ',', '.') }} data
            </span>
            <div class="flex gap-2">
                <button type="button" wire:click="previousPage" @disabled($page <= 1)
                    class="inline-flex min-h-10 items-center gap-2 rounded-md border border-zinc-200 px-3 font-bold disabled:cursor-not-allowed disabled:opacity-40">
                    <i class="fa-solid fa-chevron-left"></i>
                    Sebelumnya
                </button>
                <button type="button" wire:click="nextPage" @disabled($page >= $this->lastPage())
                    class="inline-flex min-h-10 items-center gap-2 rounded-md border border-zinc-200 px-3 font-bold disabled:cursor-not-allowed disabled:opacity-40">
                    Berikutnya
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </section>
</div>
