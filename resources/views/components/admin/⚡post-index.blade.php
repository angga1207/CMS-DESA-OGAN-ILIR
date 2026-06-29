<?php

use App\Support\CurrentVillage;
use App\Support\PublicSiteCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public array $posts = [];

    public int $villageId = 1;

    public int $page = 1;

    public int $perPage = 5;

    public int $totalPosts = 0;

    public string $search = '';

    public string $statusFilter = '';

    public string $categoryFilter = '';

    public string $sourceFilter = '';

    public string $sortBy = 'updated_at';

    public string $sortDirection = 'desc';

    public array $categoryOptions = [];

    public array $sourceOptions = [];

    public function mount(): void
    {
        $this->villageId = CurrentVillage::id();
        $this->categoryOptions = DB::table('content_categories')->where('village_id', $this->villageId)->orderBy('name')->pluck('name', 'id')->prepend('Semua kategori', '')->all();
        $this->sourceOptions = DB::table('content_sources')->where('village_id', $this->villageId)->orderBy('name')->pluck('name', 'code')->prepend('Semua sumber', '')->all();
        $this->loadPosts();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'categoryFilter', 'sourceFilter', 'sortBy', 'sortDirection'], true)) {
            $this->page = 1;
            $this->loadPosts();
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->categoryFilter = '';
        $this->sourceFilter = '';
        $this->sortBy = 'updated_at';
        $this->sortDirection = 'desc';
        $this->page = 1;
        $this->loadPosts();
    }

    public function delete(int $id): void
    {
        DB::table('posts')->where('village_id', $this->villageId)->where('id', $id)->delete();
        PublicSiteCache::forget($this->villageId);
        $this->loadPosts();
    }

    public function previousPage(): void
    {
        $this->page = max($this->page - 1, 1);
        $this->loadPosts();
    }

    public function nextPage(): void
    {
        $this->page = min($this->page + 1, max((int) ceil($this->totalPosts / $this->perPage), 1));
        $this->loadPosts();
    }

    private function loadPosts(): void
    {
        $query = DB::table('posts')
            ->leftJoin('content_categories', 'posts.category_id', '=', 'content_categories.id')
            ->where('posts.village_id', $this->villageId)
            ->when(trim($this->search) !== '', function ($query): void {
                $search = '%' . strtolower(trim($this->search)) . '%';
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(posts.title) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(posts.excerpt, \'\')) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(posts.slug, \'\')) LIKE ?', [$search]);
                });
            })
            ->when($this->statusFilter !== '', fn($query) => $query->where('posts.status', $this->statusFilter))
            ->when($this->categoryFilter !== '', fn($query) => $query->where('posts.category_id', $this->categoryFilter))
            ->when($this->sourceFilter !== '', fn($query) => $query->where('posts.source_type', $this->sourceFilter));

        $this->totalPosts = (clone $query)->count('posts.id');
        $this->page = min($this->page, max((int) ceil($this->totalPosts / $this->perPage), 1));

        $sortColumns = [
            'title' => 'posts.title',
            'updated_at' => 'posts.updated_at',
            'status' => 'posts.status',
            'category' => 'content_categories.name',
            'source' => 'posts.source_type',
        ];
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $this->posts = $query
            ->select('posts.*', 'content_categories.name as category_name')
            ->orderBy($sortColumns[$this->sortBy] ?? 'posts.updated_at', $direction)
            ->orderByDesc('posts.id')
            ->forPage($this->page, $this->perPage)
            ->get()
            ->map(fn($row): array => (array) $row)
            ->all();
    }
};
?>

