<?php

use App\Support\CurrentVillage;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public ?object $village = null;

    public function mount(): void
    {
        $this->village = DB::table('villages')->where('id', CurrentVillage::id())->first();
    }
};
?>

<div class="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
    <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex items-start gap-4">
            <div class="grid size-12 shrink-0 place-items-center rounded-lg bg-emerald-50 text-xl text-emerald-700">
                <i class="fa-solid fa-map-location-dot"></i>
            </div>
            <div>
                <h2 class="font-black">Integrasi SIDESI Ogan Ilir</h2>
                <p class="mt-1 text-sm leading-6 text-zinc-500">
                    CMS tidak menyimpan data titik Peta Sebaran. Kategori dan data peta ditarik langsung dari SIDESI
                    berdasarkan ID Desa SIDESI.
                </p>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <article class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                <div class="text-sm font-black text-blue-900">Fasilitas Umum</div>
                <p class="mt-1 text-sm leading-6 text-blue-800">Subkategori, daftar fasilitas, dan detail fasilitas
                    berasal dari endpoint Listing SIDESI.</p>
            </article>
            <article class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div class="text-sm font-black text-amber-900">Bantuan</div>
                <p class="mt-1 text-sm leading-6 text-amber-800">Subkategori bantuan dan data keluarga penerima berasal
                    dari endpoint Rumah Tangga Miskin SIDESI.</p>
            </article>
        </div>
    </section>

    <aside class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        @if ($village?->sidesi_village_id && auth()->user()?->role === 'developer')
            <div class="text-sm font-bold text-zinc-500">ID Desa SIDESI</div>
            <div class="mt-2 font-mono text-2xl font-black text-emerald-700">{{ $village->sidesi_village_id }}</div>
            <div
                class="mt-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-black text-emerald-700">
                <i class="fa-solid fa-circle-check"></i>
                Siap menarik data SIDESI
            </div>
        @elseif($village?->sidesi_village_id)
            <div
                class="mt-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-2xl font-black text-emerald-700">
                <i class="fa-solid fa-circle-check"></i>
                Konfigurasi lengkap, siap menarik data Peta Sebaran dari SIDESI
            </div>
        @else
            <div class="mt-2 text-lg font-black text-red-700">Belum dikonfigurasi</div>
            <p class="mt-2 text-sm leading-6 text-zinc-500">Harap hubungi Diskominfo Ogan Ilir untuk mengonfigurasi data
                Peta Sebaran.</p>
        @endif

        @if (auth()->user()?->role === 'developer')
            <a href="{{ route('admin.villages.index') }}"
                class="mt-5 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-black text-white">
                <i class="fa-solid fa-gear"></i>
                Atur ID Desa SIDESI
            </a>
        @endif
    </aside>
</div>
