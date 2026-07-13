<?php

use App\Support\CurrentVillage;
use App\Support\PublicSiteCache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public array $pages = [];

    public int $villageId = 1;

    public array $village = [];

    public int $page = 1;

    public int $perPage = 5;

    public int $totalPages = 0;

    public string $search = '';

    public function mount(): void
    {
        $this->villageId = CurrentVillage::id();
        $this->village = (array) DB::table('villages')->where('id', $this->villageId)->first();
        $this->loadPages();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->loadPages();
    }

    public function resetSearch(): void
    {
        $this->search = '';
        $this->page = 1;
        $this->loadPages();
    }

    public function delete(int $id): void
    {
        DB::table('pages')->where('village_id', $this->villageId)->where('id', $id)->delete();
        PublicSiteCache::forget($this->villageId);
        $this->loadPages();
    }

    public function previousPage(): void
    {
        $this->page = max($this->page - 1, 1);
        $this->loadPages();
    }

    public function nextPage(): void
    {
        $this->page = min($this->page + 1, max((int) ceil($this->totalPages / $this->perPage), 1));
        $this->loadPages();
    }

    private function loadPages(): void
    {
        $query = DB::table('pages')
            ->where('village_id', $this->villageId)
            ->when(trim($this->search) !== '', function ($query): void {
                $search = '%' . strtolower(trim($this->search)) . '%';
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(title) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(excerpt, \'\')) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(status) LIKE ?', [$search]);
                });
            });

        $this->totalPages = (clone $query)->count();
        $this->page = min($this->page, max((int) ceil($this->totalPages / $this->perPage), 1));
        $this->pages = $query->orderByDesc('updated_at')->forPage($this->page, $this->perPage)->get()->map(fn($row): array => (array) $row)->all();
    }
};
?>

<section class="admin-panel overflow-hidden border bg-white">
    <div class="admin-panel-header flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="flex items-center gap-2 font-black text-emerald-950"><i
                    class="fa-solid fa-file-lines text-amber-600"></i>Daftar Halaman</h2>
            <p class="text-sm text-zinc-500">Tambah/edit halaman dilakukan di halaman.</p>
        </div>
        <a href="{{ route('admin.pages.create') }}"
            class="admin-btn-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-4 text-sm font-black text-white"><i
                class="fa-solid fa-plus"></i>Tambah Halaman</a>
    </div>
    <div class="grid gap-3 border-b border-zinc-200 p-5 sm:grid-cols-[minmax(0,1fr)_auto]">
        <x-admin.input label="Cari Halaman" model="search" placeholder="Judul, slug, ringkasan, atau status" />
        <button type="button" wire:click="resetSearch"
            class="inline-flex min-h-11 items-center justify-center gap-2 self-end rounded-md border border-zinc-300 px-3 text-sm font-bold text-zinc-700">
            <i class="fa-solid fa-rotate-left"></i>
            Reset
        </button>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                <tr>
                    <th class="px-5 py-3">Judul</th>
                    <th class="px-5 py-3">Link</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="w-44 px-5 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
                @foreach ($pages as $pageItem)
                    <tr>
                        <td class="px-5 py-4">
                            <div class="flex min-w-64 items-center gap-3">
                                <div
                                    class="grid h-14 w-20 shrink-0 place-items-center overflow-hidden rounded-md border border-zinc-200 bg-zinc-100 text-zinc-400">
                                    @if ($pageItem['featured_image_url'])
                                        <img src="{{ $pageItem['featured_image_url'] }}" alt="" loading="lazy"
                                            class="h-full w-full object-cover">
                                    @else
                                        <i class="fa-solid fa-file-lines"></i>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-semibold text-zinc-900">{{ $pageItem['title'] }}</div>
                                    <div class="mt-1 line-clamp-1 text-xs text-zinc-500">
                                        {{ $pageItem['excerpt'] ?: 'Belum ada ringkasan halaman.' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-zinc-500">
                            <a href="{{ ($village['website_url'] ?? 'http://localhost:3000') . '/halaman/' . $pageItem['slug'] }}"
                                target="_blank"
                                title="{{ ($village['website_url'] ?? 'http://localhost:3000') . '/halaman/' . $pageItem['slug'] }}"
                                class="inline-flex items-center gap-1 rounded bg-zinc-100 px-2 py-1 text-xs font-bold text-zinc-700 max-w-[200px] truncate">
                                <i class="fa-solid fa-link"></i>
                                Buka Halaman
                            </a>
                        </td>
                        <td class="px-5 py-4"><x-admin.pill :value="$pageItem['status']" /></td>
                        <td class="px-5 py-4">
                            <div class="flex gap-2">
                                <a href="{{ route('admin.pages.edit', $pageItem['id']) }}"
                                    class="inline-flex min-h-9 items-center gap-2 rounded bg-zinc-100 px-3 text-xs font-bold"><i
                                        class="fa-solid fa-pen"></i>Edit</a>
                                <button wire:click="delete({{ $pageItem['id'] }})" wire:confirm="Hapus halaman ini?"
                                    class="inline-flex min-h-9 items-center gap-2 rounded bg-red-50 px-3 text-xs font-bold text-red-700"><i
                                        class="fa-solid fa-trash"></i>Hapus</button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="flex items-center justify-between gap-4 border-t border-zinc-200 px-5 py-4 text-sm">
        <span class="font-semibold text-zinc-500">{{ $totalPages }} data · Halaman {{ $page }}</span>
        <div class="flex gap-2">
            <button type="button" wire:click="previousPage" @disabled($page <= 1)
                class="rounded-md border border-zinc-200 px-3 py-2 font-bold disabled:opacity-40">Sebelumnya</button>
            <button type="button" wire:click="nextPage" @disabled($page >= max((int) ceil($totalPages / $perPage), 1))
                class="rounded-md border border-zinc-200 px-3 py-2 font-bold disabled:opacity-40">Berikutnya</button>
        </div>
    </div>
</section>
