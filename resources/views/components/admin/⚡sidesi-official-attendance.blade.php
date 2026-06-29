<?php

use App\Support\CurrentVillage;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public ?object $village = null;
    public string $attendanceUrl = '';
    public string $photoProxyUrl = '';

    public function mount(): void
    {
        $this->village = DB::table('villages')->where('id', CurrentVillage::id())->first();

        if ($this->village) {
            $this->attendanceUrl = route('api.villages.officials.today', $this->village->slug);
            $this->photoProxyUrl = route('api.villages.officials.photo', $this->village->slug);
        }
    }
};
?>

<div x-data="{
    loading: false,
    error: '',
    payload: null,
    async load() {
        if (!'{{ $village?->sidesi_village_id }}') return;
        this.loading = true;
        this.error = '';
        try {
            const response = await fetch(@js($attendanceUrl), { headers: { Accept: 'application/json' } });
            const body = await response.json();
            if (!response.ok) throw new Error(body.message || 'Data absensi gagal dimuat.');
            this.payload = body;
        } catch (error) {
            this.error = error.message;
        } finally {
            this.loading = false;
        }
    },
    records() {
        if (!this.payload) return [];
        const source = this.payload.data;
        if (Array.isArray(source)) return source;
        for (const key of ['data', 'result', 'absensi', 'pegawai', 'perangkat_desa']) {
            if (Array.isArray(source?.[key])) return source[key];
        }
        return source && typeof source === 'object' ? [source] : [];
    },
    value(row, keys, fallback = '-') {
        for (const key of keys) {
            if (row?.[key] !== undefined && row[key] !== null && row[key] !== '') return row[key];
        }
        return fallback;
    },
    photoUrl(row) {
        const photo = this.value(row, ['foto_pegawai', 'foto', 'photo_url'], '');
        return photo ? `${@js($photoProxyUrl)}?url=${encodeURIComponent(photo)}` : '';
    }
}" x-init="load()" class="space-y-5">
    <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-black">Absensi Hari Ini</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    Sumber SIDESI Ogan Ilir · {{ now()->translatedFormat('l, d F Y') }}
                </p>
            </div>
            <button type="button" @click="load()" :disabled="loading || !'{{ $village?->sidesi_village_id }}'"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-50">
                <i class="fa-solid fa-rotate" :class="{ 'animate-spin': loading }"></i>
                Muat Ulang
            </button>
        </div>

        @if (!$village?->sidesi_village_id)
            <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                ID Desa SIDESI belum dikonfigurasi. Developer dapat mengisinya melalui menu Manajemen Desa.
            </div>
        @elseif(auth()->user()?->role === 'developer')
            <div class="mt-4 font-mono text-xs font-bold text-emerald-700">ID Desa SIDESI:
                {{ $village->sidesi_village_id }}</div>
        @endif

        <div x-show="loading"
            class="mt-5 rounded-lg border border-zinc-200 bg-zinc-50 p-8 text-center text-sm text-zinc-500">
            Memuat absensi perangkat desa dari SIDESI...
        </div>

        <div x-show="error" x-cloak class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
            x-text="error"></div>

        <div x-show="!loading && !error && payload && records().length === 0" x-cloak
            class="mt-5 rounded-lg border border-zinc-200 bg-zinc-50 p-8 text-center text-sm text-zinc-500">
            Belum ada data absensi perangkat desa untuk hari ini.
        </div>

        <div x-show="!loading && !error && records().length > 0" x-cloak
            class="mt-5 overflow-x-auto rounded-lg border border-zinc-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">Perangkat Desa</th>
                        <th class="px-4 py-3">Jabatan</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <template x-for="(row, index) in records()" :key="value(row, ['id', 'id_pegawai', 'nik'], index)">
                        <tr>
                            <td class="px-4 py-4 font-bold">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="relative grid size-10 shrink-0 place-items-center overflow-hidden rounded-full bg-emerald-100 text-xs font-black text-emerald-700">
                                        <span
                                            x-text="value(row, ['nama_lengkap', 'nama'], '?').charAt(0).toUpperCase()"></span>
                                        <template x-if="photoUrl(row)">
                                            <img :src="photoUrl(row)" :alt="value(row, ['nama_lengkap', 'nama'])"
                                                x-on:error="$el.remove()"
                                                class="absolute size-10 rounded-full object-cover">
                                        </template>
                                    </div>
                                    <span x-text="value(row, ['nama_lengkap', 'nama'])"></span>
                                </div>
                            </td>
                            <td class="px-4 py-4" x-text="value(row, ['jabatan'])"></td>
                            <td class="px-4 py-4">
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-black text-emerald-700"
                                    :class="{
                                        'bg-emerald-50 text-emerald-700': value(row, [
                                            'status_kehadiran'
                                        ]) === 'Hadir',
                                        'bg-amber-50 text-amber-700': value(row, ['status_kehadiran']) === 'Izin',
                                        'bg-red-50 text-red-700': value(row, ['status_kehadiran']) === 'Tidak Hadir',
                                    }"
                                    x-show="value(row, ['status_kehadiran'])"
                                    x-text="value(row, ['status_kehadiran'])"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>
</div>
