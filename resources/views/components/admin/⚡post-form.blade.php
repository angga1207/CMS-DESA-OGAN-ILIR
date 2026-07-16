<?php

use App\Services\OptimizedImageStorage;
use App\Support\CurrentVillage;
use App\Support\PostRevisionHistory;
use App\Support\PublicSiteCache;
use App\Support\UniqueSlug;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    #[Locked]
    public ?int $id = null;

    #[Locked]
    public int $villageId = 1;

    public array $categories = [];

    public array $revisions = [];

    public ?array $revisionPreview = null;

    public $thumbnailUpload = null;

    public array $form = [
        'category_id' => '',
        'title' => '',
        'slug' => '',
        'excerpt' => '',
        'body' => '',
        'featured_image_url' => '',
        'status' => 'published',
        'published_at' => '',
    ];

    public function mount(?int $id = null): void
    {
        $this->id = $id;
        $this->villageId = CurrentVillage::id();
        $this->categories = DB::table('content_categories')->where('village_id', $this->villageId)->orderBy('name')->get()->map(fn($row): array => (array) $row)->all();

        if ($id) {
            $post = DB::table('posts')->where('village_id', $this->villageId)->where('id', $id)->first();
            abort_if(! $post, 404);

            $this->form = array_merge($this->form, (array) $post);
            $this->form['published_at'] = $post->published_at
                ? Carbon::parse($post->published_at)->format('Y-m-d\TH:i')
                : now()->format('Y-m-d\TH:i');
            $this->loadRevisions();
        } else {
            $this->form['published_at'] = now()->format('Y-m-d\TH:i');
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
                'form.status' => ['required', 'string'],
                'form.published_at' => ['nullable', 'date'],
                'thumbnailUpload' => ['nullable', 'image', 'max:4096'],
            ],
            [],
            [
                'form.category_id' => 'Kategori',
                'form.title' => 'Judul Artikel',
                'form.excerpt' => 'Ringkasan',
                'form.body' => 'Konten',
                'form.status' => 'Status',
                'form.published_at' => 'Tanggal Publikasi',
                'thumbnailUpload' => 'Gambar Thumbnail',
            ],
        )['form'];

        if ($data['category_id']) {
            abort_unless(
                DB::table('content_categories')
                    ->where('village_id', $this->villageId)
                    ->where('id', $data['category_id'])
                    ->exists(),
                422,
            );
        }

        if ($this->thumbnailUpload) {
            $data['featured_image_url'] = app(OptimizedImageStorage::class)->replace($this->thumbnailUpload, 'post-thumbnails', $this->form['featured_image_url'] ?: null);
        }

        $publishedAt = $data['status'] === 'published'
            ? Carbon::parse($data['published_at'] ?: now())->format('Y-m-d H:i:s')
            : null;

        $payload = [...$data, 'category_id' => $data['category_id'] ?: null, 'slug' => UniqueSlug::make('posts', $data['title'], $this->id), 'author_id' => auth()->id(), 'village_id' => $this->villageId, 'published_at' => $publishedAt, 'updated_at' => now()];

        if ($this->id) {
            DB::transaction(function () use ($payload): void {
                $post = DB::table('posts')->where('village_id', $this->villageId)->where('id', $this->id)->first();

                if (! $post) {
                    abort(404);
                }

                if (PostRevisionHistory::hasChanged($post, $payload)) {
                    PostRevisionHistory::capture($post, auth()->id());
                }

                DB::table('posts')->where('village_id', $this->villageId)->where('id', $this->id)->update($payload);
            });
        } else {
            $this->id = DB::table('posts')->insertGetId([...$payload, 'created_at' => now()]);
        }

        PublicSiteCache::forget($this->villageId);

        LivewireAlert::title('Tersimpan')->text('Artikel berhasil disimpan.')->success()->show();
        $this->redirectRoute('admin.posts.index', navigate: true);
    }

    public function restoreRevision(int $revisionId): void
    {
        if (! $this->id) {
            return;
        }

        $restored = false;

        DB::transaction(function () use ($revisionId, &$restored): void {
            $post = DB::table('posts')->where('village_id', $this->villageId)->where('id', $this->id)->first();

            if (! $post) {
                return;
            }

            $revision = $this->findRevision($revisionId);

            if (! $revision) {
                return;
            }

            PostRevisionHistory::capture($post, auth()->id());

            DB::table('posts')->where('village_id', $this->villageId)->where('id', $this->id)->update([
                'category_id' => $revision->category_id,
                'author_id' => $revision->author_id,
                'title' => $revision->title,
                'slug' => UniqueSlug::make('posts', $revision->slug, $this->id),
                'excerpt' => $revision->excerpt,
                'body' => $revision->body,
                'featured_image_url' => $revision->featured_image_url,
                'status' => $revision->status,
                'published_at' => $revision->published_at,
                'updated_at' => now(),
            ]);

            PostRevisionHistory::prune($this->id);
            $restored = true;
        });

        if (! $restored) {
            LivewireAlert::title('Revisi tidak ditemukan')->text('Revisi artikel tidak tersedia atau sudah terhapus otomatis.')->error()->show();

            return;
        }

        $this->revisionPreview = null;
        $this->thumbnailUpload = null;
        $this->loadPostFromDatabase();
        $this->loadRevisions();
        PublicSiteCache::forget($this->villageId);

        LivewireAlert::title('Revisi dipulihkan')->text('Artikel berhasil dikembalikan ke versi revisi yang dipilih.')->success()->show();
    }

    public function previewRevision(int $revisionId): void
    {
        $revision = $this->findRevision($revisionId);

        if (! $revision) {
            LivewireAlert::title('Revisi tidak ditemukan')->text('Revisi artikel tidak tersedia atau sudah terhapus otomatis.')->error()->show();

            return;
        }

        $categoryName = $revision->category_id
            ? DB::table('content_categories')->where('id', $revision->category_id)->value('name')
            : null;

        $this->revisionPreview = [
            'id' => $revision->id,
            'title' => $revision->title,
            'slug' => $revision->slug,
            'excerpt' => $revision->excerpt ?: 'Belum ada ringkasan artikel.',
            'body' => $revision->body ?: '<p>Belum ada konten artikel.</p>',
            'featured_image_url' => $revision->featured_image_url,
            'category_name' => $categoryName ?: 'Tanpa kategori',
            'status' => $revision->status,
            'published_at' => $revision->published_at
                ? Carbon::parse($revision->published_at)->isoFormat('D MMMM Y HH:mm').' WIB'
                : 'Belum dijadwalkan',
            'created_at' => Carbon::parse($revision->created_at)->isoFormat('D MMMM Y HH:mm').' WIB',
        ];
    }

    public function closeRevisionPreview(): void
    {
        $this->revisionPreview = null;
    }

    public function restorePreviewedRevision(): void
    {
        if (! $this->revisionPreview) {
            return;
        }

        $this->restoreRevision((int) $this->revisionPreview['id']);
    }

    private function loadPostFromDatabase(): void
    {
        $post = DB::table('posts')->where('village_id', $this->villageId)->where('id', $this->id)->first();

        if ($post) {
            $this->form = array_merge($this->form, (array) $post);
            $this->form['published_at'] = $post->published_at
                ? Carbon::parse($post->published_at)->format('Y-m-d\TH:i')
                : '';
            $this->dispatch('article-body-restored', body: $this->form['body'] ?? '');
        }
    }

    private function loadRevisions(): void
    {
        if (! $this->id) {
            $this->revisions = [];

            return;
        }

        $this->revisions = DB::table('post_revisions')
            ->leftJoin('users', 'post_revisions.revision_author_id', '=', 'users.id')
            ->where('post_revisions.village_id', $this->villageId)
            ->where('post_revisions.post_id', $this->id)
            ->orderByDesc('post_revisions.created_at')
            ->orderByDesc('post_revisions.id')
            ->limit(PostRevisionHistory::MAX_REVISIONS)
            ->get([
                'post_revisions.id',
                'post_revisions.title',
                'post_revisions.status',
                'post_revisions.created_at',
                'users.name as revision_author_name',
            ])
            ->map(fn($row): array => (array) $row)
            ->all();
    }

    private function findRevision(int $revisionId): ?object
    {
        if (! $this->id) {
            return null;
        }

        return DB::table('post_revisions')
            ->where('village_id', $this->villageId)
            ->where('post_id', $this->id)
            ->where('id', $revisionId)
            ->first();
    }
};
?>

