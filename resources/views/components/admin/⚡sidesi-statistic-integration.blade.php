<?php

use App\Support\CurrentVillage;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public ?object $village = null;
    public string $statisticUrl = '';

    public function mount(): void
    {
        $this->village = DB::table('villages')->where('id', CurrentVillage::id())->first();

        if ($this->village) {
            $this->statisticUrl = route('api.villages.statistics.show', $this->village->slug);
        }
    }
};
?>

<div
    x-data="{
        loading: false,
        error: '',
        payload: null,
        sections: [
            { key: 'occupations', label: 'Berdasarkan Jenis Pekerjaan', icon: 'fa-briefcase' },
            { key: 'education', label: 'Berdasarkan Pendidikan', icon: 'fa-graduation-cap' },
            { key: 'ages', label: 'Berdasarkan Usia', icon: 'fa-people-group' }
        ],
        async load() {
            if (!'{{ $village?->sidesi_village_id }}') return;
            this.loading = true;
            this.error = '';
            try {
                const response = await fetch(@js($statisticUrl), { headers: { Accept: 'application/json' } });
                const body = await response.json();
                if (!response.ok) throw new Error(body.message || 'Data statistik gagal dimuat.');
                this.payload = body;
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        population() {
            return this.payload?.data?.population ?? {};
        },
        rows(key) {
            return Array.isArray(this.payload?.data?.[key]) ? this.payload.data[key] : [];
        },
        value(row, keys, fallback = '-') {
            for (const key of keys) {
                if (row?.[key] !== undefined && row[key] !== null && row[key] !== '') return row[key];
            }
            return fallback;
        },
        number(value) {
            return new Intl.NumberFormat('id-ID').format(Number(value || 0));
        },
        percent(row) {
            return Number(row?.percentage || 0);
        }
    }"
    x-init="load()"
    class="space-y-5"
>
    <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-black">Statistik Penduduk Desa</h2>
                <p class="mt-1 text-sm text-zinc-500">Sumber SIDESI Ogan Ilir · ID Desa {{ $village?->sidesi_village_id ?: '-' }}</p>
            </div>
            <button type="button" @click="load()" :disabled="loading || !'{{ $village?->sidesi_village_id }}'"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-50">
                <i class="fa-solid fa-rotate" :class="{ 'animate-spin': loading }"></i>
                Muat Ulang
            </button>
        </div>

        @if(! $village?->sidesi_village_id)
            <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                ID Desa SIDESI belum dikonfigurasi. Developer dapat mengisinya melalui menu Manajemen Desa.
            </div>
        @endif

        <div x-show="loading" class="mt-5 rounded-lg border border-zinc-200 bg-zinc-50 p-8 text-center text-sm text-zinc-500">
            Memuat data statistik dari SIDESI...
        </div>
        <div x-show="error" x-cloak class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700" x-text="error"></div>

        <div x-show="!loading && !error && payload" x-cloak class="mt-5 space-y-5">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <template x-for="metric in [
                    { label: 'Jumlah Penduduk', key: 'total_population' },
                    { label: 'Laki-laki', key: 'male_population' },
                    { label: 'Perempuan', key: 'female_population' },
                    { label: 'Kepala Keluarga', key: 'total_families' },
                    { label: 'Tidak Diketahui', key: 'unknown_population' }
                ]" :key="metric.label">
                    <article class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                        <div class="text-xs font-bold uppercase text-zinc-500" x-text="metric.label"></div>
                        <div class="mt-2 text-2xl font-black text-zinc-950" x-text="number(population()[metric.key] || 0)"></div>
                    </article>
                </template>
            </div>

            <template x-for="section in sections" :key="section.key">
                <article class="overflow-hidden rounded-lg border border-zinc-200">
                    <div class="flex items-center gap-3 bg-zinc-50 px-4 py-3">
                        <i class="fa-solid text-emerald-700" :class="section.icon"></i>
                        <h3 class="font-black" x-text="section.label"></h3>
                    </div>
                    <div class="divide-y divide-zinc-200">
                        <template x-for="(row, index) in rows(section.key)" :key="value(row, ['id', 'kode'], index)">
                            <div class="grid gap-3 p-4 sm:grid-cols-[1fr_120px_180px] sm:items-center">
                                <div>
                                    <div class="font-bold" x-text="row.label"></div>
                                    <div class="mt-1 text-xs text-zinc-500">
                                        <span x-text="`L ${number(row.male)} (${row.male_percentage.toFixed(2)}%)`"></span>
                                        <span class="mx-1">·</span>
                                        <span x-text="`P ${number(row.female)} (${row.female_percentage.toFixed(2)}%)`"></span>
                                    </div>
                                </div>
                                <div class="text-sm font-bold text-zinc-600" x-text="number(row.total)"></div>
                                <div>
                                    <div class="flex justify-between text-xs font-bold">
                                        <span>Persentase</span>
                                        <span x-text="`${percent(row).toFixed(2)}%`"></span>
                                    </div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-200">
                                        <div class="h-full rounded-full bg-emerald-600" :style="`width: ${Math.min(percent(row), 100)}%`"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div x-show="rows(section.key).length === 0" class="p-6 text-center text-sm text-zinc-500">Belum ada data.</div>
                    </div>
                </article>
            </template>
        </div>
    </section>
</div>
