<?php

use App\Services\OptimizedImageStorage;
use App\Support\CurrentVillage;
use App\Support\PublicSiteCache;
use Illuminate\Support\Facades\DB;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public array $banners = [];

    public int $villageId = 1;

    public bool $showModal = false;

    public $imageUpload = null;

    public $portraitImageUpload = null;

    public array $form = [
        'id' => null,
        'title' => '',
        'subtitle' => '',
        'image_url' => '',
        'portrait_image_url' => '',
        'button_label' => '',
        'button_url' => '',
        'sort_order' => 0,
        'is_active' => true,
    ];

    public function mount(): void
    {
        $this->villageId = CurrentVillage::id();
        $this->loadData();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $banner = DB::table('hero_banners')->where('village_id', $this->villageId)->where('id', $id)->first();

        if (!$banner) {
            return;
        }

        $this->form = array_merge($this->form, (array) $banner);
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $data = $this->validate(
            [
                'form.title' => ['required', 'string', 'max:255'],
                'form.subtitle' => ['nullable', 'string'],
                'form.button_label' => ['nullable', 'string', 'max:80'],
                'form.button_url' => ['nullable', 'string', 'max:255'],
                'form.sort_order' => ['nullable', 'integer', 'min:0'],
                'form.is_active' => ['boolean'],
                'imageUpload' => [$this->form['id'] ? 'nullable' : 'required', 'image', 'max:20000'], // max 20MB
                'portraitImageUpload' => ['nullable', 'image', 'max:12000'],
            ],
            [],
            [
                'form.title' => 'Judul Banner',
                'form.subtitle' => 'Sub Judul',
                'form.button_label' => 'Label Tombol',
                'form.button_url' => 'Tautan Tombol',
                'form.sort_order' => 'Urutan',
                'form.is_active' => 'Status',
                'imageUpload' => 'Gambar Landscape',
                'portraitImageUpload' => 'Gambar Portrait',
            ],
        )['form'];

        if ($this->imageUpload) {
            $data['image_url'] = app(OptimizedImageStorage::class)->replace($this->imageUpload, 'hero-banners', $this->form['image_url'] ?: null, 'banner_landscape');
        } else {
            $data['image_url'] = $this->form['image_url'];
        }

        if ($this->portraitImageUpload) {
            $data['portrait_image_url'] = app(OptimizedImageStorage::class)->replace($this->portraitImageUpload, 'hero-banners/mobile', $this->form['portrait_image_url'] ?: null, 'banner_portrait');
        } else {
            $data['portrait_image_url'] = $this->form['portrait_image_url'] ?: null;
        }

        $payload = [...$data, 'village_id' => $this->villageId, 'sort_order' => (int) ($data['sort_order'] ?: 0), 'is_active' => (bool) $data['is_active'], 'updated_at' => now()];

        if ($this->form['id']) {
            DB::table('hero_banners')->where('village_id', $this->villageId)->where('id', $this->form['id'])->update($payload);
        } else {
            DB::table('hero_banners')->insert([...$payload, 'created_at' => now()]);
        }

        PublicSiteCache::forget($this->villageId);

        $this->showModal = false;
        $this->resetForm();
        $this->loadData();
        LivewireAlert::title('Tersimpan')->text('Banner hero berhasil disimpan.')->success()->timer(1200)->show();
    }

    public function delete(int $id): void
    {
        $banner = DB::table('hero_banners')->where('village_id', $this->villageId)->where('id', $id)->first();

        if ($banner) {
            $images = app(OptimizedImageStorage::class);
            $images->delete($banner->image_url);
            $images->delete($banner->portrait_image_url);
            DB::table('hero_banners')->where('id', $id)->delete();
        }

        PublicSiteCache::forget($this->villageId);
        $this->loadData();
    }

    private function loadData(): void
    {
        $this->banners = DB::table('hero_banners')->where('village_id', $this->villageId)->orderBy('sort_order')->orderByDesc('created_at')->get()->map(fn($row): array => (array) $row)->all();
    }

    private function resetForm(): void
    {
        $this->reset('imageUpload', 'portraitImageUpload');
        $this->form = [
            'id' => null,
            'title' => '',
            'subtitle' => '',
            'image_url' => '',
            'portrait_image_url' => '',
            'button_label' => '',
            'button_url' => '',
            'sort_order' => count($this->banners) + 1,
            'is_active' => true,
        ];
    }
};
?>

