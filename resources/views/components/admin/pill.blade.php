@props(['value', 'type' => 'default', 'icon' => null])

@php
    $raw = (string) ($value ?? '-');
    $normalized = strtolower($raw);
    $labels = [
        'published' => 'Terbit',
        'draft' => 'Draf',
        'planned' => 'Direncanakan',
        'in_progress' => 'Berjalan',
        'completed' => 'Selesai',
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
        'developer' => 'Developer',
        'admin_desa' => 'Admin Desa',
        'editor' => 'Editor',
        'pengawas' => 'Pengawas',
        'article' => 'Artikel',
        'announcement' => 'Pengumuman',
        'url' => 'Tautan',
        'page' => 'Halaman',
        'income' => 'Pendapatan',
        'expense' => 'Belanja',
        'financing' => 'Pembiayaan',
    ];
    $label = $labels[$normalized] ?? $raw;

    if (!$icon) {
        $icon = match (true) {
            in_array($normalized, ['published', 'active', 'completed', 'hadir'], true) => 'fa-solid fa-check-circle',
            in_array($normalized, ['draft', 'planned', 'inactive'], true) => 'fa-solid fa-times-circle',
            in_array($normalized, ['in_progress', 'dinas luar'], true) => 'fa-solid fa-clock',
            in_array($normalized, ['announcement', 'article'], true) => 'fa-solid fa-newspaper',
            $normalized === 'developer' => 'fa-solid fa-shield-alt',
            in_array($normalized, ['admin_desa', 'editor', 'pengawas'], true) => 'fa-solid fa-user-group',
            $type === 'category' => 'fa-solid fa-tag',
            default => null,
        };
    }

    $tone = match (true) {
        in_array($normalized, ['published', 'active', 'completed', 'hadir'], true)
            => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        in_array($normalized, ['draft', 'planned', 'inactive'], true) => 'bg-zinc-100 text-zinc-700 ring-zinc-200',
        in_array($normalized, ['in_progress', 'dinas luar', 'announcement'], true)
            => 'bg-amber-50 text-amber-800 ring-amber-200',
        $normalized === 'developer' => 'bg-violet-50 text-violet-700 ring-violet-200',
        in_array($normalized, ['admin_desa', 'editor', 'pengawas'], true) => 'bg-sky-50 text-sky-700 ring-sky-200',
        $type === 'category' => 'bg-blue-50 text-blue-700 ring-blue-200',
        default => 'bg-zinc-100 text-zinc-700 ring-zinc-200',
    };
@endphp

<span
    {{ $attributes->class("whitespace-nowrap inline-flex max-w-full items-center rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset {$tone}") }}>
    @if ($icon)
        <i class="{{ $icon }} mr-1"></i>
    @endif
    {{ $label }}
</span>