<section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
    <div class="flex flex-col gap-3 border-b border-zinc-200 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="font-black">Daftar Artikel</h2>
            <p class="text-sm text-zinc-500">Form tambah/edit artikel dibuka di halaman.</p>
        </div>
        <a href="{{ route('admin.posts.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-md bg-emerald-600 px-4 text-sm font-black text-white"><span>Tambah Artikel</span></a>
    </div>
    <div class="grid gap-3 border-b border-zinc-200 p-5 md:grid-cols-2 xl:grid-cols-6">
        <x-admin.input label="Cari Artikel" model="search" placeholder="Judul, ringkasan, atau slug"
            class="xl:col-span-2" />
        <x-admin.select label="Status" model="statusFilter" :options="['' => 'Semua status', 'draft' => 'Draft', 'published' => 'Terbit', 'archived' => 'Diarsipkan']" />
        <x-admin.select label="Kategori" model="categoryFilter" :options="$categoryOptions" />
        <x-admin.select label="Sumber" model="sourceFilter" :options="$sourceOptions" />
        {{-- <div class="grid grid-cols-[1fr_120px] gap-2">
            <x-admin.select label="Urutkan" model="sortBy" :options="['updated_at' => 'Terakhir diubah', 'title' => 'Judul', 'category' => 'Kategori', 'source' => 'Sumber', 'status' => 'Status']" />
            <x-admin.select label="Arah" model="sortDirection" :options="['desc' => 'Z-A', 'asc' => 'A-Z']" />
        </div> --}}
        <button type="button" wire:click="resetFilters"
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-zinc-300 px-3 text-sm font-bold text-zinc-700 md:col-span-2 xl:col-span-1 xl:self-end">
            <i class="fa-solid fa-rotate-left"></i>
            Reset
        </button>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                <tr>
                    <th class="px-5 py-3">Judul</th>
                    <th class="px-5 py-3">Kategori</th>
                    <th class="px-5 py-3">Sumber</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="w-44 px-5 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
                @forelse($posts as $post)
                    <tr>
                        <td class="px-5 py-4">
                            <div class="flex min-w-64 items-center gap-3">
                                <div
                                    class="grid h-14 w-20 shrink-0 place-items-center overflow-hidden rounded-md border border-zinc-200 bg-zinc-100 text-zinc-400">
                                    <a href="{{ route('admin.posts.edit', $post['id']) }}">
                                        @if ($post['featured_image_url'])
                                            <img src="{{ $post['featured_image_url'] }}" alt="" loading="lazy"
                                                class="h-full w-full object-cover">
                                        @else
                                            <i class="fa-solid fa-newspaper"></i>
                                        @endif
                                    </a>
                                </div>
                                <div>
                                    <a href="{{ route('admin.posts.edit', $post['id']) }}">
                                        <div class="font-semibold text-zinc-900">
                                            {{ $post['title'] }}
                                        </div>
                                        <div class="mt-1 line-clamp-1 text-xs text-zinc-500">
                                            {{ $post['excerpt'] ?: 'Belum ada ringkasan artikel.' }}</div>
                                        {{-- date --}}
                                        <div class="mt-1 line-clamp-1 text-xs text-zinc-500 flex">
                                            <i class="fa-solid fa-calendar-alt"></i>
                                            {{ Carbon::parse($post['published_at'])->isoFormat('d MMM Y HH:mm [wib]') }}
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4"><x-admin.pill :value="$post['category_name']" type="category" /></td>
                        <td class="px-5 py-4"><x-admin.pill :value="$post['source_type']" /></td>
                        <td class="px-5 py-4"><x-admin.pill :value="$post['status']" /></td>
                        <td class="px-5 py-4">
                            <div class="flex gap-2">
                                <a href="{{ route('admin.posts.edit', $post['id']) }}"
                                    class="rounded bg-zinc-100 px-3 py-2 text-xs font-bold">Edit</a>
                                <button wire:click="delete({{ $post['id'] }})" wire:confirm="Hapus artikel ini?"
                                    class="rounded bg-red-50 px-3 py-2 text-xs font-bold text-red-700">Hapus</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-zinc-500">Tidak ada artikel yang cocok.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="flex items-center justify-between gap-4 border-t border-zinc-200 px-5 py-4 text-sm">
        <span class="font-semibold text-zinc-500">{{ $totalPosts }} artikel · Halaman {{ $page }}</span>
        <div class="flex gap-2">
            <button type="button" wire:click="previousPage" @disabled($page <= 1)
                class="rounded-md border border-zinc-200 px-3 py-2 font-bold disabled:opacity-40">Sebelumnya</button>
            <button type="button" wire:click="nextPage" @disabled($page >= max((int) ceil($totalPosts / $perPage), 1))
                class="rounded-md border border-zinc-200 px-3 py-2 font-bold disabled:opacity-40">Berikutnya</button>
        </div>
    </div>
</section>