<div class="space-y-5">
    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-zinc-200 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-black">Banner Hero</h2>
                <p class="text-sm text-zinc-500">Jika banner aktif lebih dari satu, Beranda akan menampilkannya sebagai
                    carousel.</p>
            </div>
            <button type="button" wire:click="create"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-black text-white"><i class="fa-solid fa-plus"></i>Tambah
                Banner</button>
        </div>

        <div class="grid gap-4 p-5 lg:grid-cols-2">
            @forelse($banners as $banner)
                <article class="overflow-hidden rounded-lg border border-zinc-200">
                    <div
                        class="{{ $banner['portrait_image_url'] ? 'grid grid-cols-[minmax(0,1fr)_112px]' : '' }} gap-2 bg-zinc-100 p-2">
                        <div class="relative overflow-hidden rounded-md bg-zinc-200">
                            <img src="{{ $banner['image_url'] }}" alt="Preview landscape {{ $banner['title'] }}"
                                class="h-48 w-full object-cover">
                            <span
                                class="absolute bottom-2 left-2 rounded-full bg-zinc-950/75 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-white backdrop-blur">Landscape</span>
                        </div>
                        @if ($banner['portrait_image_url'])
                            <div class="relative overflow-hidden rounded-md bg-zinc-200">
                                <img src="{{ $banner['portrait_image_url'] }}"
                                    alt="Preview portrait {{ $banner['title'] }}" class="h-48 w-full object-cover">
                                <span
                                    class="absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-zinc-950/75 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-white backdrop-blur">Portrait</span>
                            </div>
                        @endif
                    </div>
                    <div class="p-4">
                        <div class="flex flex-wrap gap-2 text-xs font-black uppercase">
                            <span class="rounded bg-zinc-100 px-2 py-1">Urutan {{ $banner['sort_order'] }}</span>
                            <span
                                class="rounded {{ $banner['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }} px-2 py-1">{{ $banner['is_active'] ? 'Aktif' : 'Nonaktif' }}</span>
                        </div>
                        <h3 class="mt-3 text-lg font-black">{{ $banner['title'] }}</h3>
                        <p class="mt-1 line-clamp-2 text-sm text-zinc-600">{{ $banner['subtitle'] }}</p>
                        <div class="mt-4 flex gap-2">
                            <button type="button" wire:click="edit({{ $banner['id'] }})"
                                class="inline-flex items-center gap-2 rounded bg-zinc-100 px-3 py-2 text-xs font-bold"><i class="fa-solid fa-pen"></i>Edit</button>
                            <button type="button" wire:click="delete({{ $banner['id'] }})"
                                wire:confirm="Hapus banner ini?"
                                class="inline-flex items-center gap-2 rounded bg-red-50 px-3 py-2 text-xs font-bold text-red-700"><i class="fa-solid fa-trash"></i>Hapus</button>
                        </div>
                    </div>
                </article>
            @empty
                <div
                    class="rounded-lg border border-dashed border-zinc-300 p-10 text-center text-zinc-500 lg:col-span-2">
                    Belum ada banner hero.</div>
            @endforelse
        </div>
    </section>

    @if ($showModal)
        <div x-data @click.self="$wire.closeModal()" @keydown.escape.window="$wire.closeModal()"
            class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 p-4" role="dialog"
            aria-modal="true">
            <div class="max-h-[90dvh] w-full max-w-4xl overflow-y-auto rounded-lg bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                    <div>
                        <h3 class="text-lg font-black">{{ $form['id'] ? 'Edit' : 'Tambah' }} Banner</h3>
                        <p class="text-sm text-zinc-500">Landscape untuk desktop, portrait opsional untuk tampilan
                            mobile.</p>
                    </div>
                    <button type="button" wire:click="closeModal"
                        class="grid size-11 place-items-center rounded-md border border-zinc-300"
                        aria-label="Tutup modal">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit="save" class="grid gap-4 p-5 sm:grid-cols-2">
                    <x-admin.input label="Judul" model="form.title" />
                    <x-admin.input label="Urutan" model="form.sort_order" type="number" />
                    <x-admin.textarea label="Subjudul" model="form.subtitle" class="sm:col-span-2" />
                    <x-admin.input label="Label Tombol" model="form.button_label"
                        placeholder="contoh: Baca Kabar Desa" />
                    <x-admin.input label="URL Tombol" model="form.button_url" placeholder="/artikel atau https://..." />
                    <x-admin.select label="Status" model="form.is_active" :options="[1 => 'Aktif', 0 => 'Nonaktif']" />
                    <div class="sm:col-span-2 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="flex items-center gap-2 text-sm font-bold"><i class="fa-solid fa-image text-amber-600"></i>Gambar Landscape <span
                                    class="text-red-600">*</span></label>
                            <p class="mt-1 text-xs text-zinc-500">Desktop/tablet. Rekomendasi 1920×1080 px.</p>
                            <input type="file" wire:model="imageUpload" accept="image/*"
                                class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                            @error('imageUpload')
                                <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                            @enderror
                            @if ($imageUpload)
                                <img src="{{ $imageUpload->temporaryUrl() }}" alt="Preview banner"
                                    class="mt-2 h-36 w-full rounded-md object-cover">
                            @elseif($form['image_url'])
                                <img src="{{ $form['image_url'] }}" alt="Banner saat ini"
                                    class="mt-2 h-36 w-full rounded-md object-cover">
                            @endif
                        </div>
                        <div>
                            <label class="flex items-center gap-2 text-sm font-bold"><i class="fa-solid fa-mobile-screen text-amber-600"></i>Gambar Portrait <span
                                    class="font-normal text-zinc-500">(opsional)</span></label>
                            <p class="mt-1 text-xs text-zinc-500">Mobile. Rekomendasi 1080×1440 px.</p>
                            <input type="file" wire:model="portraitImageUpload" accept="image/*"
                                class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                            @error('portraitImageUpload')
                                <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                            @enderror
                            @if ($portraitImageUpload)
                                <img src="{{ $portraitImageUpload->temporaryUrl() }}" alt="Preview banner mobile"
                                    class="mt-2 h-36 w-full rounded-md object-cover">
                            @elseif($form['portrait_image_url'])
                                <img src="{{ $form['portrait_image_url'] }}" alt="Banner mobile saat ini"
                                    class="mt-2 h-36 w-full rounded-md object-cover">
                            @else
                                <div
                                    class="mt-2 grid h-36 place-items-center rounded-md border border-dashed border-zinc-300 bg-zinc-50 px-4 text-center text-xs text-zinc-500">
                                    Jika kosong, mobile menggunakan gambar landscape.</div>
                            @endif
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-zinc-200 pt-5 sm:col-span-2">
                        <button type="button" wire:click="closeModal"
                            class="inline-flex min-h-11 items-center gap-2 rounded-md border border-zinc-300 px-4 text-sm font-bold"><i class="fa-solid fa-xmark"></i>Batal</button>
                        <button
                            class="inline-flex min-h-11 items-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-black text-white"><i class="fa-solid fa-floppy-disk"></i>Simpan
                            Banner</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
