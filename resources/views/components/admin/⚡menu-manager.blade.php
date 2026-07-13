<?php

use App\Support\CurrentVillage;
use App\Support\PublicSiteCache;
use Illuminate\Support\Facades\DB;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

new class extends Component {
    public string $title = 'Menu Dinamis';

    public bool $showModal = false;

    public int $villageId = 1;

    public array $rows = [];

    public array $pages = [];

    public array $parentMenuOptions = [];

    public array $form = [];

    public function mount(): void
    {
        $role = auth()->user()?->role;
        abort_unless(in_array($role, ['developer', 'admin_desa', 'editor'], true), 403);

        $this->villageId = CurrentVillage::id();
        $this->resetForm();
        $this->loadData();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function createSubmenu(int $parentId): void
    {
        $this->resetForm();
        $this->form['parent_id'] = $this->validParentId($parentId) ? $parentId : '';
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $row = DB::table('navigation_items')->where('village_id', $this->villageId)->where('id', $id)->first();

        if (!$row) {
            return;
        }

        $this->form = array_merge($this->form, (array) $row);
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->validate(
            [
                'form.label' => ['required', 'string', 'max:255'],
                'form.type' => ['required', 'in:url,page'],
                'form.parent_id' => ['nullable', 'integer'],
                'form.page_id' => ['nullable', 'integer'],
                'form.url' => ['nullable', 'string', 'max:255'],
                'form.target' => ['required', 'in:_self,_blank'],
                'form.is_active' => ['boolean'],
            ],
            [],
            [
                'form.label' => 'Label',
                'form.type' => 'Jenis',
                'form.parent_id' => 'Menu Induk',
                'form.page_id' => 'Halaman',
                'form.url' => 'Tautan',
                'form.target' => 'Buka Tautan',
                'form.is_active' => 'Status',
            ],
        );

        $itemId = $this->form['id'] ?? null;
        $parentId = $this->form['parent_id'] ?: null;
        $currentParentId = $itemId ? DB::table('navigation_items')->where('village_id', $this->villageId)->where('id', $itemId)->value('parent_id') : null;
        $payload = [
            'menu_id' => $this->publicMenuId(),
            'village_id' => $this->villageId,
            'parent_id' => $parentId,
            'page_id' => $this->form['type'] === 'page' ? ($this->form['page_id'] ?: null) : null,
            'label' => $this->form['label'],
            'type' => $this->form['type'],
            'url' => $this->form['type'] === 'url' ? $this->form['url'] : null,
            'target' => $this->form['target'] ?: '_self',
            'sort_order' => $itemId && (int) $currentParentId === (int) $parentId ? (int) ($this->form['sort_order'] ?? 0) : $this->nextMenuSortOrder($parentId),
            'is_active' => (bool) $this->form['is_active'],
            'updated_at' => now(),
        ];

        if ($itemId) {
            DB::table('navigation_items')->where('village_id', $this->villageId)->where('id', $itemId)->update($payload);
        } else {
            DB::table('navigation_items')->insert([...$payload, 'created_at' => now()]);
        }

        PublicSiteCache::forget($this->villageId);

        $this->showModal = false;
        $this->resetForm();
        $this->loadData();
        LivewireAlert::title('Tersimpan')->text('Menu berhasil disimpan.')->success()->timer(1200)->show();
    }

    public function delete(int $id): void
    {
        DB::table('navigation_items')->where('village_id', $this->villageId)->where('id', $id)->delete();

        PublicSiteCache::forget($this->villageId);
        $this->loadData();
    }

    public function reorderMenus(?int $parentId, array $orderedIds, ?int $movedId = null, ?int $sourceParentId = null): void
    {
        $menuId = $this->publicMenuId();
        $parentId = $parentId ?: null;
        $sourceParentId = $sourceParentId ?: null;

        DB::transaction(function () use ($menuId, $parentId, $orderedIds, $movedId, $sourceParentId): void {
            if ($movedId) {
                $movedItem = DB::table('navigation_items')->where('village_id', $this->villageId)->where('menu_id', $menuId)->where('id', $movedId)->first();

                if (!$movedItem) {
                    return;
                }

                $childIds = [];

                if ($parentId !== null) {
                    $targetParentExists = DB::table('navigation_items')->where('village_id', $this->villageId)->where('menu_id', $menuId)->where('id', $parentId)->whereNull('parent_id')->exists();

                    if (!$targetParentExists || (int) $movedItem->id === $parentId) {
                        return;
                    }

                    if ($movedItem->parent_id === null) {
                        $childIds = DB::table('navigation_items')->where('village_id', $this->villageId)->where('menu_id', $menuId)->where('parent_id', $movedId)->orderBy('sort_order')->orderBy('id')->pluck('id')->map(fn($id): int => (int) $id)->all();
                    }

                    if ($childIds !== []) {
                        DB::table('navigation_items')
                            ->where('village_id', $this->villageId)
                            ->where('menu_id', $menuId)
                            ->whereIn('id', $childIds)
                            ->update(['parent_id' => $parentId, 'updated_at' => now()]);

                        $orderedIds = collect($orderedIds)->flatMap(fn($id): array => (int) $id === $movedId ? [$movedId, ...$childIds] : [(int) $id])->all();
                    }
                }

                if ((int) ($movedItem->parent_id ?? 0) !== (int) ($parentId ?? 0)) {
                    $orderedIds = collect($orderedIds)->map(fn($id): int => (int) $id)->all();

                    DB::table('navigation_items')
                        ->where('village_id', $this->villageId)
                        ->where('menu_id', $menuId)
                        ->where('id', $movedId)
                        ->update(['parent_id' => $parentId, 'updated_at' => now()]);
                }
            }

            $this->normalizeMenuOrder($menuId, $parentId, $orderedIds);

            if ($movedId && $sourceParentId !== $parentId) {
                $this->normalizeMenuOrder($menuId, $sourceParentId);
            }
        });

        PublicSiteCache::forget($this->villageId);
        $this->loadData();
    }

    private function loadData(): void
    {
        $menuId = $this->publicMenuId();

        $this->pages = DB::table('pages')->where('village_id', $this->villageId)->orderBy('title')->get()->map(fn($row): array => (array) $row)->all();

        $this->rows = DB::table('navigation_items')
            ->leftJoin('navigation_items as parents', 'navigation_items.parent_id', '=', 'parents.id')
            ->leftJoin('pages', 'navigation_items.page_id', '=', 'pages.id')
            ->where('navigation_items.menu_id', $menuId)
            ->where('navigation_items.village_id', $this->villageId)
            ->orderBy('navigation_items.parent_id')
            ->orderBy('navigation_items.sort_order')
            ->get(['navigation_items.*', 'parents.label as parent_label', 'pages.title as page_title'])
            ->map(fn($row): array => (array) $row)
            ->all();

        $this->parentMenuOptions = collect($this->rows)->whereNull('parent_id')->values()->all();
    }

    private function resetForm(): void
    {
        $this->form = [
            'id' => null,
            'parent_id' => '',
            'page_id' => '',
            'label' => '',
            'type' => 'url',
            'url' => '',
            'target' => '_self',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    private function normalizeMenuOrder(int $menuId, ?int $parentId, array $orderedIds = []): void
    {
        $parentId = $parentId ?: null;
        $orderedIds = array_values(array_unique(array_map('intval', $orderedIds)));

        $currentIds = DB::table('navigation_items')->where('village_id', $this->villageId)->where('menu_id', $menuId)->where('parent_id', $parentId)->orderBy('sort_order')->orderBy('id')->pluck('id')->map(fn($id): int => (int) $id)->all();

        $validIds = array_values(array_intersect($orderedIds, $currentIds));
        $remainingIds = array_values(array_diff($currentIds, $validIds));
        $finalIds = [...$validIds, ...$remainingIds];

        foreach ($finalIds as $index => $id) {
            DB::table('navigation_items')
                ->where('village_id', $this->villageId)
                ->where('menu_id', $menuId)
                ->where('id', $id)
                ->update(['sort_order' => $index + 1, 'updated_at' => now()]);
        }
    }

    private function nextMenuSortOrder(?int $parentId): int
    {
        return ((int) DB::table('navigation_items')->where('village_id', $this->villageId)->where('menu_id', $this->publicMenuId())->where('parent_id', $parentId)->max('sort_order')) + 1;
    }

    private function publicMenuId(): int
    {
        return (int) (DB::table('navigation_menus')->where('village_id', $this->villageId)->where('location', 'public')->value('id') ?:
        DB::table('navigation_menus')->insertGetId([
            'village_id' => $this->villageId,
            'name' => 'Navbar Publik',
            'location' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function validParentId(int $parentId): bool
    {
        return DB::table('navigation_items')->where('village_id', $this->villageId)->where('menu_id', $this->publicMenuId())->where('id', $parentId)->whereNull('parent_id')->exists();
    }
};
?>

<div class="space-y-5">
    <section class="admin-panel overflow-hidden border bg-white">
        <div class="admin-panel-header flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="flex items-center gap-2 font-black text-emerald-950"><i
                        class="fa-solid fa-bars-staggered text-amber-600"></i>{{ $title }}</h2>
                <p class="text-sm text-zinc-500">Susun menu utama dan submenu sesuai urutan navigasi.</p>
            </div>
            <button type="button" wire:click="create"
                class="admin-btn-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-4 text-sm font-black text-white">
                <i class="fa-solid fa-plus"></i>
                Tambah Menu
            </button>
        </div>

        @php($mainMenus = collect($rows)->whereNull('parent_id')->sortBy('sort_order'))
        <div class="space-y-3 bg-[#f4f7f1] p-5" data-menu-sortable data-menu-level="main" data-parent-id="">
            @forelse($mainMenus as $mainMenu)
                @php($children = collect($rows)->where('parent_id', $mainMenu['id'])->sortBy('sort_order'))
                <article draggable="true" data-menu-item-id="{{ $mainMenu['id'] }}" data-menu-level="main"
                    data-menu-parent-id=""
                    class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm transition data-[dragging=true]:opacity-50">
                    <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <button type="button"
                                class="grid size-10 shrink-0 cursor-grab place-items-center rounded-md bg-emerald-50 font-black text-emerald-700 active:cursor-grabbing"
                                title="Geser untuk mengubah urutan" aria-label="Geser menu {{ $mainMenu['label'] }}">
                                <i class="fa-solid fa-grip-vertical"></i>
                            </button>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-black text-zinc-950">{{ $mainMenu['label'] }}</h3>
                                    <x-admin.pill :value="$mainMenu['type']" />
                                    <x-admin.pill :value="$mainMenu['is_active'] ? 'active' : 'inactive'" />
                                    <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-bold text-zinc-600">
                                        {{ $children->count() }} submenu
                                    </span>
                                </div>
                                <p class="mt-1 truncate text-sm text-zinc-500">
                                    {{ $mainMenu['type'] === 'page' ? ($mainMenu['page_title'] ?: 'Halaman belum dipilih') : ($mainMenu['url'] ?: 'URL belum diisi') }}
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <button type="button" wire:click="createSubmenu({{ $mainMenu['id'] }})"
                                class="inline-flex min-h-9 items-center gap-2 rounded-md bg-emerald-50 px-3 text-xs font-bold text-emerald-700 hover:bg-emerald-100">
                                <i class="fa-solid fa-plus"></i> Submenu
                            </button>
                            <button type="button" wire:click="edit({{ $mainMenu['id'] }})"
                                class="inline-flex min-h-9 items-center gap-2 rounded-md bg-zinc-100 px-3 text-xs font-bold hover:bg-zinc-200">
                                <i class="fa-solid fa-pen"></i> Edit
                            </button>
                            <button type="button" wire:click="delete({{ $mainMenu['id'] }})"
                                wire:confirm="Hapus menu utama beserta seluruh submenunya?"
                                class="inline-flex min-h-9 items-center gap-2 rounded-md bg-red-50 px-3 text-xs font-bold text-red-700 hover:bg-red-100">
                                <i class="fa-solid fa-trash"></i> Hapus
                            </button>
                        </div>
                    </div>

                    <div class="border-t border-zinc-200 bg-zinc-50/70 px-4 py-3">
                        <div class="ml-5 min-h-11 space-y-2 border-l-2 border-emerald-200 pl-4" data-menu-sortable
                            data-menu-level="submenu" data-parent-id="{{ $mainMenu['id'] }}">
                            @if ($children->isEmpty())
                                <div
                                    class="flex flex-col gap-2 rounded-md border border-dashed border-zinc-300 bg-white/70 px-3 py-3 text-xs font-bold text-zinc-400 sm:flex-row sm:items-center sm:justify-between">
                                    <span>Tarik submenu ke sini</span>
                                    <button type="button" wire:click="createSubmenu({{ $mainMenu['id'] }})"
                                        class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md bg-emerald-600 px-3 text-xs font-black text-white">
                                        <i class="fa-solid fa-plus"></i> Tambah Submenu
                                    </button>
                                </div>
                            @endif
                            @foreach ($children as $child)
                                <div draggable="true" data-menu-item-id="{{ $child['id'] }}" data-menu-level="submenu"
                                    data-menu-parent-id="{{ $mainMenu['id'] }}"
                                    class="flex flex-col gap-3 rounded-md border border-zinc-200 bg-white p-3 transition data-[dragging=true]:opacity-50 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <button type="button"
                                            class="grid size-8 shrink-0 cursor-grab place-items-center rounded-md bg-emerald-50 text-emerald-600 active:cursor-grabbing"
                                            title="Geser untuk mengubah urutan"
                                            aria-label="Geser submenu {{ $child['label'] }}">
                                            <i class="fa-solid fa-grip-vertical"></i>
                                        </button>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-bold text-zinc-900">{{ $child['label'] }}</span>
                                                <x-admin.pill :value="$child['type']" />
                                                <x-admin.pill :value="$child['is_active'] ? 'active' : 'inactive'" />
                                            </div>
                                            <p class="mt-1 truncate text-xs text-zinc-500">
                                                {{ $child['type'] === 'page' ? ($child['page_title'] ?: 'Halaman belum dipilih') : ($child['url'] ?: 'URL belum diisi') }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 gap-2">
                                        <button type="button" wire:click="edit({{ $child['id'] }})"
                                            class="inline-flex min-h-9 items-center gap-2 rounded-md bg-zinc-100 px-3 text-xs font-bold hover:bg-zinc-200">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                        <button type="button" wire:click="delete({{ $child['id'] }})"
                                            wire:confirm="Hapus submenu ini?"
                                            class="inline-flex min-h-9 items-center gap-2 rounded-md bg-red-50 px-3 text-xs font-bold text-red-700 hover:bg-red-100">
                                            <i class="fa-solid fa-trash"></i> Hapus
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center text-zinc-500">
                    Belum ada menu. Tambahkan menu utama terlebih dahulu.
                </div>
            @endforelse
        </div>
    </section>

    @if ($showModal)
        <div x-data @click.self="$wire.closeModal()" @keydown.escape.window="$wire.closeModal()"
            class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/60 p-4 backdrop-blur-sm"
            role="dialog" aria-modal="true">
            <div class="admin-panel max-h-[90dvh] w-full max-w-4xl overflow-y-auto border bg-white shadow-2xl">
                <div class="admin-panel-header flex items-center justify-between border-b p-5">
                    <div>
                        <h3 class="text-lg font-black text-emerald-950">{{ $form['id'] ? 'Edit' : 'Tambah' }}
                            {{ $title }}</h3>
                        <p class="text-sm text-zinc-500">Isi data lalu simpan perubahan.</p>
                    </div>
                    <button type="button" wire:click="closeModal"
                        class="grid size-11 place-items-center rounded-md border border-emerald-950/15"
                        aria-label="Tutup modal">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit="save" class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-xl border border-emerald-950/10 bg-[#f4f7f1] p-4 sm:col-span-2 lg:col-span-3">
                        <div class="flex items-start gap-3">
                            <div
                                class="grid size-10 shrink-0 place-items-center rounded-md bg-emerald-100 text-emerald-700">
                                <i class="fa-solid {{ $form['parent_id'] ? 'fa-turn-up rotate-90' : 'fa-bars' }}"></i>
                            </div>
                            <div>
                                <div class="font-black">{{ $form['parent_id'] ? 'Submenu' : 'Menu Utama' }}</div>
                                <p class="mt-1 text-sm text-zinc-500">Pilih menu induk jika item ini ingin ditampilkan
                                    sebagai submenu.</p>
                            </div>
                        </div>
                    </div>

                    <x-admin.input label="Label Menu" model="form.label" class="lg:col-span-3" />
                    <x-admin.select label="Menu Induk" model="form.parent_id" :options="collect($parentMenuOptions)
                        ->reject(fn($item) => (int) $item['id'] === (int) ($form['id'] ?? 0))
                        ->pluck('label', 'id')
                        ->prepend('Tidak ada - jadikan menu utama', '')
                        ->all()"
                        class="lg:col-span-2" />
                    <x-admin.select label="Status" model="form.is_active" :options="[1 => 'Aktif', 0 => 'Nonaktif']"/>
                    <x-admin.select label="Jenis Tujuan" model="form.type" :options="['url' => 'URL / Tautan', 'page' => 'Halaman Khusus']" />

                    @if ($form['type'] === 'page')
                        <x-admin.select label="Halaman Tujuan" model="form.page_id" :options="collect($pages)->pluck('title', 'id')->prepend('Pilih halaman', '')->all()"
                            class="lg:col-span-2" />
                    @else
                        <x-admin.input label="URL Tujuan" model="form.url"
                            placeholder="/alamat-halaman atau https://..." class="lg:col-span-2" />
                    @endif

                    <x-admin.select label="Buka Tautan" model="form.target" :options="['_self' => 'Di tab yang sama', '_blank' => 'Di tab baru']" />

                    <div
                        class="flex justify-end gap-2 border-t border-emerald-950/10 pt-5 sm:col-span-2 lg:col-span-3">
                        <button type="button" wire:click="closeModal"
                            class="inline-flex min-h-11 items-center gap-2 rounded-md border border-emerald-950/15 px-4 text-sm font-bold"><i
                                class="fa-solid fa-xmark"></i>Batal</button>
                        <button type="submit"
                            class="admin-btn-primary inline-flex min-h-11 items-center gap-2 rounded-md px-5 text-sm font-black text-white"><i
                                class="fa-solid fa-floppy-disk"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
