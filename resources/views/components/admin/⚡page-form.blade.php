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

    public $thumbnailUpload = null;

    public array $form = ['title' => '', 'slug' => '', 'excerpt' => '', 'body' => '', 'featured_image_url' => '', 'status' => 'published'];

    public function mount(?int $id = null): void
    {
        $this->id = $id;
        $this->villageId = CurrentVillage::id();

        if ($id) {
            $page = DB::table('pages')->where('village_id', $this->villageId)->where('id', $id)->first();
            if ($page) {
                $this->form = array_merge($this->form, (array) $page);
            }
        }
    }

    public function save(): void
    {
        $data = $this->validate(
            [
                'form.title' => ['required', 'string', 'max:255'],
                'form.excerpt' => ['nullable', 'string'],
                'form.body' => ['nullable', 'string'],
                'form.status' => ['required', 'string'],
                'thumbnailUpload' => ['nullable', 'image', 'max:4096'],
            ],
            [],
            [
                'form.title' => 'Judul Halaman',
                'form.excerpt' => 'Ringkasan',
                'form.body' => 'Konten',
                'form.status' => 'Status',
                'thumbnailUpload' => 'Gambar Thumbnail',
            ],
        )['form'];

        if ($this->thumbnailUpload) {
            $data['featured_image_url'] = app(OptimizedImageStorage::class)->replace($this->thumbnailUpload, 'page-thumbnails', $this->form['featured_image_url'] ?: null);
        }

        $payload = [...$data, 'slug' => UniqueSlug::make('pages', $data['title'], $this->id), 'author_id' => auth()->id(), 'village_id' => $this->villageId, 'published_at' => $data['status'] === 'published' ? now() : null, 'updated_at' => now()];

        if ($this->id) {
            DB::table('pages')->where('village_id', $this->villageId)->where('id', $this->id)->update($payload);
        } else {
            $this->id = DB::table('pages')->insertGetId([...$payload, 'created_at' => now()]);
        }

        PublicSiteCache::forget($this->villageId);

        LivewireAlert::title('Tersimpan')->text('Halaman berhasil disimpan.')->success()->show();
        $this->redirectRoute('admin.pages.index', navigate: true);
    }
};
?>

<form wire:submit="save" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
    <section class="admin-panel border bg-white p-5">
        <div class="mb-5 border-b border-emerald-950/10 pb-4">
            <h2 class="flex items-center gap-2 font-black text-emerald-950"><i class="fa-solid fa-file-pen text-amber-600"></i>Konten Halaman</h2>
            <p class="mt-1 text-sm text-zinc-500">Susun halaman statis untuk profil, layanan, dan informasi desa.</p>
        </div>
        <div class="space-y-4">
            <x-admin.input label="Judul" model="form.title" />
            <x-admin.textarea label="Ringkasan" model="form.excerpt" class="sm:col-span-2" />
            <div class="sm:col-span-2">
                <label class="admin-field-label">Konten</label>
                <div wire:ignore class="mt-1 overflow-hidden rounded-xl border border-emerald-950/15 bg-white">
                    <div data-livewire-model="form.body" class="quill-editor">{!! $form['body'] !!}</div>
                </div>
                @error('form.body')
                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </section>
    <aside class="space-y-4">
        <section class="admin-panel border bg-white p-5">
            <div class="mb-4 border-b border-emerald-950/10 pb-4">
                <h3 class="flex items-center gap-2 font-black text-emerald-950"><i class="fa-solid fa-sliders text-amber-600"></i>Publikasi</h3>
                <p class="mt-1 text-sm text-zinc-500">Atur status dan gambar hero halaman.</p>
            </div>
            <x-admin.select label="Status" model="form.status" :options="['published' => 'Terbit', 'draft' => 'Draf']" />
            <div class="mt-4">
                <label class="admin-field-label">Gambar Hero</label>
                <p class="mt-1 text-xs text-zinc-500">Otomatis diperkecil dan dikompresi ke WebP.</p>
                <input type="file" wire:model="thumbnailUpload" accept="image/*"
                    class="admin-control mt-1">
                @error('thumbnailUpload')
                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                @enderror
                <div wire:loading wire:target="thumbnailUpload" class="mt-2 text-sm text-zinc-500">Mengunggah gambar...
                </div>
                @if ($thumbnailUpload)
                    <img src="{{ $thumbnailUpload->temporaryUrl() }}" alt="Preview hero"
                        class="mt-3 h-40 w-full rounded-xl border border-emerald-950/10 object-cover">
                @elseif($form['featured_image_url'])
                    <img src="{{ $form['featured_image_url'] }}" alt="Gambar hero saat ini"
                        class="mt-3 h-40 w-full rounded-xl border border-emerald-950/10 object-cover">
                @endif
            </div>
            <div class="mt-5 flex gap-2">
                <button
                    class="admin-btn-primary inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-md px-4 text-sm font-black text-white"><i class="fa-solid fa-floppy-disk"></i>Simpan</button>
                <a href="{{ route('admin.pages.index') }}"
                    class="inline-flex min-h-11 items-center gap-2 rounded-md border border-emerald-950/15 px-4 text-sm font-bold text-zinc-700"><i class="fa-solid fa-arrow-left"></i>Batal</a>
            </div>
        </section>
    </aside>
</form>