<form wire:submit="save" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
    <section class="admin-panel border bg-white p-5">
        <div class="mb-5 border-b border-emerald-950/10 pb-4">
            <h2 class="flex items-center gap-2 font-black text-emerald-950"><i class="fa-solid fa-pen-nib text-amber-600"></i>Konten Artikel</h2>
            <p class="mt-1 text-sm text-zinc-500">Tulis judul, ringkasan, dan isi berita yang akan tampil di website publik.</p>
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
        @if ($id)
            <section class="admin-panel border bg-white p-5">
                <div class="mb-4 border-b border-emerald-950/10 pb-4">
                    <h3 class="flex items-center gap-2 font-black text-emerald-950"><i class="fa-solid fa-clock-rotate-left text-amber-600"></i>Revisi Artikel</h3>
                    <p class="mt-1 text-sm text-zinc-500">Menyimpan otomatis maksimal 3 revisi terakhir dari artikel ini.</p>
                </div>
                <div class="space-y-3">
                    @forelse($revisions as $revision)
                        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="line-clamp-1 text-sm font-black text-zinc-900">{{ $revision['title'] }}</p>
                                    <p class="mt-1 text-xs font-semibold text-zinc-500">
                                        {{ Carbon::parse($revision['created_at'])->isoFormat('D MMM Y HH:mm') }} WIB
                                    </p>
                                    <p class="mt-1 text-xs text-zinc-500">
                                        Oleh {{ $revision['revision_author_name'] ?: 'Sistem' }} · {{ $revision['status'] === 'published' ? 'Terbit' : 'Draf' }}
                                    </p>
                                </div>
                                <button type="button" wire:click="previewRevision({{ $revision['id'] }})"
                                    class="inline-flex min-h-9 shrink-0 items-center gap-2 rounded-md border border-emerald-950/15 bg-white px-3 text-xs font-black text-emerald-800">
                                    <i class="fa-solid fa-eye"></i>Preview
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-zinc-300 bg-zinc-50 p-4 text-sm font-semibold text-zinc-500">
                            Belum ada revisi. Revisi pertama akan dibuat saat artikel ini disimpan ulang.
                        </div>
                    @endforelse
                </div>
            </section>
        @endif
        <section class="admin-panel border bg-white p-5">
            <div class="mb-4 border-b border-emerald-950/10 pb-4">
                <h3 class="flex items-center gap-2 font-black text-emerald-950"><i class="fa-solid fa-sliders text-amber-600"></i>Publikasi</h3>
                <p class="mt-1 text-sm text-zinc-500">Atur kategori, status, tanggal, dan thumbnail.</p>
            </div>
            <x-admin.select label="Kategori" model="form.category_id" :options="collect($categories)->pluck('name', 'id')->prepend('Pilih kategori', '')->all()" />
            <x-admin.select label="Status" model="form.status" :options="['published' => 'Terbit', 'draft' => 'Draf']" class="mt-4" />
            <x-admin.input label="Tanggal Publikasi" model="form.published_at" type="datetime-local" class="mt-4" />
            <div class="mt-4">
                <label class="admin-field-label">Thumbnail Artikel</label>
                <p class="mt-1 text-xs text-zinc-500">Otomatis diperkecil dan dikompresi ke WebP.</p>
                <input type="file" wire:model="thumbnailUpload" accept="image/*"
                    class="admin-control mt-1">
                @error('thumbnailUpload')
                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                @enderror
                <div wire:loading wire:target="thumbnailUpload" class="mt-2 text-sm text-zinc-500">Mengunggah
                    thumbnail...</div>
                @if ($thumbnailUpload)
                    <img src="{{ $thumbnailUpload->temporaryUrl() }}" alt="Preview thumbnail"
                        class="mt-3 h-40 w-full rounded-xl border border-emerald-950/10 object-cover">
                @elseif($form['featured_image_url'])
                    <img src="{{ $form['featured_image_url'] }}" alt="Thumbnail saat ini"
                        class="mt-3 h-40 w-full rounded-xl border border-emerald-950/10 object-cover">
                @endif
            </div>
            <div class="mt-5 flex gap-2">
                <button
                    class="admin-btn-primary inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-md px-4 text-sm font-black text-white"><i class="fa-solid fa-floppy-disk"></i>Simpan</button>
                <a href="{{ route('admin.posts.index') }}"
                    class="inline-flex min-h-11 items-center gap-2 rounded-md border border-emerald-950/15 px-4 text-sm font-bold text-zinc-700"><i class="fa-solid fa-arrow-left"></i>Batal</a>
            </div>
        </section>
    </aside>
    @if ($revisionPreview)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-emerald-950/45 p-4 backdrop-blur-sm">
            <section class="max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-lg border border-emerald-950/10 bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-zinc-200 p-5">
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase tracking-wide text-amber-700">Preview Revisi</p>
                        <h3 class="mt-1 line-clamp-2 text-xl font-black text-emerald-950">{{ $revisionPreview['title'] }}</h3>
                        <p class="mt-1 text-sm font-semibold text-zinc-500">Disimpan sebagai revisi pada {{ $revisionPreview['created_at'] }}</p>
                    </div>
                    <button type="button" wire:click="closeRevisionPreview"
                        class="grid size-10 shrink-0 place-items-center rounded-md border border-zinc-300 text-zinc-600" aria-label="Tutup preview revisi">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="max-h-[62vh] overflow-y-auto p-5">
                    <div class="grid gap-4 md:grid-cols-[220px_minmax(0,1fr)]">
                        <div class="space-y-3">
                            <div class="overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100">
                                @if ($revisionPreview['featured_image_url'])
                                    <img src="{{ $revisionPreview['featured_image_url'] }}" alt="" class="h-40 w-full object-cover">
                                @else
                                    <div class="grid h-40 place-items-center text-zinc-400">
                                        <i class="fa-solid fa-newspaper text-3xl"></i>
                                    </div>
                                @endif
                            </div>
                            <dl class="space-y-2 rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-xs">
                                <div>
                                    <dt class="font-black uppercase text-zinc-400">Kategori</dt>
                                    <dd class="mt-0.5 font-bold text-zinc-700">{{ $revisionPreview['category_name'] }}</dd>
                                </div>
                                <div>
                                    <dt class="font-black uppercase text-zinc-400">Status</dt>
                                    <dd class="mt-0.5 font-bold text-zinc-700">{{ $revisionPreview['status'] === 'published' ? 'Terbit' : 'Draf' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-black uppercase text-zinc-400">Publikasi</dt>
                                    <dd class="mt-0.5 font-bold text-zinc-700">{{ $revisionPreview['published_at'] }}</dd>
                                </div>
                                <div>
                                    <dt class="font-black uppercase text-zinc-400">Slug</dt>
                                    <dd class="mt-0.5 break-all font-bold text-zinc-700">{{ $revisionPreview['slug'] }}</dd>
                                </div>
                            </dl>
                        </div>
                        <article class="min-w-0">
                            <p class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">{{ $revisionPreview['excerpt'] }}</p>
                            <div class="content-body mt-5 max-w-none rounded-lg border border-zinc-200 p-5 text-zinc-700">
                                {!! $revisionPreview['body'] !!}
                            </div>
                        </article>
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-zinc-200 p-5 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="closeRevisionPreview"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-zinc-300 px-4 text-sm font-bold text-zinc-700">
                        <i class="fa-solid fa-arrow-left"></i>Kembali
                    </button>
                    <button type="button" wire:click="restorePreviewedRevision" wire:confirm="Pulihkan artikel ke revisi yang sedang dipreview?"
                        class="admin-btn-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-4 text-sm font-black text-white">
                        <i class="fa-solid fa-rotate-left"></i>Pulihkan Revisi Ini
                    </button>
                </div>
            </section>
        </div>
    @endif
</form>
