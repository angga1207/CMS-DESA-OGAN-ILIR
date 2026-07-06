<?php

use App\Support\CurrentVillage;
use App\Support\PublicSiteCache;
use App\Support\VillageAdm4Resolver;
use App\Support\WidgetCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

new class extends Component {
    public int $villageId = 1;

    public array $catalog = [];

    public array $widgetOptions = [];

    public array $placementOptions = [];

    public array $rows = [];

    public string $catalogSearch = '';

    public string $search = '';

    public string $statusFilter = '';

    public string $activePlacementTab = 'all';

    public string $typeFilter = '';

    public string $activeTab = 'available';

    public bool $showModal = false;

    public array $form = [
        'id' => null,
        'widget_type' => '',
        'title' => '',
        'placement' => 'sidebar',
        'is_active' => true,
    ];

    public array $config = [];

    public function mount(): void
    {
        $this->villageId = CurrentVillage::id();
        $this->catalog = WidgetCatalog::all();
        $this->widgetOptions = WidgetCatalog::options();
        $this->placementOptions = [];
        $this->loadData();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'activePlacementTab', 'typeFilter'], true)) {
            $this->loadData();
        }
    }

    public function resetListFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->activePlacementTab = 'all';
        $this->typeFilter = '';
        $this->loadData();
    }

    public function create(?string $type = null): void
    {
        $this->resetForm();

        if ($type && WidgetCatalog::get($type)) {
            $this->setWidgetType($type);
        }

        $this->showModal = true;
    }

    public function updatedFormWidgetType(?string $type): void
    {
        if (!$type || !WidgetCatalog::get($type)) {
            $this->form['widget_type'] = '';
            $this->form['placement'] = 'sidebar';
            $this->placementOptions = [];
            $this->config = [];

            return;
        }

        $this->setWidgetType($type);
    }

    public function edit(int $id): void
    {
        $row = DB::table('village_widgets')->where('village_id', $this->villageId)->where('id', $id)->first();

        if (!$row || !WidgetCatalog::get($row->widget_type)) {
            return;
        }

        $this->placementOptions = WidgetCatalog::placementOptions($row->widget_type);
        $this->form = [
            'id' => $row->id,
            'widget_type' => $row->widget_type,
            'title' => $row->title,
            'placement' => WidgetCatalog::normalizePlacement($row->widget_type, $row->placement),
            'is_active' => (bool) $row->is_active,
        ];
        $this->config = array_merge(WidgetCatalog::defaults($row->widget_type), json_decode($row->config ?: '{}', true) ?: []);
        $this->fillWeatherAdm4();
        $this->showModal = true;
    }

    public function save(): void
    {
        $type = (string) ($this->form['widget_type'] ?? '');
        $definition = WidgetCatalog::get($type);
        $this->fillWeatherAdm4();

        $this->validate(
            [
                'form.widget_type' => ['required', Rule::in(array_keys($this->catalog))],
                'form.title' => ['required', 'string', 'max:150'],
                'form.placement' => ['required', Rule::in($definition ? WidgetCatalog::allowedPlacements($type) : array_keys(WidgetCatalog::placements()))],
                'form.is_active' => ['boolean'],
                ...$definition ? WidgetCatalog::rules($type) : [],
            ],
            [
                'form.widget_type.required' => 'Jenis widget wajib dipilih.',
                'form.widget_type.in' => 'Jenis widget tidak tersedia di katalog.',
                'form.title.required' => 'Judul widget wajib diisi.',
                'form.placement.required' => 'Posisi widget wajib dipilih.',
                'form.placement.in' => 'Posisi widget tidak sesuai dengan jenis widget.',
            ],
        );

        if (!$definition) {
            return;
        }

        $payload = [
            'village_id' => $this->villageId,
            'widget_type' => $type,
            'title' => $this->form['title'],
            'placement' => $this->form['placement'],
            'sort_order' => $this->widgetSortOrder($this->form['id'] ? (int) $this->form['id'] : null, (string) $this->form['placement']),
            'is_active' => (bool) $this->form['is_active'],
            'config' => json_encode(Arr::only($this->config, array_keys($definition['fields'] ?? []))),
            'updated_at' => now(),
        ];

        if ($this->form['id']) {
            DB::table('village_widgets')->where('village_id', $this->villageId)->where('id', $this->form['id'])->update($payload);
        } else {
            DB::table('village_widgets')->insert([...$payload, 'created_at' => now()]);
        }

        $this->forgetPublicCache();

        $this->closeModal();
        $this->loadData();
        $this->activeTab = 'active';
        LivewireAlert::title('Tersimpan')->text('Widget berhasil disimpan.')->success()->timer(1200)->show();
    }

    public function toggle(int $id): void
    {
        $widget = DB::table('village_widgets')->where('village_id', $this->villageId)->where('id', $id)->first();

        if (!$widget) {
            return;
        }

        DB::table('village_widgets')
            ->where('id', $id)
            ->update([
                'is_active' => !$widget->is_active,
                'updated_at' => now(),
            ]);

        $this->forgetPublicCache();

        $this->loadData();
    }

    public function delete(int $id): void
    {
        DB::table('village_widgets')->where('village_id', $this->villageId)->where('id', $id)->delete();

        $this->forgetPublicCache();

        $this->loadData();
        LivewireAlert::title('Terhapus')->text('Widget berhasil dihapus.')->success()->timer(1200)->show();
    }

    public function reorderWidgets(string $placement, array $orderedIds): void
    {
        if (!array_key_exists($placement, WidgetCatalog::placements())) {
            return;
        }

        $orderedIds = array_values(array_unique(array_map('intval', $orderedIds)));
        $currentIds = DB::table('village_widgets')
            ->where('village_id', $this->villageId)
            ->where('placement', $placement)
            ->whereIn('widget_type', array_keys($this->catalog))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn($id): int => (int) $id)
            ->all();

        $validIds = array_values(array_intersect($orderedIds, $currentIds));
        $remainingIds = array_values(array_diff($currentIds, $validIds));
        $finalIds = [...$validIds, ...$remainingIds];

        foreach ($finalIds as $index => $id) {
            DB::table('village_widgets')
                ->where('village_id', $this->villageId)
                ->where('id', $id)
                ->update(['sort_order' => $index + 1, 'updated_at' => now()]);
        }

        $this->forgetPublicCache();
        $this->loadData();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function loadData(): void
    {
        $this->rows = DB::table('village_widgets')
            ->where('village_id', $this->villageId)
            ->whereIn('widget_type', array_keys($this->catalog))
            ->when(trim($this->search) !== '', function ($query): void {
                $search = '%' . strtolower(trim($this->search)) . '%';
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(title) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(widget_type) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(placement) LIKE ?', [$search]);
                });
            })
            ->when($this->statusFilter !== '', fn($query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->when($this->activePlacementTab !== 'all', fn($query) => $query->where('placement', $this->activePlacementTab))
            ->when($this->typeFilter !== '', fn($query) => $query->where('widget_type', $this->typeFilter))
            ->orderBy('placement')
            ->orderBy('sort_order')
            ->get()
            ->map(function (object $row): array {
                $definition = WidgetCatalog::get($row->widget_type);

                return [...(array) $row, 'label' => $definition['label'] ?? $row->widget_type, 'description' => $definition['description'] ?? '', 'icon' => $definition['icon'] ?? 'fa-solid fa-puzzle-piece', 'placement_label' => WidgetCatalog::placements()[WidgetCatalog::normalizePlacement($row->widget_type, $row->placement)] ?? $row->placement];
            })
            ->all();
    }

    public function placementCounts(): array
    {
        $counts = DB::table('village_widgets')
            ->where('village_id', $this->villageId)
            ->whereIn('widget_type', array_keys($this->catalog))
            ->selectRaw('placement, COUNT(*) as total')
            ->groupBy('placement')
            ->pluck('total', 'placement')
            ->map(fn($total): int => (int) $total)
            ->all();

        return collect(WidgetCatalog::placements())->mapWithKeys(fn(string $label, string $placement): array => [$placement => $counts[$placement] ?? 0])->all();
    }

    public function placementRows(): array
    {
        return collect($this->rows)->groupBy(fn(array $row): string => WidgetCatalog::normalizePlacement($row['widget_type'], $row['placement']))->all();
    }

    public function filteredCatalog(): array
    {
        $search = strtolower(trim($this->catalogSearch));

        if ($search === '') {
            return $this->catalog;
        }

        return collect($this->catalog)->filter(fn(array $widget, string $type): bool => str_contains(strtolower($type), $search) || str_contains(strtolower($widget['label'] ?? ''), $search) || str_contains(strtolower($widget['description'] ?? ''), $search))->all();
    }

    private function forgetPublicCache(): void
    {
        PublicSiteCache::forget($this->villageId);
    }

    private function setWidgetType(string $type): void
    {
        $definition = WidgetCatalog::get($type);

        if (!$definition) {
            return;
        }

        $this->form['widget_type'] = $type;
        $this->form['title'] = $definition['label'];
        $this->form['placement'] = $definition['default_placement'];
        $this->placementOptions = WidgetCatalog::placementOptions($type);
        $this->config = WidgetCatalog::defaults($type);
        $this->fillWeatherAdm4();
        $this->resetValidation();
    }

    private function fillWeatherAdm4(): void
    {
        if (($this->form['widget_type'] ?? '') !== 'weather_information' || filled($this->config['adm4'] ?? null)) {
            return;
        }

        $village = DB::table('villages')
            ->where('id', $this->villageId)
            ->first(['name', 'district']);
        $location = $village ? VillageAdm4Resolver::resolve((string) $village->name, (string) ($village->district ?? '')) : null;

        if ($location) {
            $this->config['adm4'] = $location['adm4'];
        }
    }

    private function resetForm(): void
    {
        $this->form = [
            'id' => null,
            'widget_type' => '',
            'title' => '',
            'placement' => 'sidebar',
            'is_active' => true,
        ];
        $this->config = [];
        $this->placementOptions = [];
        $this->resetValidation();
    }

    private function widgetSortOrder(?int $widgetId, string $placement): int
    {
        if ($widgetId) {
            $current = DB::table('village_widgets')
                ->where('village_id', $this->villageId)
                ->where('id', $widgetId)
                ->first(['placement', 'sort_order']);

            if ($current && $current->placement === $placement) {
                return (int) $current->sort_order;
            }
        }

        return ((int) DB::table('village_widgets')->where('village_id', $this->villageId)->where('placement', $placement)->max('sort_order')) + 1;
    }
};
?>

