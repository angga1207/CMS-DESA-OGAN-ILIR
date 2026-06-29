<?php

use App\Services\OptimizedImageStorage;
use App\Support\CurrentVillage;
use App\Support\PublicSiteCache;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public ?int $id = null;

    public int $villageId = 1;

    public array $categories = [];

    public array $sources = [];

    public $thumbnailUpload = null;

    public array $form = [
        'category_id' => '',
        'title' => '',
        'slug' => '',
        'excerpt' => '',
        'body' => '',
        'featured_image_url' => '',
        'source_type' => 'village',
        'status' => 'published',
    ];

    public function mount(?int $id = null): void
    {
        $this->id = $id;
        $this->villageId = CurrentVillage::id();
        $this->categories = DB::table('content_categories')->where('village_id', $this->villageId)->orderBy('name')->get()->map(fn($row): array => (array) $row)->all();
        $this->sources = DB::table('content_sources')->where('village_id', $this->villageId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()->map(fn($row): array => (array) $row)->all();

        if ($id) {
            $post = DB::table('posts')->where('village_id', $this->villageId)->where('id', $id)->first();
            if ($post) {
                $this->form = array_merge($this->form, (array) $post);
            }
        }
    }

    public function save(): void
    {
        $data = $this->validate(
            [
                'form.category_id' => ['nullable'],
                'form.title' => ['required', 'string', 'max:255'],
                'form.excerpt' => ['nullable', 'string'],
                'form.body' => ['nullable', 'string'],
                'form.source_type' => ['required', 'string'],
                'form.status' => ['required', 'string'],
                'thumbnailUpload' => ['nullable', 'image', 'max:4096'],
            ],
            [],
            [
                'form.category_id' => 'Kategori',
                'form.title' => 'Judul Artikel',
                'form.excerpt' => 'Ringkasan',
                'form.body' => 'Konten',
                'form.source_type' => 'Sumber',
                'form.status' => 'Status',
                'thumbnailUpload' => 'Gambar Thumbnail',
            ],
        )['form'];

        if ($this->thumbnailUpload) {
            $data['featured_image_url'] = app(OptimizedImageStorage::class)->replace($this->thumbnailUpload, 'post-thumbnails', $this->form['featured_image_url'] ?: null);
        }

        $payload = [...$data, 'category_id' => $data['category_id'] ?: null, 'slug' => UniqueSlug::make('posts', $data['title'], $this->id), 'author_id' => auth()->id(), 'village_id' => $this->villageId, 'published_at' => $data['status'] === 'published' ? now() : null, 'updated_at' => now()];

        if ($this->id) {
            DB::table('posts')->where('village_id', $this->villageId)->where('id', $this->id)->update($payload);
        } else {
            $this->id = DB::table('posts')->insertGetId([...$payload, 'created_at' => now()]);
        }

        PublicSiteCache::forget($this->villageId);

        LivewireAlert::title('Tersimpan')->text('Artikel berhasil disimpan.')->success()->show();
        $this->redirectRoute('admin.posts.index', navigate: true);
    }
};
?>

<form wire:submit="save" class="grid gap-6 xl:grid-cols-[1fr_360px]">
    <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="">
            <x-admin.input label="Judul" model="form.title" />
            <x-admin.textarea label="Ringkasan" model="form.excerpt" class="sm:col-span-2" />
            <div class="sm:col-span-2">
                <label class="text-sm font-bold">Konten</label>
                <div wire:ignore class="mt-1 rounded-md border border-zinc-300 bg-white">
                    <div data-livewire-model="form.body" class="quill-editor">{!! $form['body'] !!}</div>
                </div>
                @error('form.body')
                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </section>
    <aside class="space-y-4">
        <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
            <x-admin.select label="Kategori" model="form.category_id" :options="collect($categories)->pluck('name', 'id')->prepend('Pilih kategori', '')->all()" />
            <x-admin.select label="Sumber" model="form.source_type" :options="collect($sources)->pluck('name', 'code')->all()" class="mt-4" />
            <x-admin.select label="Status" model="form.status" :options="['published' => 'Terbit', 'draft' => 'Draf']" class="mt-4" />
            <div class="mt-4">
                <label class="text-sm font-bold">Thumbnail Artikel</label>
                <p class="mt-1 text-xs text-zinc-500">Otomatis diperkecil dan dikompresi ke WebP.</p>
                <input type="file" wire:model="thumbnailUpload" accept="image/*"
                    class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                @error('thumbnailUpload')
                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                @enderror
                <div wire:loading wire:target="thumbnailUpload" class="mt-2 text-sm text-zinc-500">Mengunggah
                    thumbnail...</div>
                @if ($thumbnailUpload)
                    <img src="{{ $thumbnailUpload->temporaryUrl() }}" alt="Preview thumbnail"
                        class="mt-3 h-36 w-full rounded-md object-cover">
                @elseif($form['featured_image_url'])
                    <img src="{{ $form['featured_image_url'] }}" alt="Thumbnail saat ini"
                        class="mt-3 h-36 w-full rounded-md object-cover">
                @endif
            </div>
            <div class="mt-5 flex gap-2">
                <button
                    class="inline-flex min-h-11 flex-1 items-center justify-center rounded-md bg-emerald-600 px-4 text-sm font-black text-white">Simpan</button>
                <a href="{{ route('admin.posts.index') }}"
                    class="inline-flex min-h-11 items-center rounded-md border border-zinc-300 px-4 text-sm font-bold">Batal</a>
            </div>
        </section>
    </aside>
</form>
