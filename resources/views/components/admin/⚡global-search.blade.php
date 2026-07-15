<?php

use App\Support\CurrentVillage;
use App\Support\VillageFeatures;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component {
    public string $query = '';

    public function getResultsProperty(): array
    {
        $term = trim($this->query);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $results = $this->featureResults($term);

        foreach ($this->contentResults($term) as $result) {
            $results[] = $result;
        }

        return collect($results)->take(14)->values()->all();
    }

    private function featureResults(string $term): array
    {
        return collect($this->featureEntries())
            ->filter(fn(array $entry): bool => $this->canAccess($entry))
            ->filter(fn(array $entry): bool => $this->matches($entry, $term))
            ->map(
                fn(array $entry): array => [
                    'title' => $entry['title'],
                    'subtitle' => $entry['subtitle'],
                    'badge' => $entry['badge'] ?? 'Fitur',
                    'href' => $entry['href'],
                    'icon' => $entry['icon'],
                ],
            )
            ->values()
            ->all();
    }

    private function contentResults(string $term): array
    {
        $results = [];
        $user = auth()->user();
        $villageId = CurrentVillage::id();
        $search = '%' . Str::lower($term) . '%';

        if ($this->canAccess(['roles' => ['developer', 'admin_desa', 'editor'], 'feature' => 'articles'])) {
            $rows = DB::table('posts')
                ->leftJoin('content_categories', 'posts.category_id', '=', 'content_categories.id')
                ->where('posts.village_id', $villageId)
                ->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(posts.title) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(posts.excerpt, \'\')) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(content_categories.name, \'\')) LIKE ?', [$search]);
                })
                ->latest('posts.updated_at')
                ->limit(4)
                ->get(['posts.id', 'posts.title', 'posts.excerpt', 'posts.status', 'content_categories.name as category_name']);

            foreach ($rows as $row) {
                $results[] = $this->result('Artikel', $row->title, $row->category_name ?: $row->status, route('admin.posts.edit', $row->id), 'fa-newspaper');
            }
        }

        if ($this->canAccess(['roles' => ['developer', 'admin_desa', 'editor'], 'feature' => 'pages'])) {
            $rows = DB::table('pages')
                ->where('village_id', $villageId)
                ->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(title) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(excerpt, \'\')) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(status, \'\')) LIKE ?', [$search]);
                })
                ->latest('updated_at')
                ->limit(3)
                ->get(['id', 'title', 'excerpt', 'status']);

            foreach ($rows as $row) {
                $results[] = $this->result('Halaman', $row->title, $row->status, route('admin.pages.edit', $row->id), 'fa-file-lines');
            }
        }

        if ($this->canAccess(['roles' => ['developer', 'admin_desa', 'editor'], 'feature' => 'banners'])) {
            $rows = DB::table('hero_banners')
                ->where('village_id', $villageId)
                ->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(title) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(button_label) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(subtitle, \'\')) LIKE ?', [$search]);
                })
                ->latest('updated_at')
                ->limit(2)
                ->get(['title', 'subtitle']);

            foreach ($rows as $row) {
                $results[] = $this->result('Banner', $row->title, $row->subtitle ?: 'Banner hero website', route('admin.banners.index'), 'fa-panorama');
            }
        }

        if ($this->canAccess(['roles' => ['developer', 'admin_desa', 'editor'], 'feature' => 'gallery'])) {
            $rows = DB::table('gallery_albums')
                ->where('village_id', $villageId)
                ->where(function ($query) use ($search): void {
                    $query->whereRaw('LOWER(title) LIKE ?', [$search])->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$search]);
                })
                ->latest('updated_at')
                ->limit(2)
                ->get(['title', 'description']);

            foreach ($rows as $row) {
                $results[] = $this->result('Galeri', $row->title, $row->description ?: 'Album foto desa', route('admin.gallery.index'), 'fa-images');
            }
        }

        if ($this->canAccess(['roles' => ['developer', 'admin_desa', 'editor'], 'feature' => 'gallery'])) {
            $rows = DB::table('gallery_photos')
                ->where('village_id', $villageId)
                ->where(function ($query) use ($search): void {
                    $query->whereRaw('LOWER(title) LIKE ?', [$search])->orWhereRaw('LOWER(caption) LIKE ?', [$search]);
                })
                ->latest('updated_at')
                ->limit(2)
                ->get(['title', 'caption']);

            foreach ($rows as $row) {
                $results[] = $this->result('Galeri', $row->title, $row->caption ?: 'Album foto desa', route('admin.gallery.index'), 'fa-images');
            }
        }

        if ($this->canAccess(['roles' => ['developer', 'admin_desa', 'editor'], 'feature' => 'gallery'])) {
            $rows = DB::table('gallery_videos')
                ->where('village_id', $villageId)
                ->where(function ($query) use ($search): void {
                    $query->whereRaw('LOWER(title) LIKE ?', [$search])->orWhereRaw('LOWER(caption) LIKE ?', [$search]);
                })
                ->latest('updated_at')
                ->limit(2)
                ->get(['title', 'caption']);

            foreach ($rows as $row) {
                $results[] = $this->result('Galeri', $row->title, $row->caption ?: 'Album foto desa', route('admin.gallery.index'), 'fa-images');
            }
        }

        if ($this->canAccess(['roles' => ['developer', 'admin_desa', 'editor'], 'feature' => 'menus'])) {
            $rows = DB::table('navigation_items')
                ->where('village_id', $villageId)
                ->where(function ($query) use ($search): void {
                    $query->whereRaw('LOWER(label) LIKE ?', [$search])->orWhereRaw('LOWER(COALESCE(url, \'\')) LIKE ?', [$search]);
                })
                ->orderBy('sort_order')
                ->limit(8)
                ->get(['label', 'url']);

            foreach ($rows as $row) {
                if (!$this->menuUrlAllowed($row->url)) {
                    continue;
                }

                $results[] = $this->result('Menu Dinamis', $row->label, $row->url ?: 'Item navigasi publik', route('admin.module', 'menus'), 'fa-bars-staggered');
            }
        }

        if ($this->canAccess(['roles' => ['developer', 'admin_desa', 'editor'], 'feature' => 'downloads'])) {
            $rows = DB::table('downloadable_files')
                ->where('village_id', $villageId)
                ->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(title) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(file_url, \'\')) LIKE ?', [$search]);
                })
                ->latest('updated_at')
                ->limit(3)
                ->get(['title', 'description']);

            foreach ($rows as $row) {
                $results[] = $this->result('Unduhan', $row->title, $row->description ?: 'Dokumen publik', route('admin.module', 'files'), 'fa-download');
            }
        }

        if ($this->canAccess(['roles' => ['developer', 'admin_desa', 'editor'], 'feature' => 'desa_cantik'])) {
            $rows = DB::table('desa_cantik_posts')
                ->leftJoin('desa_cantik_categories', 'desa_cantik_posts.category_id', '=', 'desa_cantik_categories.id')
                ->where('desa_cantik_posts.village_id', $villageId)
                ->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(desa_cantik_posts.title) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(desa_cantik_posts.description, \'\')) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(desa_cantik_posts.content_type, \'\')) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(desa_cantik_categories.name, \'\')) LIKE ?', [$search]);
                })
                ->latest('desa_cantik_posts.updated_at')
                ->limit(3)
                ->get(['desa_cantik_posts.title', 'desa_cantik_posts.description', 'desa_cantik_categories.name as category_name']);

            foreach ($rows as $row) {
                $results[] = $this->result('Desa Cantik', $row->title, $row->category_name ?: $row->description, route('admin.module', 'desa-cantik'), 'fa-chart-simple');
            }
        }

        if ($this->canAccess(['roles' => ['developer', 'admin_desa', 'editor'], 'feature' => 'businesses'])) {
            $rows = DB::table('businesses')
                ->where('village_id', $villageId)
                ->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(owner_name, \'\')) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$search]);
                })
                ->latest('updated_at')
                ->limit(3)
                ->get(['name', 'owner_name', 'description']);

            foreach ($rows as $row) {
                $results[] = $this->result('UMKM', $row->name, $row->owner_name ?: $row->description, route('admin.module', 'businesses'), 'fa-store');
            }
        }

        if ($this->canAccess(['roles' => ['developer', 'admin_desa', 'editor'], 'feature' => 'bumdes'])) {
            $rows = DB::table('bumdes')
                ->where('village_id', $villageId)
                ->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(manager_name, \'\')) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$search]);
                })
                ->latest('updated_at')
                ->limit(3)
                ->get(['name', 'manager_name', 'description']);

            foreach ($rows as $row) {
                $results[] = $this->result('BUMDES', $row->name, $row->manager_name ?: $row->description, route('admin.module', 'bumdes'), 'fa-building-user');
            }
        }

        if ($this->canAccess(['roles' => ['developer', 'admin_desa', 'editor'], 'feature' => 'projects'])) {
            $rows = DB::table('development_projects')
                ->where('village_id', $villageId)
                ->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(title) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(location, \'\')) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(source_fund, \'\')) LIKE ?', [$search]);
                })
                ->latest('updated_at')
                ->limit(3)
                ->get(['title', 'location', 'source_fund']);

            foreach ($rows as $row) {
                $results[] = $this->result('Pembangunan', $row->title, $row->location ?: $row->source_fund, route('admin.module', 'projects'), 'fa-person-digging');
            }
        }

        if ($this->canAccess(['roles' => ['developer', 'admin_desa'], 'feature' => 'widgets'])) {
            $rows = DB::table('village_widgets')
                ->where('village_id', $villageId)
                ->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(title) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(widget_type) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(placement) LIKE ?', [$search]);
                })
                ->latest('updated_at')
                ->limit(3)
                ->get(['title', 'widget_type', 'placement']);

            foreach ($rows as $row) {
                $feature = $this->widgetFeature((string) $row->widget_type);

                if ($feature && !VillageFeatures::enabled($villageId, $feature, $user)) {
                    continue;
                }

                $results[] = $this->result('Widget', $row->title, "{$row->widget_type} - {$row->placement}", route('admin.widgets.index'), 'fa-puzzle-piece');
            }
        }

        if ($this->canAccess(['roles' => ['developer', 'admin_desa']])) {
            $rows = DB::table('users')
                ->leftJoin('villages', 'users.village_id', '=', 'villages.id')
                ->when($user?->role !== 'developer', fn($query) => $query->where('users.role', '!=', 'developer')->where('users.village_id', $villageId))
                ->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(users.name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(users.username) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(users.email) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(villages.name, \'\')) LIKE ?', [$search]);
                })
                ->orderBy('users.name')
                ->limit(3)
                ->get(['users.name', 'users.role', 'villages.name as village_name']);

            foreach ($rows as $row) {
                $results[] = $this->result('Pengguna', $row->name, trim("{$row->role} {$row->village_name}"), route('admin.users.index'), 'fa-users-gear');
            }
        }

        if ($user?->role === 'developer') {
            $rows = DB::table('villages')
                ->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(district, \'\')) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(regency, \'\')) LIKE ?', [$search]);
                })
                ->orderBy('name')
                ->limit(3)
                ->get(['name', 'district', 'regency']);

            foreach ($rows as $row) {
                $results[] = $this->result('Manajemen Desa', $row->name, trim("{$row->district}, {$row->regency}", ' ,'), route('admin.villages.index'), 'fa-city');
            }
        }

        return $results;
    }

    private function result(string $badge, string $title, ?string $subtitle, string $href, string $icon): array
    {
        return [
            'title' => $title,
            'subtitle' => $subtitle ?: 'Buka halaman terkait',
            'badge' => $badge,
            'href' => $href,
            'icon' => $icon,
        ];
    }

    private function featureEntries(): array
    {
        return [
            ['title' => 'Dasbor', 'subtitle' => 'Ringkasan aktivitas CMS desa', 'href' => route('admin.dashboard'), 'icon' => 'fa-gauge-high'],
            ['title' => 'Profil User', 'subtitle' => 'Data akun dan password', 'href' => route('admin.profile'), 'icon' => 'fa-user-gear'],
            ['title' => 'Artikel', 'subtitle' => 'Berita, pengumuman, dan kabar desa', 'href' => route('admin.posts.index'), 'icon' => 'fa-newspaper', 'feature' => 'articles', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'Halaman', 'subtitle' => 'Konten halaman khusus website publik', 'href' => route('admin.pages.index'), 'icon' => 'fa-file-lines', 'feature' => 'pages', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'Banner', 'subtitle' => 'Carousel dan hero website desa', 'href' => route('admin.banners.index'), 'icon' => 'fa-panorama', 'feature' => 'banners', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'Galeri', 'subtitle' => 'Album dan foto kegiatan desa', 'href' => route('admin.gallery.index'), 'icon' => 'fa-images', 'feature' => 'gallery', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'Menu Dinamis', 'subtitle' => 'Navbar dan submenu website publik', 'href' => route('admin.module', 'menus'), 'icon' => 'fa-bars-staggered', 'feature' => 'menus', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'Unduhan', 'subtitle' => 'Berkas dan dokumen publik', 'href' => route('admin.module', 'files'), 'icon' => 'fa-download', 'feature' => 'downloads', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'Desa Cantik', 'subtitle' => 'Publikasi dan infografis statistik', 'href' => route('admin.module', 'desa-cantik'), 'icon' => 'fa-chart-simple', 'feature' => 'desa_cantik', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'Widget Website', 'subtitle' => 'Komponen tambahan website publik', 'href' => route('admin.widgets.index'), 'icon' => 'fa-puzzle-piece', 'feature' => 'widgets', 'roles' => ['developer', 'admin_desa']],
            ['title' => 'Perangkat Desa', 'subtitle' => 'Absensi perangkat desa dari SIDESI', 'href' => route('admin.module', 'officials'), 'icon' => 'fa-id-badge', 'feature' => 'officials', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'UMKM', 'subtitle' => 'Usaha dan produk unggulan desa', 'href' => route('admin.module', 'businesses'), 'icon' => 'fa-store', 'feature' => 'businesses', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'BUMDES', 'subtitle' => 'Badan usaha milik desa', 'href' => route('admin.module', 'bumdes'), 'icon' => 'fa-building-user', 'feature' => 'bumdes', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'Pembangunan', 'subtitle' => 'Progres pembangunan desa', 'href' => route('admin.module', 'projects'), 'icon' => 'fa-person-digging', 'feature' => 'projects', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'Peta Sebaran', 'subtitle' => 'Sebaran fasilitas dan bantuan', 'href' => route('admin.module', 'maps'), 'icon' => 'fa-map-location-dot', 'feature' => 'maps', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'Anggaran', 'subtitle' => 'Transparansi APBDes dari SIDESI', 'href' => route('admin.module', 'budgets'), 'icon' => 'fa-chart-pie', 'feature' => 'budgets', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'Statistik', 'subtitle' => 'Data penduduk, usia, pendidikan, dan pekerjaan', 'href' => route('admin.module', 'demographics'), 'icon' => 'fa-chart-column', 'feature' => 'statistics', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'Kategori Berita', 'subtitle' => 'Referensi kategori artikel', 'href' => route('admin.references.index', 'content-categories'), 'icon' => 'fa-tags', 'feature' => 'articles', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'Kategori UMKM', 'subtitle' => 'Referensi kategori usaha desa', 'href' => route('admin.references.index', 'business-categories'), 'icon' => 'fa-store', 'feature' => 'businesses', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'Kategori BUMDES', 'subtitle' => 'Referensi kategori badan usaha milik desa', 'href' => route('admin.references.index', 'bumdes-categories'), 'icon' => 'fa-building-user', 'feature' => 'bumdes', 'roles' => ['developer', 'admin_desa', 'editor']],
            ['title' => 'Pengaturan Desa', 'subtitle' => 'Identitas dan informasi dasar desa', 'href' => route('admin.settings.index'), 'icon' => 'fa-gear', 'roles' => ['developer', 'admin_desa']],
            ['title' => 'Styling Website', 'subtitle' => 'Theme, warna, font, dan preview frontend', 'href' => route('admin.styling.index'), 'icon' => 'fa-palette', 'roles' => ['developer', 'admin_desa']],
            ['title' => 'Shortcut Beranda', 'subtitle' => 'Label dan link cepat di bawah banner', 'href' => route('admin.home-shortcuts.index'), 'icon' => 'fa-link', 'roles' => ['developer', 'admin_desa']],
            ['title' => 'Pengguna', 'subtitle' => 'Role dan akun CMS desa', 'href' => route('admin.users.index'), 'icon' => 'fa-users-gear', 'roles' => ['developer', 'admin_desa']],
            ['title' => 'Versi Aplikasi', 'subtitle' => 'Metadata versi backend dan frontend', 'href' => route('admin.application-versions.index'), 'icon' => 'fa-code-branch', 'roles' => ['developer', 'admin_desa']],
            ['title' => 'Manajemen Desa', 'subtitle' => 'Kelola daftar desa', 'href' => route('admin.villages.index'), 'icon' => 'fa-city', 'roles' => ['developer']],
            ['title' => 'Statistik Pengunjung', 'subtitle' => 'Analitik kunjungan website publik', 'href' => route('admin.visitor-statistics.index'), 'icon' => 'fa-chart-line', 'roles' => ['developer', 'admin_desa']],
        ];
    }

    private function canAccess(array $entry): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if (isset($entry['roles']) && !in_array($user->role, $entry['roles'], true)) {
            return false;
        }

        if (isset($entry['feature']) && !VillageFeatures::enabled(CurrentVillage::id(), $entry['feature'], $user)) {
            return false;
        }

        return true;
    }

    private function matches(array $entry, string $term): bool
    {
        $haystack = Str::lower(implode(' ', array_filter([$entry['title'] ?? '', $entry['subtitle'] ?? '', $entry['badge'] ?? '', $entry['feature'] ?? ''])));

        return str_contains($haystack, Str::lower($term));
    }

    private function menuUrlAllowed(?string $url): bool
    {
        if (!$url) {
            return true;
        }

        $path = '/' . ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        $feature = collect([
            '/artikel' => 'articles',
            '/berita' => 'articles',
            '/galeri' => 'gallery',
            '/unduhan' => 'downloads',
            '/desa-cantik' => 'desa_cantik',
            '/umkm' => 'businesses',
            '/bumdes' => 'bumdes',
            '/pembangunan' => 'projects',
            '/peta-sebaran' => 'maps',
            '/anggaran' => 'budgets',
            '/statistik' => 'statistics',
        ])->first(fn(string $feature, string $prefix): bool => $path === $prefix || str_starts_with($path, $prefix . '/'));

        return !$feature || VillageFeatures::enabled(CurrentVillage::id(), $feature, auth()->user());
    }

    private function widgetFeature(string $widgetType): ?string
    {
        return [
            'latest_articles' => 'articles',
            'population_summary' => 'statistics',
            'village_statistics' => 'statistics',
            'village_budget' => 'budgets',
            'village_officials' => 'officials',
        ][$widgetType] ?? null;
    }
}; ?>

