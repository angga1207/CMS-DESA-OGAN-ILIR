<x-admin.shell title="Versi Aplikasi" description="Informasi versi CMS Backend dan Frontend Website yang dibaca langsung dari file JSON.">
    <div class="space-y-5">

        <div class="grid gap-5 xl:grid-cols-2">
            @foreach($versions as $type => $application)
                <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm">
                    <header class="border-b border-zinc-200 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div class="grid size-12 shrink-0 place-items-center rounded-lg {{ $type === 'backend' ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700' }}">
                                    <i class="fa-solid {{ $type === 'backend' ? 'fa-server' : 'fa-globe' }} text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-black uppercase tracking-[0.18em] text-zinc-400">{{ $application['component'] ?? ucfirst($type) }}</p>
                                    <h2 class="mt-1 text-xl font-black text-zinc-950">{{ $application['application'] ?? 'Aplikasi Desa' }}</h2>
                                    <p class="mt-1 text-sm font-semibold text-zinc-500">{{ $application['framework'] ?? 'Framework tidak dicantumkan' }}</p>
                                </div>
                            </div>
                            <div class="rounded-lg bg-zinc-950 px-4 py-3 text-right text-white">
                                <div class="text-[10px] font-black uppercase tracking-[0.18em] text-white/55">Versi Aktif</div>
                                <div class="mt-1 text-xl font-black">v{{ $application['current_version'] ?? '-' }}</div>
                            </div>
                        </div>
                        @if($application['description'] ?? null)
                            <p class="mt-4 text-sm leading-6 text-zinc-600">{{ $application['description'] }}</p>
                        @endif
                        <div class="mt-4 flex flex-wrap gap-2 text-xs font-bold text-zinc-600">
                            <span class="rounded-full bg-zinc-100 px-3 py-1.5">
                                <i class="fa-regular fa-calendar mr-1"></i>
                                Rilis {{ $application['released_at'] ?? '-' }}
                            </span>
                            <span class="rounded-full bg-zinc-100 px-3 py-1.5">
                                <i class="fa-regular fa-file-code mr-1"></i>
                                {{ $type === 'backend' ? 'cms-backend.json' : 'public-frontend.json' }}
                            </span>
                        </div>
                    </header>

                    <div class="space-y-4 p-5">
                        <h3 class="text-sm font-black uppercase tracking-[0.14em] text-zinc-500">Riwayat Rilis</h3>
                        @forelse($releasePaginators[$type] as $release)
                            <article class="rounded-lg border border-zinc-200 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-black text-zinc-950">v{{ $release['version'] ?? '-' }}</span>
                                            <span class="rounded-full px-2 py-1 text-[10px] font-black uppercase {{ ($release['status'] ?? '') === 'stable' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                {{ $release['status'] ?? 'release' }}
                                            </span>
                                        </div>
                                        <h4 class="mt-2 font-bold text-zinc-800">{{ $release['title'] ?? 'Pembaruan aplikasi' }}</h4>
                                    </div>
                                    <time class="text-xs font-bold text-zinc-400">{{ $release['date'] ?? '-' }}</time>
                                </div>
                                @if(count($release['changes'] ?? []) > 0)
                                    <ul class="mt-4 space-y-2 text-sm leading-6 text-zinc-600">
                                        @foreach($release['changes'] as $change)
                                            <li class="flex items-start gap-2">
                                                <i class="fa-solid fa-check mt-1.5 text-[10px] text-emerald-600"></i>
                                                <span>{{ $change }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </article>
                        @empty
                            <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500">Belum ada riwayat rilis pada file JSON.</div>
                        @endforelse

                        @if($releasePaginators[$type]->hasPages())
                            <div class="border-t border-zinc-200 pt-4">
                                {{ $releasePaginators[$type]->onEachSide(1)->links() }}
                            </div>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-admin.shell>
