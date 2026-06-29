<?php

use App\Support\CurrentVillage;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public ?object $village = null;
    public string $budgetUrl = '';
    public int $initialYear;

    public function mount(): void
    {
        $this->initialYear = (int) now()->year;
        $this->village = DB::table('villages')->where('id', CurrentVillage::id())->first();

        if ($this->village) {
            $this->budgetUrl = route('api.villages.budget.show', $this->village->slug);
        }
    }
};
?>

<div
    x-data="{
        year: @js($initialYear),
        loading: false,
        error: '',
        payload: null,
        groups: ['Pelaksanaan', 'Pembelanjaan', 'Pendapatan'],
        async load() {
            if (!'{{ $village?->sidesi_village_id }}') return;
            this.loading = true;
            this.error = '';
            try {
                const url = new URL(@js($budgetUrl), window.location.origin);
                url.searchParams.set('year', this.year);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const body = await response.json();
                if (!response.ok) throw new Error(body.message || 'Data anggaran gagal dimuat.');
                this.payload = body;
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        data() {
            return this.payload?.data?.data ?? {};
        },
        rows(group) {
            return Array.isArray(this.data()?.[group]) ? this.data()[group] : [];
        },
        money(value) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0
            }).format(Number(value || 0));
        },
        percentage(row) {
            const budget = Number(row.anggaran || 0);
            if (budget <= 0) return 0;
            return Math.min((Number(row.realisasi || 0) / budget) * 100, 100);
        }
    }"
    x-init="load()"
    class="space-y-5"
>
    <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-black">Transparansi APBDesa</h2>
                <p class="mt-1 text-sm text-zinc-500">Sumber SIDESI Ogan Ilir · ID Desa {{ $village?->sidesi_village_id ?: '-' }}</p>
            </div>
            <form @submit.prevent="load()" class="flex items-end gap-2">
                <div>
                    <label class="text-xs font-bold uppercase text-zinc-500">Tahun Anggaran</label>
                    <input type="number" min="2000" max="2100" x-model.number="year"
                        class="mt-1 w-28 rounded-md border border-zinc-300 px-3 py-2 text-sm font-bold focus:border-emerald-600 focus:outline-none">
                </div>
                <button type="submit" :disabled="loading || !'{{ $village?->sidesi_village_id }}'"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-50">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Tampilkan
                </button>
            </form>
        </div>

        @if(! $village?->sidesi_village_id)
            <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                ID Desa SIDESI belum dikonfigurasi. Developer dapat mengisinya melalui menu Manajemen Desa.
            </div>
        @endif

        <div x-show="loading" class="mt-5 rounded-lg border border-zinc-200 bg-zinc-50 p-8 text-center text-sm text-zinc-500">
            Memuat transparansi anggaran dari SIDESI...
        </div>

        <div x-show="error" x-cloak class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700" x-text="error"></div>

        <div x-show="!loading && !error && payload" x-cloak class="mt-5 space-y-5">
            <template x-for="group in groups" :key="group">
                <article class="overflow-hidden rounded-lg border border-zinc-200">
                    <div class="flex items-center justify-between bg-zinc-50 px-4 py-3">
                        <h3 class="font-black" x-text="group"></h3>
                        <span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-zinc-500" x-text="`${rows(group).length} data`"></span>
                    </div>
                    <div class="divide-y divide-zinc-200">
                        <template x-for="row in rows(group)" :key="row.id">
                            <div class="grid gap-3 p-4 lg:grid-cols-[1fr_180px_180px_170px] lg:items-center">
                                <div>
                                    <div class="font-bold text-zinc-900" x-text="row.nama"></div>
                                    <div class="mt-1 text-xs text-zinc-500" x-text="`Tahun ${row.tahun}`"></div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-zinc-400">Anggaran</div>
                                    <div class="mt-1 font-bold" x-text="money(row.anggaran)"></div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-zinc-400">Realisasi</div>
                                    <div class="mt-1 font-bold text-emerald-700" x-text="money(row.realisasi)"></div>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between text-xs font-bold">
                                        <span>Progres</span>
                                        <span x-text="`${percentage(row).toFixed(1)}%`"></span>
                                    </div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-200">
                                        <div class="h-full rounded-full bg-emerald-600" :style="`width: ${percentage(row)}%`"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div x-show="rows(group).length === 0" class="p-6 text-center text-sm text-zinc-500">
                            Tidak ada data untuk kelompok ini.
                        </div>
                    </div>
                </article>
            </template>
        </div>
    </section>
</div>
