<?php

use App\Rules\ValidCoordinates;
use App\Services\OptimizedImageStorage;
use App\Support\CoordinatePair;
use App\Support\CurrentVillage;
use App\Support\PublicSiteCache;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $module;

    public string $title = '';

    public bool $showModal = false;

    public int $villageId = 1;

    public $businessImageUpload = null;

    public array $businessPhotoUploads = [];

    public array $businessPhotos = [];

    public $projectImageUpload = null;

    public $documentUpload = null;

    public $desaCantikImageUpload = null;

    public $desaCantikDocumentUpload = null;

    public array $rows = [];

    public array $columns = [];

    public array $businessCategories = [];

    public array $bumdesCategories = [];

    public array $desaCantikCategories = [];

    public array $form = [];

    public int $page = 1;

    public int $perPage = 20;

    public int $totalRows = 0;

    public string $search = '';

    public function mount(string $module): void
    {
        abort_unless(in_array($module, ['businesses', 'bumdes', 'projects', 'files', 'desa-cantik'], true), 404);
        $role = auth()->user()?->role;
        abort_unless(in_array($role, ['developer', 'admin_desa', 'editor'], true), 403);

        $this->module = $module;
        $this->villageId = CurrentVillage::id();
        $this->ensureBumdesCategories();
        $this->ensureDesaCantikCategories();
        $this->resetForm();
        $this->loadData();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $row = DB::table($this->table())->where('village_id', $this->villageId)->where('id', $id)->first();

        if (!$row) {
            return;
        }

        $data = (array) $row;

        if (in_array($this->module, ['businesses', 'bumdes', 'projects'], true)) {
            $data['coordinates'] = CoordinatePair::format($data['latitude'] ?? null, $data['longitude'] ?? null);
        }

        unset($data['latitude'], $data['longitude']);

        $this->form = array_merge($this->form, $data);

        if (in_array($this->module, ['businesses', 'bumdes'], true)) {
            $this->loadBusinessPhotos((int) $row->id);
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->loadData();
    }

    public function resetSearch(): void
    {
        $this->search = '';
        $this->page = 1;
        $this->loadData();
    }

    public function save(): void
    {
        match ($this->module) {
            'businesses' => $this->saveBusiness(),
            'bumdes' => $this->saveBumdes(),
            'projects' => $this->saveProject(),
            'files' => $this->saveFile(),
            'desa-cantik' => $this->saveDesaCantik(),
        };

        PublicSiteCache::forget($this->villageId);

        $this->showModal = false;
        $this->resetForm();
        $this->loadData();
        LivewireAlert::title('Tersimpan')->text('Data berhasil disimpan.')->success()->timer(1200)->show();
    }

    public function delete(int $id): void
    {
        $row = DB::table($this->table())->where('village_id', $this->villageId)->where('id', $id)->first();

        if ($row && in_array($this->module, ['businesses', 'bumdes'], true)) {
            app(OptimizedImageStorage::class)->delete($row->featured_image_url);
            $this->deleteBusinessPhotos($id);
        } elseif ($row && $this->module === 'projects') {
            app(OptimizedImageStorage::class)->delete($row->image_url);
        } elseif ($row && $this->module === 'files') {
            $this->deletePublicFile($row->file_url);
        } elseif ($row && $this->module === 'desa-cantik') {
            app(OptimizedImageStorage::class)->delete($row->image_url);
            $this->deletePublicFile($row->file_url);
        }

        DB::table($this->table())->where('village_id', $this->villageId)->where('id', $id)->delete();
        PublicSiteCache::forget($this->villageId);
        $this->loadData();
    }

    public function removeBusinessPhoto(int $id): void
    {
        $photo = DB::table($this->photoTable())
            ->where('village_id', $this->villageId)
            ->where('id', $id)
            ->when($this->form['id'] ?? null, fn ($query, $businessId) => $query->where($this->photoForeignKey(), $businessId))
            ->first();

        if (! $photo) {
            return;
        }

        app(OptimizedImageStorage::class)->delete($photo->image_url);
        DB::table($this->photoTable())->where('id', $photo->id)->delete();

        if ($this->form['id']) {
            $this->loadBusinessPhotos((int) $this->form['id']);
        }

        PublicSiteCache::forget($this->villageId);
    }

    public function previousPage(): void
    {
        $this->page = max($this->page - 1, 1);
        $this->loadData();
    }

    public function nextPage(): void
    {
        $this->page = min($this->page + 1, max((int) ceil($this->totalRows / $this->perPage), 1));
        $this->loadData();
    }

    private function loadData(): void
    {
        $this->businessCategories = DB::table('business_categories')->where('village_id', $this->villageId)->orderBy('name')->get()->map(fn($row): array => (array) $row)->all();
        $this->bumdesCategories = DB::table('bumdes_categories')->where('village_id', $this->villageId)->orderBy('name')->get()->map(fn($row): array => (array) $row)->all();
        $this->desaCantikCategories = DB::table('desa_cantik_categories')->where('village_id', $this->villageId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()->map(fn($row): array => (array) $row)->all();

        if ($this->module === 'businesses') {
            $this->title = 'UMKM';
            $this->columns = ['thumbnail' => 'Foto', 'name' => 'Usaha', 'category_name' => 'Kategori', 'owner_name' => 'Pemilik', 'coordinates' => 'Lokasi', 'social_media' => 'Media Sosial'];
            $query = DB::table('businesses')->leftJoin('business_categories', 'businesses.category_id', '=', 'business_categories.id')->where('businesses.village_id', $this->villageId);
            $this->applySearch($query, ['businesses.name', 'businesses.slug', 'businesses.owner_name', 'businesses.whatsapp', 'businesses.hamlet', 'businesses.address', 'businesses.description', 'business_categories.name']);
            $this->totalRows = (clone $query)->count('businesses.id');
            $this->page = min($this->page, max((int) ceil($this->totalRows / $this->perPage), 1));
            $this->rows = $query->select('businesses.*', 'business_categories.name as category_name')->orderByDesc('businesses.updated_at')->forPage($this->page, $this->perPage)->get()->map(fn($row): array => (array) $row)->all();
        } elseif ($this->module === 'bumdes') {
            $this->title = 'BUMDES';
            $this->columns = ['thumbnail' => 'Foto', 'name' => 'BUMDES', 'category_name' => 'Kategori', 'manager_name' => 'Pengelola', 'coordinates' => 'Lokasi', 'social_media' => 'Media Sosial'];
            $query = DB::table('bumdes')->leftJoin('bumdes_categories', 'bumdes.category_id', '=', 'bumdes_categories.id')->where('bumdes.village_id', $this->villageId);
            $this->applySearch($query, ['bumdes.name', 'bumdes.slug', 'bumdes.manager_name', 'bumdes.whatsapp', 'bumdes.address', 'bumdes.description', 'bumdes_categories.name']);
            $this->totalRows = (clone $query)->count('bumdes.id');
            $this->page = min($this->page, max((int) ceil($this->totalRows / $this->perPage), 1));
            $this->rows = $query->select('bumdes.*', 'bumdes_categories.name as category_name')->orderByDesc('bumdes.updated_at')->forPage($this->page, $this->perPage)->get()->map(fn($row): array => (array) $row)->all();
        } elseif ($this->module === 'projects') {
            $this->title = 'Pembangunan';
            $this->columns = ['thumbnail' => 'Foto', 'title' => 'Judul', 'year' => 'Tahun', 'source_fund' => 'Sumber', 'progress_percentage' => 'Progress', 'coordinates' => 'Lokasi'];
            $query = DB::table('development_projects')->where('village_id', $this->villageId);
            $this->applySearch($query, ['title', 'slug', 'year', 'location', 'source_fund', 'volume', 'status', 'description']);
            $this->totalRows = (clone $query)->count();
            $this->page = min($this->page, max((int) ceil($this->totalRows / $this->perPage), 1));
            $this->rows = $query->orderByDesc('year')->forPage($this->page, $this->perPage)->get()->map(fn($row): array => (array) $row)->all();
        } elseif ($this->module === 'files') {
            $this->title = 'Unduhan Berkas';
            $this->columns = ['title' => 'Judul', 'file_url' => 'URL', 'published_at' => 'Tanggal'];
            $query = DB::table('downloadable_files')->where('village_id', $this->villageId);
            $this->applySearch($query, ['title', 'slug', 'description', 'file_url', 'published_at']);
            $this->totalRows = (clone $query)->count();
            $this->page = min($this->page, max((int) ceil($this->totalRows / $this->perPage), 1));
            $this->rows = $query->orderByDesc('published_at')->forPage($this->page, $this->perPage)->get()->map(fn($row): array => (array) $row)->all();
        } elseif ($this->module === 'desa-cantik') {
            $this->title = 'Desa Cantik';
            $this->columns = ['thumbnail' => 'Preview', 'title' => 'Judul', 'category_name' => 'Kategori', 'content_type' => 'Jenis', 'published_at' => 'Tanggal', 'is_published' => 'Status'];
            $query = DB::table('desa_cantik_posts')->join('desa_cantik_categories', 'desa_cantik_posts.category_id', '=', 'desa_cantik_categories.id')->where('desa_cantik_posts.village_id', $this->villageId);
            $this->applySearch($query, ['desa_cantik_posts.title', 'desa_cantik_posts.slug', 'desa_cantik_posts.description', 'desa_cantik_posts.content_type', 'desa_cantik_posts.published_at', 'desa_cantik_categories.name', 'desa_cantik_categories.type']);
            $this->totalRows = (clone $query)->count('desa_cantik_posts.id');
            $this->page = min($this->page, max((int) ceil($this->totalRows / $this->perPage), 1));
            $this->rows = $query
                ->orderByDesc('desa_cantik_posts.published_at')
                ->orderByDesc('desa_cantik_posts.updated_at')
                ->forPage($this->page, $this->perPage)
                ->get(['desa_cantik_posts.*', 'desa_cantik_categories.name as category_name', 'desa_cantik_categories.type as category_type'])
                ->map(fn($row): array => (array) $row)
                ->all();
        }
    }

    private function applySearch($query, array $columns): void
    {
        if (trim($this->search) === '') {
            return;
        }

        $search = '%'.strtolower(trim($this->search)).'%';
        $query->where(function ($query) use ($columns, $search): void {
            foreach ($columns as $column) {
                $query->orWhereRaw('LOWER(COALESCE('.$this->searchExpression($column).", '')) LIKE ?", [$search]);
            }
        });
    }

    private function searchExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "CAST({$column} AS CHAR)",
            default => "CAST({$column} AS TEXT)",
        };
    }

    private function resetForm(): void
    {
        $this->reset(['businessImageUpload', 'businessPhotoUploads', 'projectImageUpload', 'documentUpload', 'desaCantikImageUpload', 'desaCantikDocumentUpload']);
        $this->businessPhotos = [];

        $this->form = match ($this->module ?? '') {
            'businesses' => ['id' => null, 'category_id' => '', 'name' => '', 'slug' => '', 'owner_name' => '', 'whatsapp' => '', 'instagram_url' => '', 'facebook_url' => '', 'tiktok_url' => '', 'address' => '', 'coordinates' => '', 'description' => '', 'featured_image_url' => '', 'worker_count' => 0, 'hamlet' => '', 'is_active' => true],
            'bumdes' => ['id' => null, 'category_id' => '', 'name' => '', 'slug' => '', 'manager_name' => '', 'whatsapp' => '', 'instagram_url' => '', 'facebook_url' => '', 'tiktok_url' => '', 'address' => '', 'coordinates' => '', 'description' => '', 'featured_image_url' => '', 'worker_count' => 0, 'is_active' => true],
            'projects' => ['id' => null, 'title' => '', 'slug' => '', 'year' => (int) date('Y'), 'location' => '', 'coordinates' => '', 'source_fund' => '', 'budget_amount' => 0, 'volume' => '', 'progress_percentage' => 0, 'status' => 'planned', 'description' => '', 'image_url' => ''],
            'files' => ['id' => null, 'title' => '', 'slug' => '', 'description' => '', 'file_url' => '', 'published_at' => now()->toDateString(), 'is_published' => true],
            'desa-cantik' => ['id' => null, 'category_id' => '', 'title' => '', 'slug' => '', 'description' => '', 'content_type' => 'pdf', 'image_url' => '', 'file_url' => '', 'external_url' => '', 'published_at' => now()->toDateString(), 'is_published' => true],
            default => [],
        };
    }

    private function saveBusiness(): void
    {
        $this->validate(
            [
                'form.name' => ['required', 'string', 'max:255'],
                'form.category_id' => ['nullable', 'integer'],
                'form.owner_name' => ['nullable', 'string', 'max:255'],
                'form.whatsapp' => ['nullable', 'string', 'max:40'],
                'form.instagram_url' => ['nullable', 'url', 'max:2048'],
                'form.facebook_url' => ['nullable', 'url', 'max:2048'],
                'form.tiktok_url' => ['nullable', 'url', 'max:2048'],
                'form.address' => ['nullable', 'string'],
                'form.coordinates' => ['nullable', 'string', new ValidCoordinates()],
                'form.worker_count' => ['required', 'integer', 'min:0'],
                'form.is_active' => ['boolean'],
                'businessImageUpload' => ['nullable', 'image', 'max:4096'],
                'businessPhotoUploads' => ['nullable', 'array'],
                'businessPhotoUploads.*' => ['image', 'max:4096'],
            ],
            [],
            [
                'form.name' => 'Nama UMKM',
                'form.category_id' => 'Kategori',
                'form.owner_name' => 'Nama Pemilik',
                'form.whatsapp' => 'WhatsApp',
                'form.instagram_url' => 'Instagram',
                'form.facebook_url' => 'Facebook',
                'form.tiktok_url' => 'Tiktok',
                'form.address' => 'Alamat UMKM',
                'form.coordinates' => 'Kordinat',
                'form.worker_count' => 'Jumlah Pekerja',
                'form.is_active' => 'Status',
                'businessImageUpload' => 'Gambar UMKM',
                'businessPhotoUploads' => 'Foto Galeri UMKM',
                'businessPhotoUploads.*' => 'Foto Galeri UMKM',
            ],
        );

        if ($this->businessImageUpload) {
            $this->form['featured_image_url'] = app(OptimizedImageStorage::class)->replace($this->businessImageUpload, 'business-images', $this->form['featured_image_url'] ?: null);
        }

        $coordinates = $this->form['coordinates'] !== '' ? CoordinatePair::parse($this->form['coordinates']) : ['latitude' => null, 'longitude' => null];
        $payload = $this->form;
        unset($payload['coordinates']);

        $businessId = $this->upsert('businesses', [...$payload, ...$coordinates, 'village_id' => $this->villageId, 'category_id' => $this->form['category_id'] ?: null, 'slug' => UniqueSlug::make('businesses', $this->form['name'], $this->form['id']), 'instagram_url' => $this->form['instagram_url'] ?: null, 'facebook_url' => $this->form['facebook_url'] ?: null, 'tiktok_url' => $this->form['tiktok_url'] ?: null, 'worker_count' => (int) $this->form['worker_count'], 'is_active' => (bool) $this->form['is_active']]);

        $this->storeBusinessPhotos($businessId);
    }

    private function saveBumdes(): void
    {
        $this->validate(
            [
                'form.name' => ['required', 'string', 'max:255'],
                'form.category_id' => ['nullable', 'integer'],
                'form.manager_name' => ['nullable', 'string', 'max:255'],
                'form.whatsapp' => ['nullable', 'string', 'max:40'],
                'form.instagram_url' => ['nullable', 'url', 'max:2048'],
                'form.facebook_url' => ['nullable', 'url', 'max:2048'],
                'form.tiktok_url' => ['nullable', 'url', 'max:2048'],
                'form.address' => ['nullable', 'string'],
                'form.coordinates' => ['nullable', 'string', new ValidCoordinates()],
                'form.worker_count' => ['required', 'integer', 'min:0'],
                'form.is_active' => ['boolean'],
                'businessImageUpload' => ['nullable', 'image', 'max:4096'],
                'businessPhotoUploads' => ['nullable', 'array'],
                'businessPhotoUploads.*' => ['image', 'max:4096'],
            ],
            [],
            [
                'form.name' => 'Nama BUMDES',
                'form.category_id' => 'Kategori',
                'form.manager_name' => 'Pengelola',
                'form.whatsapp' => 'WhatsApp',
                'form.instagram_url' => 'Instagram',
                'form.facebook_url' => 'Facebook',
                'form.tiktok_url' => 'Tiktok',
                'form.address' => 'Alamat BUMDES',
                'form.coordinates' => 'Kordinat',
                'form.worker_count' => 'Jumlah Pekerja',
                'form.is_active' => 'Status',
                'businessImageUpload' => 'Gambar BUMDES',
                'businessPhotoUploads' => 'Foto Galeri BUMDES',
                'businessPhotoUploads.*' => 'Foto Galeri BUMDES',
            ],
        );

        if ($this->businessImageUpload) {
            $this->form['featured_image_url'] = app(OptimizedImageStorage::class)->replace($this->businessImageUpload, 'bumdes-images', $this->form['featured_image_url'] ?: null);
        }

        $coordinates = $this->form['coordinates'] !== '' ? CoordinatePair::parse($this->form['coordinates']) : ['latitude' => null, 'longitude' => null];
        $payload = $this->form;
        unset($payload['coordinates']);

        $bumdesId = $this->upsert('bumdes', [...$payload, ...$coordinates, 'village_id' => $this->villageId, 'category_id' => $this->form['category_id'] ?: null, 'slug' => UniqueSlug::make('bumdes', $this->form['name'], $this->form['id']), 'instagram_url' => $this->form['instagram_url'] ?: null, 'facebook_url' => $this->form['facebook_url'] ?: null, 'tiktok_url' => $this->form['tiktok_url'] ?: null, 'worker_count' => (int) $this->form['worker_count'], 'is_active' => (bool) $this->form['is_active']]);

        $this->storeBusinessPhotos($bumdesId);
    }

    private function saveProject(): void
    {
        $this->validate(
            [
                'form.title' => ['required', 'string', 'max:255'],
                'form.year' => ['required', 'integer', 'between:1900,2100'],
                'form.coordinates' => ['nullable', 'string', new ValidCoordinates()],
                'form.budget_amount' => ['required', 'numeric', 'min:0'],
                'form.progress_percentage' => ['required', 'numeric', 'between:0,100'],
                'projectImageUpload' => ['nullable', 'image', 'max:4096'],
            ],
            [],
            [
                'form.title' => 'Judul Pembangunan',
                'form.year' => 'Tahun',
                'form.coordinates' => 'Kordinat',
                'form.budget_amount' => 'Anggaran',
                'form.progress_percentage' => 'Progres',
                'projectImageUpload' => 'Gambar Pembangunan',
            ],
        );

        if ($this->projectImageUpload) {
            $this->form['image_url'] = app(OptimizedImageStorage::class)->replace($this->projectImageUpload, 'project-images', $this->form['image_url'] ?: null);
        }

        $coordinates = $this->form['coordinates'] !== '' ? CoordinatePair::parse($this->form['coordinates']) : ['latitude' => null, 'longitude' => null];
        $payload = $this->form;
        unset($payload['coordinates']);

        $this->upsert('development_projects', [...$payload, ...$coordinates, 'village_id' => $this->villageId, 'slug' => UniqueSlug::make('development_projects', $this->form['title'], $this->form['id']), 'year' => (int) $this->form['year'], 'budget_amount' => (float) $this->form['budget_amount'], 'progress_percentage' => (float) $this->form['progress_percentage']]);
    }

    private function saveFile(): void
    {
        $this->validate(
            [
                'form.title' => ['required', 'string', 'max:250'],
                'documentUpload' => ['nullable', 'required_without:form.id', 'file', 'max:10240'],
                'form.description' => ['nullable', 'string'],
            ],
            [
                'documentUpload.required_without' => 'Berkas Wajib Diisi',
            ],
            [
                'form.title' => 'Judul Berkas',
                'documentUpload' => 'Berkas',
                'form.description' => 'Deskripsi',
            ],
        );

        if ($this->documentUpload) {
            $this->form['file_url'] = Storage::url($this->documentUpload->store('download-files', 'public'));
        }

        $this->upsert('downloadable_files', [...$this->form, 'village_id' => $this->villageId, 'slug' => UniqueSlug::make('downloadable_files', $this->form['title'], $this->form['id']), 'is_published' => (bool) $this->form['is_published']]);
    }

    private function saveDesaCantik(): void
    {
        $this->validate(
            [
                'form.category_id' => ['required', 'integer'],
                'form.title' => ['required', 'string', 'max:255'],
                'form.description' => ['nullable', 'string'],
                'form.content_type' => ['required', 'in:image,pdf,url,fliphtml'],
                'form.external_url' => ['nullable', 'string', 'max:10000'],
                'form.published_at' => ['nullable', 'date'],
                'form.is_published' => ['boolean'],
                'desaCantikImageUpload' => ['nullable', 'image', 'max:10240'],
                'desaCantikDocumentUpload' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            ],
            [],
            [
                'form.category_id' => 'Kategori',
                'form.title' => 'Judul',
                'form.description' => 'Deskripsi',
                'form.content_type' => 'Jenis Publikasi',
                'form.external_url' => 'Tautan',
                'form.published_at' => 'Tanggal Publikasi',
                'form.is_published' => 'Status',
                'desaCantikImageUpload' => 'Thumbnail',
                'desaCantikDocumentUpload' => 'File PDF Publikasi',
            ],
        );

        $category = DB::table('desa_cantik_categories')->where('village_id', $this->villageId)->where('is_active', true)->where('id', (int) $this->form['category_id'])->first();
        $categoryType = $category?->type;

        if ($categoryType === 'infographic') {
            $this->form['content_type'] = 'image';
            $this->form['file_url'] = '';
            $this->form['external_url'] = '';
        } elseif ($categoryType === 'publication') {
            if (!in_array($this->form['content_type'], ['pdf', 'url', 'fliphtml'], true)) {
                $this->form['content_type'] = 'pdf';
            }
        } else {
            $this->addError('form.category_id', 'Kategori Desa Cantik tidak valid.');

            return;
        }

        if ($this->form['content_type'] === 'image' && !$this->desaCantikImageUpload && !$this->form['image_url']) {
            $this->addError('desaCantikImageUpload', 'Gambar wajib diunggah untuk kategori Infografis.');

            return;
        }

        if ($this->form['content_type'] === 'pdf' && !$this->desaCantikDocumentUpload && !$this->form['file_url']) {
            $this->addError('desaCantikDocumentUpload', 'File PDF wajib diunggah untuk publikasi PDF.');

            return;
        }

        if (in_array($this->form['content_type'], ['url', 'fliphtml'], true) && !filter_var($this->extractUrl($this->form['external_url']), FILTER_VALIDATE_URL)) {
            $this->addError('form.external_url', 'URL publikasi wajib berupa alamat yang valid.');

            return;
        }

        if ($this->desaCantikImageUpload) {
            $this->form['image_url'] = app(OptimizedImageStorage::class)->replace($this->desaCantikImageUpload, 'desa-cantik-images', $this->form['image_url'] ?: null, 'gallery');
        }

        if ($this->desaCantikDocumentUpload) {
            $this->deletePublicFile($this->form['file_url'] ?: null);
            $this->form['file_url'] = Storage::url($this->desaCantikDocumentUpload->store('desa-cantik-publications', 'public'));
        }

        $this->form['external_url'] = in_array($this->form['content_type'], ['url', 'fliphtml'], true) ? $this->extractUrl($this->form['external_url']) : '';

        $this->upsert('desa_cantik_posts', [
            'id' => $this->form['id'] ?? null,
            'village_id' => $this->villageId,
            'category_id' => (int) $this->form['category_id'],
            'slug' => UniqueSlug::make('desa_cantik_posts', $this->form['title'], $this->form['id']),
            'title' => $this->form['title'],
            'description' => $this->form['description'] ?: null,
            'content_type' => $this->form['content_type'],
            'image_url' => $this->form['image_url'] ?: null,
            'file_url' => $this->form['file_url'] ?: null,
            'external_url' => $this->form['external_url'] ?: null,
            'published_at' => $this->form['published_at'] ?: null,
            'is_published' => (bool) $this->form['is_published'],
        ]);
    }

    private function upsert(string $table, array $payload): int
    {
        $id = $payload['id'] ?? null;
        unset($payload['id']);
        $payload['updated_at'] = now();

        if ($id) {
            DB::table($table)->where('village_id', $this->villageId)->where('id', $id)->update($payload);

            return (int) $id;
        } else {
            return (int) DB::table($table)->insertGetId([...$payload, 'created_at' => now()]);
        }
    }

    private function loadBusinessPhotos(int $businessId): void
    {
        $this->businessPhotos = DB::table($this->photoTable())
            ->where('village_id', $this->villageId)
            ->where($this->photoForeignKey(), $businessId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    private function storeBusinessPhotos(int $businessId): void
    {
        if ($this->businessPhotoUploads === []) {
            return;
        }

        $storage = app(OptimizedImageStorage::class);
        $sortOrder = (int) DB::table($this->photoTable())
            ->where('village_id', $this->villageId)
            ->where($this->photoForeignKey(), $businessId)
            ->max('sort_order');

        foreach ($this->businessPhotoUploads as $upload) {
            $sortOrder++;
            DB::table($this->photoTable())->insert([
                'village_id' => $this->villageId,
                $this->photoForeignKey() => $businessId,
                'image_url' => $storage->store($upload, $this->galleryDirectory(), 'gallery'),
                'sort_order' => $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function deleteBusinessPhotos(int $businessId): void
    {
        $photos = DB::table($this->photoTable())
            ->where('village_id', $this->villageId)
            ->where($this->photoForeignKey(), $businessId)
            ->get();

        foreach ($photos as $photo) {
            app(OptimizedImageStorage::class)->delete($photo->image_url);
        }

        DB::table($this->photoTable())
            ->where('village_id', $this->villageId)
            ->where($this->photoForeignKey(), $businessId)
            ->delete();
    }

    private function photoTable(): string
    {
        return $this->module === 'bumdes' ? 'bumdes_photos' : 'business_photos';
    }

    private function photoForeignKey(): string
    {
        return $this->module === 'bumdes' ? 'bumdes_id' : 'business_id';
    }

    private function galleryDirectory(): string
    {
        return $this->module === 'bumdes' ? 'bumdes-gallery' : 'business-gallery';
    }

    private function deletePublicFile(?string $url): void
    {
        $path = $url ? parse_url($url, PHP_URL_PATH) : null;

        if (is_string($path) && str_starts_with($path, '/storage/')) {
            Storage::disk('public')->delete(Str::after($path, '/storage/'));
        }
    }

    private function table(): string
    {
        return match ($this->module) {
            'businesses' => 'businesses',
            'bumdes' => 'bumdes',
            'projects' => 'development_projects',
            'files' => 'downloadable_files',
            'desa-cantik' => 'desa_cantik_posts',
            default => throw new InvalidArgumentException('Modul tidak didukung.'),
        };
    }

    private function ensureBumdesCategories(): void
    {
        if ($this->module !== 'bumdes') {
            return;
        }

        foreach ([['Usaha Desa', 'usaha-desa'], ['Layanan', 'layanan'], ['Perdagangan', 'perdagangan']] as [$name, $slug]) {
            $exists = DB::table('bumdes_categories')->where('village_id', $this->villageId)->where('name', $name)->exists();

            if ($exists) {
                continue;
            }

            DB::table('bumdes_categories')->insert([
                'village_id' => $this->villageId,
                'name' => $name,
                'slug' => UniqueSlug::make('bumdes_categories', $slug . '-' . $this->villageId),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureDesaCantikCategories(): void
    {
        foreach ([['Publikasi', 'publikasi', 'publication', 1], ['Infografis', 'infografis', 'infographic', 2]] as [$name, $slug, $type, $sortOrder]) {
            $exists = DB::table('desa_cantik_categories')->where('village_id', $this->villageId)->where('type', $type)->exists();

            if ($exists) {
                DB::table('desa_cantik_categories')
                    ->where('village_id', $this->villageId)
                    ->where('type', $type)
                    ->update(['name' => $name, 'sort_order' => $sortOrder, 'is_active' => true, 'updated_at' => now()]);

                continue;
            }

            DB::table('desa_cantik_categories')->insert([
                'village_id' => $this->villageId,
                'name' => $name,
                'slug' => UniqueSlug::make('desa_cantik_categories', $slug . '-' . $this->villageId),
                'type' => $type,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function extractUrl(?string $value): string
    {
        $value = trim((string) $value);

        if (preg_match('/src=["\']([^"\']+)["\']/i', $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }
};
?>

<div class="space-y-5">
    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-zinc-200 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-black">{{ $title }}</h2>
                <p class="text-sm text-zinc-500">Tambah dan edit data menggunakan modal.</p>
            </div>
            <button type="button" wire:click="create"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-black text-white">
                <i class="fa-solid fa-plus"></i>
                Tambah Data
            </button>
        </div>

            <div class="grid gap-3 border-b border-zinc-200 p-5 sm:grid-cols-[minmax(0,1fr)_auto]">
                <x-admin.input label="Cari {{ $title }}" model="search" placeholder="Cari data berdasarkan judul, nama, kategori, status, atau keterangan" />
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
                            @foreach ($columns as $label)
                                <th class="px-5 py-3">{{ $label }}</th>
                            @endforeach
                            <th class="w-44 px-5 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse($rows as $row)
                            <tr>
                                @foreach ($columns as $key => $label)
                                    <td class="px-5 py-4">
                                        @if ($key === 'thumbnail')
                                            @php($imageUrl = in_array($module, ['businesses', 'bumdes'], true) ? $row['featured_image_url'] ?? null : $row['image_url'] ?? null)
                                            @if ($imageUrl)
                                                <img src="{{ $imageUrl }}"
                                                    alt="Thumbnail {{ in_array($module, ['businesses', 'bumdes'], true) ? $row['name'] : $row['title'] }}"
                                                    loading="lazy"
                                                    class="size-16 rounded-lg border border-zinc-200 object-cover">
                                            @else
                                                <div
                                                    class="grid size-16 place-items-center rounded-lg border border-dashed border-zinc-300 bg-zinc-50 text-zinc-300">
                                                    <i
                                                        class="fa-solid {{ ($row['content_type'] ?? '') === 'pdf' ? 'fa-file-pdf' : (($row['content_type'] ?? '') === 'fliphtml' ? 'fa-book-open' : 'fa-link') }}"></i>
                                                </div>
                                            @endif
                                        @elseif($key === 'coordinates')
                                            @if ($row['latitude'] !== null && $row['longitude'] !== null)
                                                <a href="https://www.google.com/maps?q={{ $row['latitude'] }},{{ $row['longitude'] }}"
                                                    target="_blank" rel="noopener"
                                                    class="inline-flex items-center gap-2 whitespace-nowrap font-bold text-emerald-700 hover:text-emerald-900">
                                                    <i class="fa-solid fa-location-dot"></i> Google Maps
                                                </a>
                                            @else
                                                <span class="text-zinc-400">Belum diatur</span>
                                            @endif
                                        @elseif($key === 'social_media')
                                            <div class="flex items-center gap-2">
                                                @foreach ([['instagram_url', 'fa-instagram', 'Instagram'], ['facebook_url', 'fa-facebook-f', 'Facebook'], ['tiktok_url', 'fa-tiktok', 'TikTok']] as [$field, $icon, $socialLabel])
                                                    @if (!empty($row[$field]))
                                                        <a href="{{ $row[$field] }}" target="_blank" rel="noopener"
                                                            title="{{ $socialLabel }}"
                                                            aria-label="{{ $socialLabel }} {{ $row['name'] }}"
                                                            class="grid size-8 place-items-center rounded-full bg-zinc-100 text-zinc-600 hover:bg-emerald-100 hover:text-emerald-700"><i
                                                                class="fa-brands {{ $icon }}"></i></a>
                                                    @endif
                                                @endforeach
                                                @if (empty($row['instagram_url']) && empty($row['facebook_url']) && empty($row['tiktok_url']))
                                                    <span class="text-zinc-400">-</span>
                                                @endif
                                            </div>
                                        @elseif(in_array($key, ['status', 'attendance_status', 'category_name', 'category_type', 'type', 'content_type'], true))
                                            <x-admin.pill :value="data_get($row, $key, '-')" :type="str_contains($key, 'category') ? 'category' : 'default'" />
                                        @elseif($key === 'is_published')
                                            <x-admin.pill :value="$row['is_published'] ? 'published' : 'draft'" />
                                        @else
                                            {{ data_get($row, $key, '-') }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-5 py-4">
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="edit({{ $row['id'] }})"
                                            class="rounded bg-zinc-100 px-3 py-2 text-xs font-bold">Edit</button>
                                        <button type="button" wire:click="delete({{ $row['id'] }})"
                                            wire:confirm="Hapus data ini?"
                                            class="rounded bg-red-50 px-3 py-2 text-xs font-bold text-red-700">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns) + 1 }}" class="px-5 py-10 text-center text-zinc-500">
                                    Belum ada data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between gap-4 border-t border-zinc-200 px-5 py-4 text-sm">
                <span class="font-semibold text-zinc-500">{{ $totalRows }} data · Halaman
                    {{ $page }}</span>
                <div class="flex gap-2">
                    <button type="button" wire:click="previousPage" @disabled($page <= 1)
                        class="rounded-md border border-zinc-200 px-3 py-2 font-bold disabled:opacity-40">Sebelumnya</button>
                    <button type="button" wire:click="nextPage" @disabled($page >= max((int) ceil($totalRows / $perPage), 1))
                        class="rounded-md border border-zinc-200 px-3 py-2 font-bold disabled:opacity-40">Berikutnya</button>
                </div>
            </div>
    </section>

    @if ($showModal)
        <div x-data @click.self="$wire.closeModal()" @keydown.escape.window="$wire.closeModal()"
            class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 p-4" role="dialog"
            aria-modal="true">
            <div class="max-h-[90dvh] w-full max-w-4xl overflow-y-auto rounded-lg bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                    <div>
                        <h3 class="text-lg font-black">{{ $form['id'] ? 'Edit' : 'Tambah' }} {{ $title }}</h3>
                        <p class="text-sm text-zinc-500">Isi data lalu simpan perubahan.</p>
                    </div>
                    <button type="button" wire:click="closeModal"
                        class="grid size-11 place-items-center rounded-md border border-zinc-300"
                        aria-label="Tutup modal">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit="save" class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    @if(in_array($module, ['businesses', 'bumdes'], true))
                        @php($isBumdes = $module === 'bumdes')
                        @php($entityLabel = $isBumdes ? 'BUMDES' : 'UMKM')
                        <x-admin.input :label="$isBumdes ? 'Nama BUMDES' : 'Nama Usaha'" model="form.name" />
                        <x-admin.select label="Kategori" model="form.category_id" :options="collect($isBumdes ? $bumdesCategories : $businessCategories)
                            ->pluck('name', 'id')
                            ->prepend('Pilih kategori', '')
                            ->all()" />
                        @if($isBumdes)
                            <x-admin.input label="Pengelola" model="form.manager_name" />
                        @else
                            <x-admin.input label="Pemilik" model="form.owner_name" />
                        @endif
                        <x-admin.input label="WhatsApp" model="form.whatsapp" />
                        <x-admin.input label="Instagram" model="form.instagram_url" type="url"
                            placeholder="https://instagram.com/namausaha" />
                        <x-admin.input label="Facebook" model="form.facebook_url" type="url"
                            placeholder="https://facebook.com/namausaha" />
                        <x-admin.input label="TikTok" model="form.tiktok_url" type="url"
                            placeholder="https://tiktok.com/@namausaha" />
                        @unless($isBumdes)
                            <x-admin.input label="Dusun" model="form.hamlet" />
                        @endunless
                        <x-admin.input label="Pekerja" model="form.worker_count" type="number" />
                        <x-admin.textarea label="Alamat" model="form.address" class="lg:col-span-3" />
                        <x-admin.input label="Koordinat (Latitude, Longitude)" model="form.coordinates"
                            type="text" placeholder="Contoh: -3.295384, 104.674993" class="lg:col-span-2"
                            inputmode="decimal" />
                        <div class="flex items-end">
                            @php($previewCoordinates = filled($form['coordinates']) ? rescue(fn() => CoordinatePair::parse($form['coordinates']), null, report: false) : null)
                            @if ($previewCoordinates)
                                <a href="https://www.google.com/maps?q={{ $previewCoordinates['latitude'] }},{{ $previewCoordinates['longitude'] }}"
                                    target="_blank" rel="noopener"
                                    class="inline-flex min-h-10 items-center gap-2 rounded-md bg-emerald-50 px-3 text-sm font-bold text-emerald-700"><i
                                        class="fa-solid fa-map-location-dot"></i> Buka Google Maps</a>
                            @else
                                <p class="pb-2 text-xs text-zinc-500">Pisahkan latitude dan longitude dengan tanda
                                    koma.</p>
                            @endif
                        </div>
                        <div class="lg:col-span-3">
                            <label class="text-sm font-bold">Gambar {{ $entityLabel }}</label>
                            <p class="mt-1 text-xs text-zinc-500">Digunakan sebagai cover utama pada daftar {{ $entityLabel }}.</p>
                            <input type="file" wire:model="businessImageUpload" accept="image/*"
                                class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                            @error('businessImageUpload')
                                <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                            @enderror
                            <div wire:loading wire:target="businessImageUpload" class="mt-1 text-sm text-zinc-500">
                                Mengunggah gambar...</div>
                            @if ($businessImageUpload)
                                <img src="{{ $businessImageUpload->temporaryUrl() }}" alt="Preview gambar {{ $entityLabel }}"
                                    class="mt-2 h-32 w-full rounded-md object-cover">
                            @elseif($form['featured_image_url'])
                                <img src="{{ $form['featured_image_url'] }}" alt="Gambar saat ini"
                                    class="mt-2 h-32 w-full rounded-md object-cover">
                            @endif
                        </div>
                        <div class="lg:col-span-3">
                            <label class="text-sm font-bold">Foto Galeri {{ $entityLabel }}</label>
                            <p class="mt-1 text-xs text-zinc-500">Pilih lebih dari satu foto untuk gallery detail {{ $entityLabel }} di website publik.</p>
                            <input type="file" wire:model="businessPhotoUploads" accept="image/*" multiple
                                class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                            @error('businessPhotoUploads')
                                <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                            @enderror
                            @error('businessPhotoUploads.*')
                                <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                            @enderror
                            <div wire:loading wire:target="businessPhotoUploads" class="mt-1 text-sm text-zinc-500">
                                Mengunggah foto galeri...</div>
                            @if ($businessPhotoUploads)
                                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    @foreach ($businessPhotoUploads as $photoUpload)
                                        <img src="{{ $photoUpload->temporaryUrl() }}" alt="Preview foto galeri {{ $entityLabel }}"
                                            class="h-24 w-full rounded-md object-cover">
                                    @endforeach
                                </div>
                            @endif
                            @if ($businessPhotos)
                                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    @foreach ($businessPhotos as $photo)
                                        <div class="group relative overflow-hidden rounded-md border border-zinc-200 bg-zinc-50">
                                            <img src="{{ $photo['image_url'] }}" alt="Foto galeri {{ $entityLabel }}"
                                                class="h-24 w-full object-cover">
                                            <button type="button" wire:click="removeBusinessPhoto({{ $photo['id'] }})"
                                                wire:confirm="Hapus foto galeri ini?"
                                                class="absolute right-2 top-2 grid size-8 place-items-center rounded-md bg-red-600 text-white opacity-95 shadow-lg"
                                                aria-label="Hapus foto galeri">
                                                <i class="fa-solid fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <x-admin.textarea label="Deskripsi" model="form.description" class="lg:col-span-3" />
                    @elseif($module === 'projects')
                        <x-admin.input label="Judul" model="form.title" />
                        <x-admin.input label="Tahun" model="form.year" type="number" />
                        <x-admin.input label="Sumber Dana" model="form.source_fund" />
                        <x-admin.input label="Anggaran" model="form.budget_amount" type="number" />
                        <x-admin.input label="Volume" model="form.volume" />
                        <x-admin.input label="Progress %" model="form.progress_percentage" type="number" />
                        <x-admin.input label="Lokasi" model="form.location" class="lg:col-span-2" />
                        <x-admin.input label="Koordinat (Latitude, Longitude)" model="form.coordinates"
                            type="text" placeholder="Contoh: -3.295384, 104.674993" class="lg:col-span-2"
                            inputmode="decimal" />
                        <div class="flex items-end">
                            @php($previewCoordinates = filled($form['coordinates']) ? rescue(fn() => CoordinatePair::parse($form['coordinates']), null, report: false) : null)
                            @if ($previewCoordinates)
                                <a href="https://www.google.com/maps?q={{ $previewCoordinates['latitude'] }},{{ $previewCoordinates['longitude'] }}"
                                    target="_blank" rel="noopener"
                                    class="inline-flex min-h-10 items-center gap-2 rounded-md bg-emerald-50 px-3 text-sm font-bold text-emerald-700"><i
                                        class="fa-solid fa-map-location-dot"></i> Buka Google Maps</a>
                            @else
                                <p class="pb-2 text-xs text-zinc-500">Pisahkan latitude dan longitude dengan tanda
                                    koma.</p>
                            @endif
                        </div>
                        <div>
                            <label class="text-sm font-bold">Gambar Pembangunan</label>
                            <input type="file" wire:model="projectImageUpload" accept="image/*"
                                class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                            @error('projectImageUpload')
                                <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                            @enderror
                            <div wire:loading wire:target="projectImageUpload" class="mt-1 text-sm text-zinc-500">
                                Mengunggah gambar...</div>
                            @if ($projectImageUpload)
                                <img src="{{ $projectImageUpload->temporaryUrl() }}" alt="Preview gambar pembangunan"
                                    class="mt-2 h-24 w-full rounded-md object-cover">
                            @elseif($form['image_url'])
                                <img src="{{ $form['image_url'] }}" alt="Gambar saat ini"
                                    class="mt-2 h-24 w-full rounded-md object-cover">
                            @endif
                        </div>
                        <x-admin.textarea label="Deskripsi" model="form.description" class="lg:col-span-3" />
                    @elseif($module === 'files')
                        <div class="lg:col-span-3">
                            <x-admin.input label="Judul" model="form.title" />
                        </div>
                        <div class="lg:col-span-3">
                            <label class="text-sm font-bold">Berkas Unduhan</label>
                            <input type="file" wire:model="documentUpload"
                                class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                            @error('documentUpload')
                                <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                            @enderror
                            <div wire:loading wire:target="documentUpload" class="mt-1 text-sm text-zinc-500">
                                Mengunggah berkas...</div>
                            @if ($form['file_url'])
                                <a href="{{ $form['file_url'] }}" target="_blank"
                                    class="mt-2 inline-flex text-sm font-bold text-emerald-700">Lihat berkas saat
                                    ini</a>
                            @endif
                        </div>
                        <x-admin.textarea label="Deskripsi" model="form.description" class="lg:col-span-3" />
                    @elseif($module === 'desa-cantik')
                        @php($selectedDesaCantikCategory = collect($desaCantikCategories)->firstWhere('id', (int) ($form['category_id'] ?? 0)))
                        @php($selectedDesaCantikType = $selectedDesaCantikCategory['type'] ?? 'publication')
                        <div class="rounded-md border border-emerald-100 bg-emerald-50 p-4 lg:col-span-3">
                            <div class="flex items-start gap-3">
                                <div
                                    class="grid size-10 shrink-0 place-items-center rounded-md bg-white text-emerald-700">
                                    <i class="fa-solid fa-chart-simple"></i>
                                </div>
                                <div>
                                    <div class="font-black">Konten Desa Cantik</div>
                                    <p class="mt-1 text-sm text-emerald-900/70">Infografis hanya memakai gambar.
                                        Publikasi mendukung PDF, URL biasa, atau URL/embed FlipHTML.</p>
                                </div>
                            </div>
                        </div>

                        <x-admin.select label="Kategori" model="form.category_id" :options="collect($desaCantikCategories)
                            ->pluck('name', 'id')
                            ->prepend('Pilih kategori', '')
                            ->all()" />
                        <x-admin.input label="Judul" model="form.title" class="lg:col-span-2" />
                        <x-admin.input label="Tanggal Publikasi" model="form.published_at" type="date" />
                        <x-admin.select label="Status" model="form.is_published" :options="[1 => 'Tayang', 0 => 'Draft']" />

                        @if ($selectedDesaCantikType === 'publication')
                            <x-admin.select label="Jenis Publikasi" model="form.content_type" :options="[
                                'pdf' => 'File PDF',
                                'url' => 'URL / Tautan',
                                'fliphtml' => 'URL / Embed FlipHTML',
                            ]" />

                            <div class="lg:col-span-3">
                                <label class="text-sm font-bold">Thumbnail Publikasi (opsional, maks. 10MB)</label>
                                <input type="file" wire:model="desaCantikImageUpload" accept="image/*"
                                    class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                                @error('desaCantikImageUpload')
                                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                @enderror
                                <div wire:loading wire:target="desaCantikImageUpload"
                                    class="mt-1 text-sm text-zinc-500">Mengunggah thumbnail...</div>
                                @if ($desaCantikImageUpload)
                                    <img src="{{ $desaCantikImageUpload->temporaryUrl() }}"
                                        alt="Preview thumbnail publikasi"
                                        class="mt-2 h-40 w-full rounded-md object-cover">
                                @elseif($form['image_url'])
                                    <img src="{{ $form['image_url'] }}" alt="Thumbnail publikasi saat ini"
                                        class="mt-2 h-40 w-full rounded-md object-cover">
                                @endif
                            </div>

                            @if ($form['content_type'] === 'pdf')
                                <div class="lg:col-span-3">
                                    <label class="text-sm font-bold">File PDF Publikasi (maks. 10MB)</label>
                                    <input type="file" wire:model="desaCantikDocumentUpload"
                                        accept="application/pdf"
                                        class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                                    @error('desaCantikDocumentUpload')
                                        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                    @enderror
                                    <div wire:loading wire:target="desaCantikDocumentUpload"
                                        class="mt-1 text-sm text-zinc-500">Mengunggah PDF...</div>
                                    @if ($form['file_url'])
                                        <a href="{{ $form['file_url'] }}" target="_blank"
                                            class="mt-2 inline-flex text-sm font-bold text-emerald-700">Lihat PDF saat
                                            ini</a>
                                    @endif
                                </div>
                            @else
                                <x-admin.textarea
                                    label="{{ $form['content_type'] === 'fliphtml' ? 'URL / Embed FlipHTML' : 'URL Publikasi' }}"
                                    model="form.external_url"
                                    placeholder="{{ $form['content_type'] === 'fliphtml' ? 'Tempel URL atau kode iframe FlipHTML' : 'https://...' }}"
                                    class="lg:col-span-3" />
                            @endif
                        @else
                            <input type="hidden" wire:model="form.content_type">
                            <div class="lg:col-span-3">
                                <label class="text-sm font-bold">Gambar Infografis (maks. 10MB)</label>
                                <input type="file" wire:model="desaCantikImageUpload" accept="image/*"
                                    class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                                @error('desaCantikImageUpload')
                                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                @enderror
                                <div wire:loading wire:target="desaCantikImageUpload"
                                    class="mt-1 text-sm text-zinc-500">Mengunggah gambar...</div>
                                @if ($desaCantikImageUpload)
                                    <img src="{{ $desaCantikImageUpload->temporaryUrl() }}" alt="Preview infografis"
                                        class="mt-2 max-h-[30rem] w-full rounded-md object-contain">
                                @elseif($form['image_url'])
                                    <img src="{{ $form['image_url'] }}" alt="Infografis saat ini"
                                        class="mt-2 max-h-[30rem] w-full rounded-md object-contain">
                                @endif
                            </div>
                        @endif

                        <x-admin.textarea label="Deskripsi" model="form.description" class="lg:col-span-3" />
                    @endif

                    <div class="flex justify-end gap-2 border-t border-zinc-200 pt-5 sm:col-span-2 lg:col-span-3">
                        <button type="button" wire:click="closeModal"
                            class="inline-flex min-h-11 items-center rounded-md border border-zinc-300 px-4 text-sm font-bold">Batal</button>
                        <button
                            class="inline-flex min-h-11 items-center rounded-md bg-emerald-600 px-4 text-sm font-black text-white">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
