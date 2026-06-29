<?php

use App\Support\CurrentVillage;
use App\Support\PublicSiteCache;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

new class extends Component
{
    public string $reference;
    public string $title = '';
    public bool $showModal = false;
    public int $villageId = 1;
    public array $rows = [];
    public array $columns = [];
    public array $form = [];
    public string $search = '';

    private array $configs = [
        'content-categories' => [
            'title' => 'Kategori Berita',
            'table' => 'content_categories',
            'columns' => ['name' => 'Nama', 'type' => 'Tipe'],
            'defaults' => ['id' => null, 'name' => '', 'slug' => '', 'type' => 'article'],
        ],
        'content-sources' => [
            'title' => 'Sumber Artikel',
            'table' => 'content_sources',
            'columns' => ['name' => 'Nama', 'code' => 'Kode', 'sort_order' => 'Urutan'],
            'defaults' => ['id' => null, 'name' => '', 'code' => '', 'sort_order' => 0, 'is_active' => true],
        ],
    ];

    public function mount(string $reference): void
    {
        abort_unless(array_key_exists($reference, $this->configs), 404);

        $this->reference = $reference;
        $this->villageId = CurrentVillage::id();
        $this->title = $this->config('title');
        $this->columns = $this->config('columns');
        $this->resetForm();
        $this->loadData();
    }

    public function updatedSearch(): void
    {
        $this->loadData();
    }

    public function resetSearch(): void
    {
        $this->search = '';
        $this->loadData();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $row = DB::table($this->config('table'))->where('village_id', $this->villageId)->where('id', $id)->first();

        if (! $row) {
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
        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['nullable', 'string', 'max:60'],
            'form.type' => ['nullable', 'string', 'max:40'],
            'form.color' => ['nullable', 'string', 'max:30'],
            'form.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $payload = $this->payload();
        $id = $this->form['id'] ?? null;

        if ($id) {
            DB::table($this->config('table'))->where('village_id', $this->villageId)->where('id', $id)->update([...$payload, 'updated_at' => now()]);
        } else {
            DB::table($this->config('table'))->insert([...$payload, 'created_at' => now(), 'updated_at' => now()]);
        }

        PublicSiteCache::forget($this->villageId);

        $this->showModal = false;
        $this->resetForm();
        $this->loadData();
        LivewireAlert::title('Tersimpan')->text('Referensi berhasil disimpan.')->success()->timer(1200)->show();
    }

    public function delete(int $id): void
    {
        DB::table($this->config('table'))->where('village_id', $this->villageId)->where('id', $id)->delete();
        PublicSiteCache::forget($this->villageId);
        $this->loadData();
    }

    private function loadData(): void
    {
        $this->rows = DB::table($this->config('table'))
            ->where('village_id', $this->villageId)
            ->when(trim($this->search) !== '', function ($query): void {
                $search = '%'.strtolower(trim($this->search)).'%';
                $query->where(function ($query) use ($search): void {
                    foreach (array_keys($this->columns) as $column) {
                        $query->orWhereRaw("LOWER(CAST({$column} AS TEXT)) LIKE ?", [$search]);
                    }
                });
            })
            ->when(in_array('sort_order', array_keys($this->columns), true), fn ($query) => $query->orderBy('sort_order'))
            ->orderBy('name')
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    private function resetForm(): void
    {
        $this->form = $this->config('defaults');
    }

    private function payload(): array
    {
        return match ($this->reference) {
            'content-categories' => [
                'village_id' => $this->villageId,
                'name' => $this->form['name'],
                'slug' => UniqueSlug::make('content_categories', $this->form['name'], $this->form['id']),
                'type' => $this->form['type'] ?: 'article',
            ],
            default => [
                'village_id' => $this->villageId,
                'name' => $this->form['name'],
                'code' => $this->form['code'] ?: Str::slug($this->form['name'], '_'),
                'sort_order' => (int) ($this->form['sort_order'] ?: 0),
                'is_active' => (bool) ($this->form['is_active'] ?? true),
            ],
        };
    }

    private function config(string $key): mixed
    {
        return $this->configs[$this->reference][$key];
    }
};
?>

<div class="space-y-5">
    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-zinc-200 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-black">{{ $title }}</h2>
                <p class="text-sm text-zinc-500">Master data untuk pilihan input di panel admin.</p>
            </div>
            <button type="button" wire:click="create" class="inline-flex min-h-11 items-center justify-center rounded-md bg-emerald-600 px-4 text-sm font-black text-white">Tambah Referensi</button>
        </div>

        <div class="grid gap-3 border-b border-zinc-200 p-5 sm:grid-cols-[1fr_auto] sm:items-end">
            <x-admin.input label="Cari Referensi" model="search" placeholder="Nama, kode, tipe, atau urutan" />
            <button type="button" wire:click="resetSearch" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-zinc-300 px-3 text-sm font-bold text-zinc-700">
                <i class="fa-solid fa-rotate-left"></i>
                Reset
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                    <tr>
                        @foreach($columns as $label)
                            <th class="px-5 py-3">{{ $label }}</th>
                        @endforeach
                        <th class="w-44 px-5 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @forelse($rows as $row)
                        <tr>
                            @foreach($columns as $key => $label)
                                <td class="px-5 py-4">
                                    @if($key === 'type')
                                        <x-admin.pill :value="data_get($row, $key, '-')" />
                                    @else
                                        {{ data_get($row, $key, '-') }}
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-5 py-4">
                                <div class="flex gap-2">
                                    <button type="button" wire:click="edit({{ $row['id'] }})" class="rounded bg-zinc-100 px-3 py-2 text-xs font-bold">Edit</button>
                                    <button type="button" wire:click="delete({{ $row['id'] }})" wire:confirm="Hapus referensi ini?" class="rounded bg-red-50 px-3 py-2 text-xs font-bold text-red-700">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) + 1 }}" class="px-5 py-10 text-center text-zinc-500">Belum ada data.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($showModal)
        <div x-data @click.self="$wire.closeModal()" @keydown.escape.window="$wire.closeModal()" class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-2xl rounded-lg bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                    <div>
                        <h3 class="text-lg font-black">{{ $form['id'] ? 'Edit' : 'Tambah' }} {{ $title }}</h3>
                        <p class="text-sm text-zinc-500">Isi data master lalu simpan.</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="grid size-11 place-items-center rounded-md border border-zinc-300" aria-label="Tutup modal">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit="save" class="grid gap-4 p-5 sm:grid-cols-2">
                    <x-admin.input label="Nama" model="form.name" />

                    @if($reference !== 'content-categories')
                        <x-admin.input label="Kode" model="form.code" placeholder="otomatis jika kosong" />
                        <x-admin.input label="Urutan" model="form.sort_order" type="number" />
                    @endif

                    @if($reference === 'content-categories')
                        <x-admin.select label="Tipe Konten" model="form.type" :options="['article' => 'Artikel', 'announcement' => 'Pengumuman']" />
                    @endif

                    <div class="flex justify-end gap-2 border-t border-zinc-200 pt-5 sm:col-span-2">
                        <button type="button" wire:click="closeModal" class="inline-flex min-h-11 items-center rounded-md border border-zinc-300 px-4 text-sm font-bold">Batal</button>
                        <button class="inline-flex min-h-11 items-center rounded-md bg-emerald-600 px-4 text-sm font-black text-white">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