<div class="space-y-6">
    <div class="rounded-lg border border-zinc-200 bg-white p-1 shadow-sm">
        <div class="grid gap-1 sm:grid-cols-2" role="tablist" aria-label="Tab widget website">
            <button type="button" wire:click="$set('activeTab', 'available')" @class([
                'flex min-h-12 items-center justify-center gap-2 rounded-md px-4 text-sm font-black transition',
                'bg-emerald-600 text-white shadow-sm' => $activeTab === 'available',
                'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-950' =>
                    $activeTab !== 'available',
            ]) role="tab"
                aria-selected="{{ $activeTab === 'available' ? 'true' : 'false' }}">
                <i class="fa-solid fa-layer-group"></i>
                <span>Available Widget</span>
                <span @class([
                    'rounded-full px-2 py-0.5 text-xs',
                    'bg-white/20 text-white' => $activeTab === 'available',
                    'bg-zinc-100 text-zinc-500' => $activeTab !== 'available',
                ])>{{ count($this->filteredCatalog()) }}</span>
            </button>
            <button type="button" wire:click="$set('activeTab', 'active')" @class([
                'flex min-h-12 items-center justify-center gap-2 rounded-md px-4 text-sm font-black transition',
                'bg-emerald-600 text-white shadow-sm' => $activeTab === 'active',
                'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-950' =>
                    $activeTab !== 'active',
            ]) role="tab"
                aria-selected="{{ $activeTab === 'active' ? 'true' : 'false' }}">
                <i class="fa-solid fa-toggle-on"></i>
                <span>Widget Aktif</span>
                <span @class([
                    'rounded-full px-2 py-0.5 text-xs',
                    'bg-white/20 text-white' => $activeTab === 'active',
                    'bg-zinc-100 text-zinc-500' => $activeTab !== 'active',
                ])>{{ count($rows) }}</span>
            </button>
        </div>
    </div>

    @if ($activeTab === 'available')
        <section role="tabpanel">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-black">Katalog Widget</h2>
                    <p class="text-sm text-zinc-500">Pilih widget yang ingin dipasang pada website desa.</p>
                </div>
                <button type="button" wire:click="create"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-black text-white">
                    <i class="fa-solid fa-plus"></i>
                    Tambah Widget
                </button>
            </div>

            <div class="mb-4">
                <x-admin.input label="Cari Katalog" model="catalogSearch" placeholder="Nama atau fungsi widget" />
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($this->filteredCatalog() as $type => $widget)
                    <article class="flex min-h-40 flex-col rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start gap-3">
                            <div
                                class="grid size-11 shrink-0 place-items-center rounded-md bg-emerald-50 text-emerald-700">
                                <i class="{{ $widget['icon'] }}"></i>
                            </div>
                            <div>
                                <h3 class="font-black">{{ $widget['label'] }}</h3>
                                <p class="mt-1 text-sm leading-6 text-zinc-500">{{ $widget['description'] }}</p>
                            </div>
                        </div>
                        <button type="button" wire:click="create('{{ $type }}')"
                            class="mt-auto inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 text-sm font-bold text-emerald-800 hover:bg-emerald-100">
                            <i class="fa-solid fa-plus"></i>
                            Pasang
                        </button>
                    </article>
                @empty
                    <div
                        class="rounded-lg border border-dashed border-zinc-300 bg-white p-8 text-center text-sm font-semibold text-zinc-500 sm:col-span-2 xl:col-span-3">
                        Tidak ada widget di katalog yang cocok.</div>
                @endforelse
            </div>
        </section>
    @elseif($activeTab === 'active')
        <section class="rounded-lg border border-zinc-200 bg-white shadow-sm" role="tabpanel">
            <div class="border-b border-zinc-200 p-5">
                <h2 class="font-black">Widget Terpasang</h2>
                <p class="text-sm text-zinc-500">Pilih tab posisi, lalu geser widget untuk mengatur urutannya.</p>
            </div>
            <div class="grid gap-3 border-b border-zinc-200 p-5 md:grid-cols-2 xl:grid-cols-4">
                <x-admin.input label="Cari Widget" model="search" placeholder="Judul, jenis, atau posisi"
                    class="" />
                <x-admin.select label="Status" model="statusFilter" :options="['' => 'Semua status', 'active' => 'Aktif', 'inactive' => 'Nonaktif']" />
                <x-admin.select label="Jenis" model="typeFilter" :options="['' => 'Semua jenis', ...$widgetOptions]" />
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-bold hidden lg:block">&nbsp;</label>
                    <button type="button" wire:click="resetListFilters"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-zinc-300 px-3 text-sm font-bold text-zinc-700">
                        <i class="fa-solid fa-rotate-left"></i>
                        Reset
                    </button>
                </div>
            </div>
            @php($placementCounts = $this->placementCounts())
            @php($placementRows = $this->placementRows())
            <div class="border-b border-zinc-200 p-4">
                <div class="flex gap-2 overflow-x-auto" role="tablist" aria-label="Tab posisi widget">
                    <button type="button" wire:click="$set('activePlacementTab', 'all')" @class([
                        'inline-flex min-h-10 shrink-0 items-center gap-2 rounded-md px-3 text-xs font-black transition',
                        'bg-emerald-600 text-white' => $activePlacementTab === 'all',
                        'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' =>
                            $activePlacementTab !== 'all',
                    ])>
                        Semua
                        <span @class([
                            'rounded-full px-2 py-0.5',
                            'bg-white/20 text-white' => $activePlacementTab === 'all',
                            'bg-white text-zinc-500' => $activePlacementTab !== 'all',
                        ])>{{ array_sum($placementCounts) }}</span>
                    </button>
                    @foreach (WidgetCatalog::placements() as $placement => $label)
                        <button type="button" wire:click="$set('activePlacementTab', '{{ $placement }}')"
                            @class([
                                'inline-flex min-h-10 shrink-0 items-center gap-2 rounded-md px-3 text-xs font-black transition',
                                'bg-emerald-600 text-white' => $activePlacementTab === $placement,
                                'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' =>
                                    $activePlacementTab !== $placement,
                            ])>
                            {{ $label }}
                            <span @class([
                                'rounded-full px-2 py-0.5',
                                'bg-white/20 text-white' => $activePlacementTab === $placement,
                                'bg-white text-zinc-500' => $activePlacementTab !== $placement,
                            ])>{{ $placementCounts[$placement] ?? 0 }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="divide-y divide-zinc-200">
                @if (count($rows) === 0)
                    <div class="px-5 py-12 text-center text-zinc-500">
                        <i class="fa-solid fa-puzzle-piece text-3xl text-zinc-300"></i>
                        <p class="mt-3 font-semibold">Belum ada widget yang dipasang.</p>
                    </div>
                @else
                    @foreach (WidgetCatalog::placements() as $placement => $label)
                        @php($widgets = collect($placementRows[$placement] ?? []))
                        @if ($activePlacementTab === 'all' || $activePlacementTab === $placement)
                            <section class="p-5">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div>
                                        <h3 class="font-black">{{ $label }}</h3>
                                        <p class="text-xs font-semibold text-zinc-500">{{ $widgets->count() }} widget
                                        </p>
                                    </div>
                                </div>
                                @if ($widgets->isEmpty())
                                    <div
                                        class="rounded-lg border border-dashed border-zinc-300 bg-zinc-50 p-6 text-center text-sm font-semibold text-zinc-500">
                                        Belum ada widget pada posisi ini.
                                    </div>
                                @else
                                    <div class="space-y-3" data-widget-sortable
                                        data-widget-placement="{{ $placement }}">
                                        @foreach ($widgets as $row)
                                            <div draggable="true" data-widget-item-id="{{ $row['id'] }}"
                                                data-widget-placement="{{ $placement }}"
                                                class="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-4 transition data-[dragging=true]:opacity-50 lg:flex-row lg:items-center">
                                                <div class="flex min-w-0 flex-1 items-start gap-3">
                                                    <button type="button"
                                                        class="grid size-11 shrink-0 cursor-grab place-items-center rounded-md bg-zinc-100 text-zinc-700 active:cursor-grabbing"
                                                        title="Geser untuk mengubah urutan"
                                                        aria-label="Geser widget {{ $row['title'] }}">
                                                        <i class="fa-solid fa-grip-vertical"></i>
                                                    </button>
                                                    <div
                                                        class="grid size-11 shrink-0 place-items-center rounded-md bg-zinc-100 text-zinc-700">
                                                        <i class="{{ $row['icon'] }}"></i>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <h4 class="font-black">{{ $row['title'] }}</h4>
                                                            <x-admin.pill :value="$row['is_active'] ? 'Aktif' : 'Nonaktif'" />
                                                            <span
                                                                class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-bold text-zinc-600">{{ $row['label'] }}</span>
                                                        </div>
                                                        <p class="mt-1 text-sm text-zinc-500">{{ $row['description'] }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="flex flex-wrap gap-2">
                                                    <button type="button" wire:click="toggle({{ $row['id'] }})"
                                                        class="inline-flex min-h-10 items-center gap-2 rounded-md border border-zinc-300 px-3 text-xs font-bold">
                                                        <i
                                                            class="fa-solid {{ $row['is_active'] ? 'fa-toggle-on text-emerald-700' : 'fa-toggle-off text-zinc-400' }}"></i>
                                                        {{ $row['is_active'] ? 'Nonaktifkan' : 'Aktifkan' }}
                                                    </button>
                                                    <button type="button" wire:click="edit({{ $row['id'] }})"
                                                        class="inline-flex min-h-10 items-center gap-2 rounded-md bg-zinc-100 px-3 text-xs font-bold">
                                                        <i class="fa-solid fa-pen"></i>
                                                        Edit
                                                    </button>
                                                    <button type="button" wire:click="delete({{ $row['id'] }})"
                                                        wire:confirm="Hapus widget ini?"
                                                        class="inline-flex min-h-10 items-center gap-2 rounded-md bg-red-50 px-3 text-xs font-bold text-red-700">
                                                        <i class="fa-solid fa-trash"></i>
                                                        Hapus
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </section>
                        @endif
                    @endforeach
                @endif
            </div>
        </section>
    @endif

    @if ($showModal)
        <div x-data @click.self="$wire.closeModal()" @keydown.escape.window="$wire.closeModal()"
            class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 p-4" role="dialog"
            aria-modal="true">
            <div class="max-h-[90dvh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                    <div>
                        <h3 class="text-lg font-black">{{ $form['id'] ? 'Edit Widget' : 'Pasang Widget' }}</h3>
                        <p class="text-sm text-zinc-500">Konfigurasi widget untuk website desa aktif.</p>
                    </div>
                    <button type="button" wire:click="closeModal"
                        class="grid size-11 place-items-center rounded-md border border-zinc-300"
                        aria-label="Tutup modal">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit="save" class="grid gap-4 p-5 sm:grid-cols-2">
                    <x-admin.select label="Jenis Widget" model="form.widget_type" :options="$widgetOptions"
                        class="sm:col-span-2" />
                    <x-admin.input label="Judul Widget" model="form.title" />
                    @if ($form['widget_type'])
                        <x-admin.select label="Posisi" model="form.placement" :options="$placementOptions" />
                    @else
                        <div>
                            <label class="text-sm font-bold text-zinc-500">Posisi</label>
                            <div
                                class="mt-1 flex min-h-11 items-center rounded-md border border-zinc-200 bg-zinc-50 px-3 text-sm text-zinc-400">
                                Pilih jenis widget terlebih dahulu</div>
                        </div>
                    @endif
                    <x-admin.select label="Status" model="form.is_active" :options="[1 => 'Aktif', 0 => 'Nonaktif']" />

                    @if ($form['widget_type'] && isset($catalog[$form['widget_type']]))
                        <div class="border-t border-zinc-200 pt-5 sm:col-span-2">
                            <h4 class="font-black">Konfigurasi {{ $catalog[$form['widget_type']]['label'] }}</h4>
                            <p class="mt-1 text-sm text-zinc-500">{{ $catalog[$form['widget_type']]['description'] }}
                            </p>
                        </div>

                        @foreach ($catalog[$form['widget_type']]['fields'] as $key => $field)
                            @if ($field['type'] === 'select')
                                <x-admin.select :label="$field['label']" :model="'config.' . $key" :options="$field['options']" />
                            @elseif($field['type'] === 'textarea')
                                <x-admin.textarea :label="$field['label']" :model="'config.' . $key" class="sm:col-span-2" />
                            @elseif($field['type'] === 'checkbox')
                                <label
                                    class="flex min-h-11 items-center gap-3 rounded-md border border-zinc-200 px-3 text-sm font-bold">
                                    <input type="checkbox" wire:model="config.{{ $key }}"
                                        class="rounded border-zinc-300 text-emerald-600">
                                    {{ $field['label'] }}
                                </label>
                            @else
                                <x-admin.input :label="$field['label']" :model="'config.' . $key" :type="$field['type']"
                                    :placeholder="$field['placeholder'] ?? null" />
                            @endif
                        @endforeach
                    @endif

                    <div class="flex justify-end gap-2 border-t border-zinc-200 pt-5 sm:col-span-2">
                        <button type="button" wire:click="closeModal"
                            class="inline-flex min-h-11 items-center rounded-md border border-zinc-300 px-4 text-sm font-bold">Batal</button>
                        <button type="submit"
                            class="inline-flex min-h-11 items-center rounded-md bg-emerald-600 px-4 text-sm font-black text-white">Simpan
                            Widget</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
