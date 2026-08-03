@props([
    'label',
    'model',
    'icon' => 'fa-palette',
    'align' => 'left',
])

<div
    x-data="{
        open: false,
        value: $wire.entangle('{{ $model }}').live,
        colors: [
            '#0f766e', '#059669', '#16a34a', '#65a30d', '#ca8a04', '#ea580c',
            '#dc2626', '#db2777', '#9333ea', '#4f46e5', '#2563eb', '#0891b2',
            '#0f172a', '#3f3f46', '#78716c', '#ffffff',
        ],
        choose(color) {
            this.value = color.toUpperCase();
            this.open = false;
        },
        normalize() {
            let color = String(this.value || '').trim();

            if (!color.startsWith('#')) color = `#${color}`;
            if (/^#[0-9a-fA-F]{6}$/.test(color)) this.value = color.toUpperCase();
        },
    }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    class="relative"
>
    <label class="admin-field-label"><i class="fa-solid {{ $icon }} text-amber-600"></i>{{ $label }}</label>

    <button type="button" @click="open = !open" :aria-expanded="open"
        class="admin-control mt-1 flex items-center gap-3 text-left focus-visible:outline-none">
        <span class="size-7 shrink-0 rounded-md border border-black/10 shadow-inner"
            :style="`background-color: ${value}`" aria-hidden="true"></span>
        <span class="min-w-0 flex-1 font-mono text-sm font-bold uppercase text-zinc-700" x-text="value"></span>
        <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition" :class="open && 'rotate-180'" aria-hidden="true"></i>
    </button>

    <div x-cloak x-show="open" x-transition.origin.top
        @class([
            'absolute z-30 mt-2 w-full min-w-64 rounded-xl border border-zinc-200 bg-white p-3 shadow-2xl shadow-zinc-950/15',
            'left-0' => $align === 'left',
            'right-0' => $align === 'right',
        ])>
        <div class="grid grid-cols-8 gap-2" aria-label="Pilihan warna {{ $label }}">
            <template x-for="color in colors" :key="color">
                <button type="button" @click="choose(color)"
                    class="relative aspect-square rounded-md border border-black/10 shadow-sm transition hover:scale-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2"
                    :style="`background-color: ${color}`" :aria-label="`Pilih warna ${color}`">
                    <i x-show="String(value).toLowerCase() === color.toLowerCase()"
                        class="fa-solid fa-check text-xs"
                        :class="['#ffffff', '#ca8a04'].includes(color) ? 'text-zinc-900' : 'text-white'"
                        aria-hidden="true"></i>
                </button>
            </template>
        </div>

        <div class="mt-3 border-t border-zinc-100 pt-3">
            <label class="block text-[11px] font-black uppercase tracking-wide text-zinc-500">Warna kustom</label>
            <div class="mt-1.5 flex gap-2">
                <input type="color" x-model="value" aria-label="Pilih warna kustom {{ $label }}"
                    class="h-11 w-14 shrink-0 cursor-pointer rounded-lg border border-zinc-200 bg-white p-1">
                <div class="relative min-w-0 flex-1">
                    <i class="fa-solid fa-hashtag absolute left-3 top-1/2 -translate-y-1/2 text-xs text-zinc-400" aria-hidden="true"></i>
                    <input type="text" x-model="value" @change="normalize()" maxlength="7"
                        aria-label="Kode HEX {{ $label }}" placeholder="#0F766E"
                        class="admin-control pl-8 font-mono font-bold uppercase">
                </div>
            </div>
        </div>
    </div>

    @error($model)
        <div class="admin-error">{{ $message }}</div>
    @enderror
</div>
