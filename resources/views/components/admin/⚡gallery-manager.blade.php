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

    public array $albums = [];

    public array $photos = [];

    public int $villageId = 1;

    public bool $showAlbumModal = false;

    public ?int $selectedAlbumId = null;

    public $coverUpload = null;

    public array $photoUploads = [];

    public string $photoTitle = '';

    public string $photoCaption = '';

    public array $albumForm = [
        'id' => null,
        'title' => '',
        'slug' => '',
        'description' => '',
        'cover_image_url' => '',
        'album_date' => '',
    ];

    public function mount(): void
    {
        $this->villageId = CurrentVillage::id();
        $this->loadAlbums();
    }

    public function createAlbum(): void
    {
        $this->resetAlbumForm();
        $this->showAlbumModal = true;
    }

    public function editAlbum(int $id): void
    {
        $album = DB::table('gallery_albums')->where('village_id', $this->villageId)->where('id', $id)->first();

        if (!$album) {
            return;
        }

        $this->albumForm = array_merge($this->albumForm, (array) $album);
        $this->showAlbumModal = true;
    }

    public function closeAlbumModal(): void
    {
        $this->showAlbumModal = false;
        $this->resetAlbumForm();
    }

    public function saveAlbum(): void
    {
        $data = $this->validate(
            [
                'albumForm.title' => ['required', 'string', 'max:255'],
                'albumForm.description' => ['nullable', 'string'],
                'albumForm.album_date' => ['nullable', 'date'],
                'coverUpload' => ['nullable', 'image', 'max:4096'],
            ],
            [],
            [
                'albumForm.title' => 'Judul Galeri',
                'albumForm.description' => 'Deskripsi',
                'albumForm.album_date' => 'Tanggal Album',
                'coverUpload' => 'Cover Album',
            ],
        )['albumForm'];

        if ($this->coverUpload) {
            $data['cover_image_url'] = app(OptimizedImageStorage::class)->replace($this->coverUpload, 'gallery-covers', $this->albumForm['cover_image_url'] ?: null, 'content_thumbnail');
        }

        $payload = [...$data, 'village_id' => $this->villageId, 'slug' => UniqueSlug::make('gallery_albums', $data['title'], $this->albumForm['id']), 'album_date' => $data['album_date'] ?: null, 'updated_at' => now()];

        if ($this->albumForm['id']) {
            DB::table('gallery_albums')->where('village_id', $this->villageId)->where('id', $this->albumForm['id'])->update($payload);
        } else {
            $this->selectedAlbumId = DB::table('gallery_albums')->insertGetId([...$payload, 'created_at' => now()]);
        }

        PublicSiteCache::forget($this->villageId);

        $this->showAlbumModal = false;
        $this->resetAlbumForm();
        $this->loadAlbums();
        LivewireAlert::title('Tersimpan')->text('Album galeri berhasil disimpan.')->success()->timer(1200)->show();
    }

    public function deleteAlbum(int $id): void
    {
        $album = DB::table('gallery_albums')->where('village_id', $this->villageId)->where('id', $id)->first();

        if ($album) {
            $images = app(OptimizedImageStorage::class);
            $images->delete($album->cover_image_url);
            DB::table('gallery_photos')->where('album_id', $id)->pluck('image_url')->each(fn(?string $url) => $images->delete($url));
            DB::table('gallery_albums')->where('id', $id)->delete();
        }

        PublicSiteCache::forget($this->villageId);

        if ($this->selectedAlbumId === $id) {
            $this->selectedAlbumId = null;
            $this->photos = [];
        }

        $this->loadAlbums();
    }

    public function selectAlbum(int $id): void
    {
        if (!DB::table('gallery_albums')->where('village_id', $this->villageId)->where('id', $id)->exists()) {
            return;
        }

        $this->selectedAlbumId = $id;
        $this->loadPhotos();
    }

    public function savePhotos(): void
    {
        $this->validate(
            [
                'selectedAlbumId' => ['required', 'integer'],
                'photoUploads' => ['required', 'array', 'min:1'],
                'photoUploads.*' => ['image', 'max:4096'],
                'photoTitle' => ['nullable', 'string', 'max:255'],
                'photoCaption' => ['nullable', 'string'],
            ],
            [],
            [
                'photoUploads' => 'Foto',
                'photoUploads.*' => 'Foto',
                'photoTitle' => 'Judul Foto',
                'photoCaption' => 'Caption Foto',
            ],
        );

        $sort = (int) DB::table('gallery_photos')->where('album_id', $this->selectedAlbumId)->max('sort_order');

        foreach ($this->photoUploads as $upload) {
            $sort++;

            DB::table('gallery_photos')->insert([
                'village_id' => $this->villageId,
                'album_id' => $this->selectedAlbumId,
                'title' => $this->photoTitle ?: 'Foto Gallery ' . $sort,
                'image_url' => app(OptimizedImageStorage::class)->store($upload, 'gallery-photos', 'gallery'),
                'caption' => $this->photoCaption ?: null,
                'sort_order' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        PublicSiteCache::forget($this->villageId);

        $this->reset(['photoUploads', 'photoTitle', 'photoCaption']);
        $this->loadPhotos();
        $this->loadAlbums();
        LivewireAlert::title('Terunggah')->text('Foto galeri berhasil ditambahkan.')->success()->timer(1200)->show();
    }

    public function deletePhoto(int $id): void
    {
        $photo = DB::table('gallery_photos')->where('village_id', $this->villageId)->where('id', $id)->first();

        if ($photo) {
            app(OptimizedImageStorage::class)->delete($photo->image_url);
            DB::table('gallery_photos')->where('id', $id)->delete();
        }

        PublicSiteCache::forget($this->villageId);
        $this->loadPhotos();
        $this->loadAlbums();
    }

    private function loadAlbums(): void
    {
        $this->albums = DB::table('gallery_albums')->leftJoin('gallery_photos', 'gallery_albums.id', '=', 'gallery_photos.album_id')->where('gallery_albums.village_id', $this->villageId)->select('gallery_albums.*', DB::raw('count(gallery_photos.id) as photos_count'))->groupBy('gallery_albums.id')->orderByDesc('gallery_albums.album_date')->orderByDesc('gallery_albums.created_at')->get()->map(fn($row): array => (array) $row)->all();

        if (!$this->selectedAlbumId && count($this->albums)) {
            $this->selectedAlbumId = $this->albums[0]['id'];
        }

        $this->loadPhotos();
    }

    private function loadPhotos(): void
    {
        $this->photos = $this->selectedAlbumId ? DB::table('gallery_photos')->where('village_id', $this->villageId)->where('album_id', $this->selectedAlbumId)->orderBy('sort_order')->get()->map(fn($row): array => (array) $row)->all() : [];
    }

    private function resetAlbumForm(): void
    {
        $this->reset('coverUpload');
        $this->albumForm = [
            'id' => null,
            'title' => '',
            'slug' => '',
            'description' => '',
            'cover_image_url' => '',
            'album_date' => now()->toDateString(),
        ];
    }
};
?>

<div class="grid gap-5 xl:grid-cols-[380px_1fr]">
    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-zinc-200 p-5">
            <div>
                <h2 class="font-black">Album Galeri</h2>
                <p class="text-sm text-zinc-500">Album kegiatan dan dokumentasi desa.</p>
            </div>
            <button type="button" wire:click="createAlbum"
                class="inline-flex min-h-11 items-center rounded-md bg-emerald-600 px-4 text-sm font-black text-white">Tambah</button>
        </div>

        <div class="divide-y divide-zinc-200">
            @forelse($albums as $album)
                <div class="p-4">
                    <button type="button" wire:click="selectAlbum({{ $album['id'] }})"
                        class="flex w-full items-center gap-3 rounded-md p-2 text-left {{ $selectedAlbumId === $album['id'] ? 'bg-emerald-50' : 'hover:bg-zinc-50' }}">
                        <img src="{{ $album['cover_image_url'] ?: 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=500&q=80' }}"
                            alt="{{ $album['title'] }}" class="size-16 rounded-md object-cover">
                        <span>
                            <span class="block font-black">{{ $album['title'] }}</span>
                            <span class="block text-sm text-zinc-500">{{ $album['photos_count'] }} foto</span>
                        </span>
                    </button>
                    <div class="mt-2 flex gap-2 pl-2">
                        <button type="button" wire:click="editAlbum({{ $album['id'] }})"
                            class="rounded bg-zinc-100 px-3 py-2 text-xs font-bold">Edit</button>
                        <button type="button" wire:click="deleteAlbum({{ $album['id'] }})"
                            wire:confirm="Hapus album dan semua foto?"
                            class="rounded bg-red-50 px-3 py-2 text-xs font-bold text-red-700">Hapus</button>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-zinc-500">Belum ada album galeri.</div>
            @endforelse
        </div>
    </section>

    <section class="space-y-5">
        <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="font-black">Upload Foto</h2>
            <form wire:submit="savePhotos" class="mt-4 grid gap-4 lg:grid-cols-3">
                <x-admin.input label="Judul Foto" model="photoTitle" placeholder="opsional" />
                <x-admin.input label="Caption" model="photoCaption" placeholder="opsional" />
                <div>
                    <label class="text-sm font-bold">File Foto</label>
                    <input type="file" wire:model="photoUploads" accept="image/*" multiple
                        class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                    @error('photoUploads')
                        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                    @enderror
                    @error('photoUploads.*')
                        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                    @enderror
                    <div wire:loading wire:target="photoUploads" class="mt-1 text-sm text-zinc-500">Menyiapkan foto...
                    </div>
                </div>
                <div class="lg:col-span-3">
                    <button
                        class="inline-flex min-h-11 items-center rounded-md bg-emerald-600 px-4 text-sm font-black text-white">Upload
                        Foto</button>
                </div>
            </form>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="font-black">Foto Album</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($photos as $photo)
                    <article class="overflow-hidden rounded-lg border border-zinc-200">
                        <img src="{{ $photo['image_url'] }}" alt="{{ $photo['title'] }}"
                            class="h-44 w-full object-cover">
                        <div class="p-4">
                            <h3 class="font-black">{{ $photo['title'] }}</h3>
                            <p class="mt-1 line-clamp-2 text-sm text-zinc-500">{{ $photo['caption'] }}</p>
                            <button type="button" wire:click="deletePhoto({{ $photo['id'] }})"
                                wire:confirm="Hapus foto ini?"
                                class="mt-3 rounded bg-red-50 px-3 py-2 text-xs font-bold text-red-700">Hapus
                                Foto</button>
                        </div>
                    </article>
                @empty
                    <div
                        class="rounded-lg border border-dashed border-zinc-300 p-8 text-center text-zinc-500 sm:col-span-2 xl:col-span-3">
                        Pilih album lalu upload foto.</div>
                @endforelse
            </div>
        </div>
    </section>

    @if ($showAlbumModal)
        <div x-data @click.self="$wire.closeAlbumModal()" @keydown.escape.window="$wire.closeAlbumModal()"
            class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 p-4" role="dialog"
            aria-modal="true">
            <div class="max-h-[90dvh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                    <div>
                        <h3 class="text-lg font-black">{{ $albumForm['id'] ? 'Edit' : 'Tambah' }} Album</h3>
                        <p class="text-sm text-zinc-500">Atur judul, cover, dan tanggal album.</p>
                    </div>
                    <button type="button" wire:click="closeAlbumModal"
                        class="grid size-11 place-items-center rounded-md border border-zinc-300"
                        aria-label="Tutup modal">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit="saveAlbum" class="grid gap-4 p-5 sm:grid-cols-2">
                    <x-admin.input label="Judul Album" model="albumForm.title" />
                    <x-admin.input label="Tanggal Album" model="albumForm.album_date" type="date" />
                    <div>
                        <label class="text-sm font-bold">Cover Album</label>
                        <input type="file" wire:model="coverUpload" accept="image/*"
                            class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                        @error('coverUpload')
                            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                        @enderror
                        @if ($coverUpload)
                            <img src="{{ $coverUpload->temporaryUrl() }}" alt="Preview cover"
                                class="mt-2 h-28 w-full rounded-md object-cover">
                        @elseif($albumForm['cover_image_url'])
                            <img src="{{ $albumForm['cover_image_url'] }}" alt="Cover saat ini"
                                class="mt-2 h-28 w-full rounded-md object-cover">
                        @endif
                    </div>
                    <x-admin.textarea label="Deskripsi" model="albumForm.description" class="sm:col-span-2" />
                    <div class="flex justify-end gap-2 border-t border-zinc-200 pt-5 sm:col-span-2">
                        <button type="button" wire:click="closeAlbumModal"
                            class="inline-flex min-h-11 items-center rounded-md border border-zinc-300 px-4 text-sm font-bold">Batal</button>
                        <button
                            class="inline-flex min-h-11 items-center rounded-md bg-emerald-600 px-4 text-sm font-black text-white">Simpan
                            Album</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
