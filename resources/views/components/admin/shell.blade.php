@props(['title' => 'Dashboard', 'description' => null])

@php
    $moduleActive = fn (string $module): bool => request()->routeIs('admin.module') && request()->route('module') === $module;
    $referenceActive = fn (string $reference): bool => request()->routeIs('admin.references.index') && request()->route('reference') === $reference;
    $isDeveloper = auth()->user()?->role === 'developer';
    $canManageSystem = in_array(auth()->user()?->role, ['developer', 'admin_desa'], true);
    $activeVillageId = \App\Support\CurrentVillage::id();
    $cmsVersion = \App\Support\ApplicationVersions::backend()['current_version'] ?? null;
    $defaultCmsLogo = asset('images/cms/ogan-ilir-logo.gif');
    $featureEnabled = fn (string $feature): bool => \App\Support\VillageFeatures::enabled($activeVillageId, $feature, auth()->user());
    $dashboardLink = [
        'label' => 'Dasbor',
        'href' => route('admin.dashboard'),
        'icon' => 'fa-gauge-high',
        'active' => request()->routeIs('admin.dashboard'),
    ];
    $profileLink = [
        'label' => 'Profil User',
        'href' => route('admin.profile'),
        'icon' => 'fa-user-gear',
        'active' => request()->routeIs('admin.profile'),
    ];

    $groups = [
        'Kelola Konten' => [
            'icon' => 'fa-folder-open',
            'links' => [
                ['label' => 'Artikel', 'href' => route('admin.posts.index'), 'icon' => 'fa-newspaper', 'active' => request()->routeIs('admin.posts.*'), 'feature' => 'articles'],
                ['label' => 'Halaman', 'href' => route('admin.pages.index'), 'icon' => 'fa-file-lines', 'active' => request()->routeIs('admin.pages.*'), 'feature' => 'pages'],
                ['label' => 'Banner', 'href' => route('admin.banners.index'), 'icon' => 'fa-panorama', 'active' => request()->routeIs('admin.banners.*'), 'feature' => 'banners'],
                ['label' => 'Galeri', 'href' => route('admin.gallery.index'), 'icon' => 'fa-images', 'active' => request()->routeIs('admin.gallery.*'), 'feature' => 'gallery'],
                ['label' => 'Unduhan', 'href' => route('admin.module', 'files'), 'icon' => 'fa-download', 'active' => $moduleActive('files'), 'feature' => 'downloads'],
                ['label' => 'Desa Cantik', 'href' => route('admin.module', 'desa-cantik'), 'icon' => 'fa-chart-simple', 'active' => $moduleActive('desa-cantik'), 'feature' => 'desa_cantik'],
            ],
        ],
        'Data Desa' => [
            'icon' => 'fa-database',
            'links' => [
                ['label' => 'Perangkat Desa', 'href' => route('admin.module', 'officials'), 'icon' => 'fa-id-badge', 'active' => $moduleActive('officials'), 'feature' => 'officials'],
                ['label' => 'UMKM', 'href' => route('admin.module', 'businesses'), 'icon' => 'fa-store', 'active' => $moduleActive('businesses'), 'feature' => 'businesses'],
                ['label' => 'BUMDES', 'href' => route('admin.module', 'bumdes'), 'icon' => 'fa-building-user', 'active' => $moduleActive('bumdes'), 'feature' => 'bumdes'],
                ['label' => 'Pembangunan', 'href' => route('admin.module', 'projects'), 'icon' => 'fa-person-digging', 'active' => $moduleActive('projects'), 'feature' => 'projects'],
                ['label' => 'Peta Sebaran', 'href' => route('admin.module', 'maps'), 'icon' => 'fa-map-location-dot', 'active' => $moduleActive('maps'), 'feature' => 'maps'],
                ['label' => 'Anggaran', 'href' => route('admin.module', 'budgets'), 'icon' => 'fa-chart-pie', 'active' => $moduleActive('budgets'), 'feature' => 'budgets'],
                ['label' => 'Statistik', 'href' => route('admin.module', 'demographics'), 'icon' => 'fa-chart-column', 'active' => $moduleActive('demographics'), 'feature' => 'statistics'],
            ],
        ],
        'Referensi' => [
            'icon' => 'fa-book',
            'links' => [
                ['label' => 'Kategori Berita', 'href' => route('admin.references.index', 'content-categories'), 'icon' => 'fa-tags', 'active' => $referenceActive('content-categories'), 'feature' => 'articles'],
                ['label' => 'Kategori UMKM', 'href' => route('admin.references.index', 'business-categories'), 'icon' => 'fa-store', 'active' => $referenceActive('business-categories'), 'feature' => 'businesses'],
                ['label' => 'Kategori BUMDES', 'href' => route('admin.references.index', 'bumdes-categories'), 'icon' => 'fa-building-user', 'active' => $referenceActive('bumdes-categories'), 'feature' => 'bumdes'],
            ],
        ],
        'Sistem' => [
            'icon' => 'fa-sliders',
            'links' => [
                ['label' => 'Pengaturan Desa', 'href' => route('admin.settings.index'), 'icon' => 'fa-gear', 'active' => request()->routeIs('admin.settings.*')],
                ['label' => 'Styling Website', 'href' => route('admin.styling.index'), 'icon' => 'fa-palette', 'active' => request()->routeIs('admin.styling.*')],
                ['label' => 'Shortcut Beranda', 'href' => route('admin.home-shortcuts.index'), 'icon' => 'fa-link', 'active' => request()->routeIs('admin.home-shortcuts.*')],
                ['label' => 'Menu Dinamis', 'href' => route('admin.module', 'menus'), 'icon' => 'fa-bars-staggered', 'active' => $moduleActive('menus'), 'feature' => 'menus'],
                ['label' => 'Widget Website', 'href' => route('admin.widgets.index'), 'icon' => 'fa-puzzle-piece', 'active' => request()->routeIs('admin.widgets.*'), 'feature' => 'widgets'],
                ['label' => 'Pengguna', 'href' => route('admin.users.index'), 'icon' => 'fa-users-gear', 'active' => request()->routeIs('admin.users.*')],
                ['label' => 'Statistik Pengunjung', 'href' => route('admin.visitor-statistics.index'), 'icon' => 'fa-chart-line', 'active' => request()->routeIs('admin.visitor-statistics.*')],
                ['label' => 'Versi Aplikasi', 'href' => route('admin.application-versions.index'), 'icon' => 'fa-code-branch', 'active' => request()->routeIs('admin.application-versions.*')],
            ],
        ],
    ];

    if (! $canManageSystem) {
        unset($groups['Sistem']);
    }

    $impersonateManager = app(\Lab404\Impersonate\Services\ImpersonateManager::class);
    $isImpersonating = $impersonateManager->isImpersonating();
    $impersonator = $isImpersonating ? $impersonateManager->getImpersonator() : null;

    if ($isDeveloper) {
        $groups['Developer'] = [
            'icon' => 'fa-code',
            'links' => [
                ['label' => 'Manajemen Desa', 'href' => route('admin.villages.index'), 'icon' => 'fa-city', 'active' => request()->routeIs('admin.villages.*')],
            ],
        ];
    }

    $groups = collect($groups)
        ->map(function (array $group) use ($featureEnabled): array {
            $group['links'] = collect($group['links'])
                ->filter(fn (array $link): bool => ! isset($link['feature']) || $featureEnabled($link['feature']))
                ->values()
                ->all();

            return $group;
        })
        ->filter(fn (array $group): bool => count($group['links']) > 0)
        ->all();

    $links = collect([$dashboardLink, $profileLink])
        ->merge(collect($groups)->flatMap(fn (array $group): array => $group['links']))
        ->all();
    $activeVillage = \Illuminate\Support\Facades\DB::table('villages')->where('id', $activeVillageId)->first();
    $villages = $isDeveloper
        ? \Illuminate\Support\Facades\DB::table('villages')->orderBy('name')->get(['id', 'name', 'district', 'regency'])
        : collect();
    $activeVillageLogo = $activeVillage?->logo_url ?: $defaultCmsLogo;
    $activeVillageFavicon = $activeVillage?->favicon_url ?: $defaultCmsLogo;
    $activeVillageName = $activeVillage?->name ?? 'Kabupaten Ogan Ilir';
    $normalizedTitle = \Illuminate\Support\Str::lower($title);
    $titleIcon = match (true) {
        str_contains($normalizedTitle, 'dasbor') => 'fa-gauge-high',
        str_contains($normalizedTitle, 'artikel') => 'fa-newspaper',
        str_contains($normalizedTitle, 'halaman') => 'fa-file-lines',
        str_contains($normalizedTitle, 'banner') => 'fa-panorama',
        str_contains($normalizedTitle, 'galeri') => 'fa-images',
        str_contains($normalizedTitle, 'widget') => 'fa-puzzle-piece',
        str_contains($normalizedTitle, 'menu') => 'fa-bars-staggered',
        str_contains($normalizedTitle, 'unduhan'), str_contains($normalizedTitle, 'download') => 'fa-download',
        str_contains($normalizedTitle, 'desa cantik') => 'fa-chart-simple',
        str_contains($normalizedTitle, 'perangkat') => 'fa-id-badge',
        str_contains($normalizedTitle, 'umkm') => 'fa-store',
        str_contains($normalizedTitle, 'bumdes') => 'fa-building-user',
        str_contains($normalizedTitle, 'pembangunan') => 'fa-person-digging',
        str_contains($normalizedTitle, 'peta') => 'fa-map-location-dot',
        str_contains($normalizedTitle, 'anggaran') => 'fa-chart-pie',
        str_contains($normalizedTitle, 'statistik') => 'fa-chart-column',
        str_contains($normalizedTitle, 'referensi') => 'fa-book',
        str_contains($normalizedTitle, 'pengaturan') => 'fa-gear',
        str_contains($normalizedTitle, 'styling') => 'fa-palette',
        str_contains($normalizedTitle, 'shortcut') => 'fa-link',
        str_contains($normalizedTitle, 'pengguna') => 'fa-users-gear',
        str_contains($normalizedTitle, 'profil') => 'fa-user-gear',
        str_contains($normalizedTitle, 'versi') => 'fa-code-branch',
        str_contains($normalizedTitle, 'desa') => 'fa-city',
        default => 'fa-layer-group',
    };
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="editor-upload-url" content="{{ route('admin.editor.upload') }}">
    <link rel="icon" href="{{ $activeVillageFavicon }}">
    @if($activeVillage?->description)
        <meta name="description" content="{{ $activeVillage->description }}">
    @endif
    <title>{{ $title }} - {{ $activeVillageName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="admin-cms bg-[#f5f7f4] text-zinc-900 antialiased">
    <div class="min-h-dvh lg:grid lg:grid-cols-[280px_1fr]">
        <aside class="hidden border-r border-emerald-950/10 bg-[#10241f] text-white lg:block">
            <div class="sticky top-0 flex h-dvh flex-col">
                <div class="border-b border-white/10 p-5">
                    <div class="flex items-center gap-3">
                        <img src="{{ $activeVillageLogo }}" alt="Logo {{ $activeVillageName }}" class="size-11 rounded-lg border border-white/15 bg-white object-contain p-1">
                        <div>
                            <div class="font-black">{{ $activeVillageName }}</div>
                            <div class="text-xs text-emerald-100/70">Panel Admin</div>
                        </div>
                    </div>
                </div>
                <nav class="flex-1 space-y-2 overflow-y-auto p-4 text-sm font-semibold">
                    <a href="{{ $dashboardLink['href'] }}" class="flex min-h-11 items-center gap-3 rounded-md px-3 {{ $dashboardLink['active'] ? 'bg-amber-300 text-emerald-950 shadow-sm' : 'text-emerald-50/80 hover:bg-white/10 hover:text-white' }}">
                        <i class="fa-solid {{ $dashboardLink['icon'] }} w-5 text-center"></i>
                        <span>{{ $dashboardLink['label'] }}</span>
                    </a>

                    @foreach($groups as $groupLabel => $group)
                        @php($groupActive = collect($group['links'])->contains(fn (array $link): bool => $link['active']))
                        <details class="group rounded-md" @if($groupActive) open @endif>
                            <summary class="flex min-h-11 cursor-pointer list-none items-center gap-3 rounded-md px-3 text-emerald-50/80 transition hover:bg-white/10 hover:text-white [&::-webkit-details-marker]:hidden {{ $groupActive ? 'bg-white/10 text-white' : '' }}">
                                <i class="fa-solid {{ $group['icon'] }} w-5 text-center"></i>
                                <span class="flex-1">{{ $groupLabel }}</span>
                                <i class="fa-solid fa-chevron-down text-xs text-emerald-100/50 transition-transform duration-200 group-open:rotate-180"></i>
                            </summary>
                            <div class="ml-5 mt-1 space-y-1 border-l border-white/10 pl-3">
                                @foreach($group['links'] as $link)
                                    <a href="{{ $link['href'] }}" class="flex min-h-10 items-center gap-3 rounded-md px-3 {{ $link['active'] ? 'bg-amber-300 text-emerald-950 shadow-sm' : 'text-emerald-50/70 hover:bg-white/10 hover:text-white' }}">
                                        <i class="fa-solid {{ $link['icon'] }} w-5 text-center"></i>
                                        <span>{{ $link['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </nav>
                <a href="{{ route('admin.application-versions.index') }}" class="group flex items-center justify-between gap-3 border-t border-white/10 px-5 py-4 text-xs text-emerald-50/60 transition hover:bg-white/10 hover:text-white">
                    <span class="flex items-center gap-2 font-bold">
                        <i class="fa-solid fa-code-branch"></i>
                        CMS Desa Ogan Ilir
                    </span>
                    <span class="rounded-full bg-amber-300 px-2.5 py-1 font-black text-emerald-950">v{{ $cmsVersion ?? '-' }}</span>
                </a>
            </div>
        </aside>

        <div>
            <header class="sticky top-0 z-30 border-b border-emerald-950/10 bg-white/90 backdrop-blur">
                @if($isImpersonating)
                    <div class="flex flex-col gap-3 border-b border-amber-300 bg-amber-50 px-4 py-3 text-amber-950 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-user-secret"></i>
                            <div>
                                <div class="text-sm font-black">Mode Impersonate: {{ auth()->user()->name }}</div>
                                <div class="text-xs font-semibold text-amber-800">Masuk dari akun {{ $impersonator?->name ?? 'Developer' }}</div>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.impersonation.leave') }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-md bg-amber-900 px-4 text-sm font-black text-white transition hover:bg-amber-950 focus:outline-none focus:ring-2 focus:ring-amber-700 focus:ring-offset-2 focus:ring-offset-amber-50">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                                Keluar Impersonate
                            </button>
                        </form>
                    </div>
                @endif
                <div class="grid gap-4 px-4 py-4 sm:px-6 lg:px-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,34rem)_auto] xl:items-center">
                    <div class="min-w-0">
                        <div class="flex items-center gap-3">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-emerald-950 text-amber-300 shadow-sm">
                                <i class="fa-solid {{ $titleIcon }}"></i>
                            </span>
                            <div class="min-w-0">
                                <h1 class="text-xl font-black tracking-normal text-emerald-950">{{ $title }}</h1>
                                @if($description)
                                    <p class="mt-1 text-sm text-zinc-500">{{ $description }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    <livewire:admin.global-search />
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        @if($isImpersonating)
                            <form method="POST" action="{{ route('admin.impersonation.leave') }}">
                                @csrf
                                <button type="submit" title="Kembali ke akun Developer asal" class="inline-flex min-h-11 items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 text-sm font-black text-amber-900 hover:bg-amber-100">
                                    <i class="fa-solid fa-user-shield"></i>
                                    <span class="hidden sm:inline">Keluar Impersonate</span>
                                </button>
                            </form>
                        @endif
                        @if($isDeveloper)
                            <form method="POST" action="{{ route('admin.village-context') }}" class="flex min-h-11 items-center">
                                @csrf
                                <select id="active-village" name="village_id" onchange="this.form.submit()" data-placeholder="Pilih desa aktif" class="tom-select min-h-9 rounded-md border-0 bg-transparent text-sm font-bold text-zinc-900 focus:outline-none">
                                    @foreach($villages as $village)
                                        <option value="{{ $village->id }}" @selected((int) $village->id === $activeVillageId)>
                                            {{ $village->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        @endif
                        <a href="{{ route('admin.profile') }}" class="inline-flex min-h-11 items-center gap-2 rounded-md border border-zinc-300 px-3 text-sm font-bold">
                            <i class="fa-solid fa-user-gear"></i>
                            <span class="hidden sm:inline">Profil</span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="inline-flex min-h-11 items-center gap-2 rounded-md bg-zinc-950 px-3 text-sm font-bold text-white">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                                Keluar
                            </button>
                        </form>
                    </div>
                </div>
                <nav class="flex gap-2 overflow-x-auto border-t border-zinc-200 px-4 py-2 text-sm font-semibold lg:hidden">
                    @foreach($links as $link)
                        <a href="{{ $link['href'] }}" class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-md px-3 {{ $link['active'] ? 'bg-emerald-50 text-emerald-800' : 'text-zinc-600' }}">
                            <i class="fa-solid {{ $link['icon'] }} text-center"></i>
                            <span>{{ $link['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
