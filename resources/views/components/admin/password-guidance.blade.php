@props(['model'])

<div x-data="{ password: $wire.entangle('{{ $model }}').live }"
    class="rounded-md border border-zinc-200 bg-zinc-50 p-3">
    <p class="text-xs font-black uppercase tracking-wide text-zinc-600">Rekomendasi password kuat</p>
    <div class="mt-2 grid gap-1.5 text-xs sm:grid-cols-2">
        <p :class="(password || '').length >= 12 ? 'text-emerald-700' : 'text-zinc-500'">
            <i class="fa-solid mr-1" :class="(password || '').length >= 12 ? 'fa-circle-check' : 'fa-circle'"></i>
            Minimal 8 karakter
        </p>
        <p :class="/[a-z]/.test(password || '') ? 'text-emerald-700' : 'text-zinc-500'">
            <i class="fa-solid mr-1" :class="/[a-z]/.test(password || '') ? 'fa-circle-check' : 'fa-circle'"></i>
            Huruf kecil
        </p>
        <p :class="/[A-Z]/.test(password || '') ? 'text-emerald-700' : 'text-zinc-500'">
            <i class="fa-solid mr-1" :class="/[A-Z]/.test(password || '') ? 'fa-circle-check' : 'fa-circle'"></i>
            Huruf besar
        </p>
        <p :class="/[0-9]/.test(password || '') ? 'text-emerald-700' : 'text-zinc-500'">
            <i class="fa-solid mr-1" :class="/[0-9]/.test(password || '') ? 'fa-circle-check' : 'fa-circle'"></i>
            Angka
        </p>
        <p :class="/[^A-Za-z0-9]/.test(password || '') ? 'text-emerald-700' : 'text-zinc-500'">
            <i class="fa-solid mr-1" :class="/[^A-Za-z0-9]/.test(password || '') ? 'fa-circle-check' : 'fa-circle'"></i>
            Simbol
        </p>
    </div>
</div>