<div x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false"
    class="relative w-full xl:max-w-xl">
    <div class="relative">
        <i
            class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-zinc-400"></i>
        <input type="search" wire:model.live.debounce.250ms="query" x-on:focus="open = true" x-on:input="open = true"
            placeholder="Cari konten atau fitur CMS..." autocomplete="off"
            class="h-11 w-full rounded-md border border-zinc-200 bg-zinc-50 pl-10 pr-3 text-sm font-semibold text-zinc-900 outline-none transition placeholder:text-zinc-400 focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100">
    </div>

    <div x-cloak x-show="open" x-transition.origin.top
        class="absolute left-0 right-0 top-[calc(100%+0.5rem)] z-50 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-2xl shadow-zinc-900/15">
        @if (mb_strlen(trim($query)) < 2)
            <div class="px-4 py-5 text-sm text-zinc-500">
                Ketik minimal 2 karakter untuk mencari fitur, artikel, halaman, dokumen, atau data CMS.
            </div>
        @elseif(count($this->results) === 0)
            <div class="px-4 py-5 text-sm text-zinc-500">
                Tidak ada hasil yang cocok atau dapat diakses oleh akun ini.
            </div>
        @else
            <div class="max-h-[24rem] overflow-y-auto py-2">
                @foreach ($this->results as $result)
                    <a href="{{ $result['href'] }}" class="flex gap-3 px-4 py-3 transition hover:bg-emerald-50">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-md bg-emerald-100 text-emerald-700">
                            <i class="fa-solid {{ $result['icon'] }}"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="truncate text-sm font-black text-zinc-900">{{ $result['title'] }}</span>
                                <span
                                    class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-zinc-500">{{ $result['badge'] }}</span>
                            </span>
                            <span
                                class="mt-1 block truncate text-xs font-semibold text-zinc-500">{{ $result['subtitle'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
