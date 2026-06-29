<?php

use App\Support\CurrentVillage;
use App\Support\PublicSiteCache;
use Illuminate\Support\Facades\DB;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

new class extends Component
{
    private const THEME_PRESETS = [
        'modern-style-1' => [
            'theme_primary' => '#8f1d2c',
            'theme_secondary' => '#102f28',
            'theme_tertiary' => '#d8e8a5',
            'theme_surface' => '#f7f7f2',
            'theme_text' => '#17221f',
            'font_style' => 'classic',
        ],
        'modern-style-2' => [
            'theme_primary' => '#c2410c',
            'theme_secondary' => '#1e293b',
            'theme_tertiary' => '#facc15',
            'theme_surface' => '#f8fafc',
            'theme_text' => '#111827',
            'font_style' => 'modern',
        ],
        'smooth-dynamic-style' => [
            'theme_primary' => '#2563eb',
            'theme_secondary' => '#083344',
            'theme_tertiary' => '#67e8f9',
            'theme_surface' => '#f0f9ff',
            'theme_text' => '#0f172a',
            'font_style' => 'system',
        ],
        'creative-branding' => [
            'theme_primary' => '#ff5a1f',
            'theme_secondary' => '#111111',
            'theme_tertiary' => '#c7ff2e',
            'theme_surface' => '#f4f1ea',
            'theme_text' => '#151515',
            'font_style' => 'elegant',
        ],
    ];

    public int $villageId = 1;

    public array $settings = [
        'site_theme' => 'modern-style-1',
        'theme_primary' => '#8f1d2c',
        'theme_secondary' => '#102f28',
        'theme_tertiary' => '#d8e8a5',
        'theme_surface' => '#f7f7f2',
        'theme_text' => '#17221f',
        'font_style' => 'classic',
    ];

    public array $shortcutLinks = [
        ['label' => '', 'url' => ''],
        ['label' => '', 'url' => ''],
        ['label' => '', 'url' => ''],
        ['label' => '', 'url' => ''],
    ];

    public function mount(): void
    {
        $this->villageId = CurrentVillage::id();
        $stored = DB::table('site_settings')->where('village_id', $this->villageId)->pluck('value', 'key')->all();
        $this->settings = array_merge($this->settings, array_intersect_key($stored, $this->settings));
        $this->settings['site_theme'] = $this->settings['site_theme'] === 'tanjung-lubuk' ? 'modern-style-1' : $this->settings['site_theme'];

        $shortcuts = json_decode((string) ($stored['home_shortcuts'] ?? '[]'), true);
        if (is_array($shortcuts)) {
            foreach (array_slice($shortcuts, 0, 4) as $index => $shortcut) {
                if (is_array($shortcut)) {
                    $this->shortcutLinks[$index] = [
                        'label' => (string) ($shortcut['label'] ?? ''),
                        'url' => (string) ($shortcut['url'] ?? ''),
                    ];
                }
            }
        }
    }

    public function updatedSettingsSiteTheme(string $theme): void
    {
        $preset = self::THEME_PRESETS[$theme] ?? null;

        if ($preset === null) {
            return;
        }

        $this->settings = array_merge($this->settings, $preset);
        $this->resetValidation([
            'settings.theme_primary',
            'settings.theme_secondary',
            'settings.theme_tertiary',
            'settings.theme_surface',
            'settings.theme_text',
            'settings.font_style',
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'settings.site_theme' => ['required', 'in:modern-style-1,modern-style-2,smooth-dynamic-style,creative-branding'],
            'settings.theme_primary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.theme_secondary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.theme_tertiary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.theme_surface' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.theme_text' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.font_style' => ['required', 'in:classic,modern,friendly,elegant,literary,system'],
            'shortcutLinks' => ['array', 'max:4'],
            'shortcutLinks.*.label' => ['nullable', 'string', 'max:80'],
            'shortcutLinks.*.url' => ['nullable', 'string', 'max:2048', 'regex:/^(\/|#)/'],
        ]);

        foreach ($this->settings as $key => $value) {
            DB::table('site_settings')->updateOrInsert(
                ['village_id' => $this->villageId, 'key' => $key],
                ['value' => $value, 'type' => 'text', 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $shortcuts = collect($this->shortcutLinks)
            ->filter(fn (array $shortcut): bool => trim($shortcut['label']) !== '' && trim($shortcut['url']) !== '')
            ->take(4)
            ->values()
            ->all();
        DB::table('site_settings')->updateOrInsert(
            ['village_id' => $this->villageId, 'key' => 'home_shortcuts'],
            ['value' => json_encode($shortcuts), 'type' => 'json', 'updated_at' => now(), 'created_at' => now()],
        );

        PublicSiteCache::forget($this->villageId);
        LivewireAlert::title('Tersimpan')->text('Styling website publik berhasil diperbarui.')->success()->timer(1200)->show();
    }
};
?>

<form wire:submit="save" class="space-y-5">
    <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-black">Template & Tipografi</h2>
                <p class="mt-1 text-sm text-zinc-500">Pilih template dan gaya huruf website publik desa.</p>
            </div>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-admin.select label="Template Website Publik" model="settings.site_theme" :options="[
                'modern-style-1' => 'Modern Style 1',
                'modern-style-2' => 'Modern Style 2',
                'smooth-dynamic-style' => 'Smooth Dynamic Style',
                'creative-branding' => 'Creative Branding',
            ]" />
            <x-admin.select label="Font Style" model="settings.font_style" :options="[
                'classic' => 'Classic Editorial',
                'modern' => 'Modern Geometric',
                'friendly' => 'Friendly Rounded',
                'elegant' => 'Elegant Display',
                'literary' => 'Literary Serif',
                'system' => 'System Clean',
            ]" />
        </div>
        <p class="mt-3 text-xs leading-5 text-zinc-500">
            Mengganti template akan langsung menerapkan rekomendasi palet warna dan font. Setelah itu, warna dan font tetap dapat disesuaikan manual sebelum disimpan.
        </p>
    </section>

    <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <h2 class="font-black">Palet Warna</h2>
        <p class="mt-1 text-sm text-zinc-500">Warna diterapkan pada banner, tombol, judul, footer, dan elemen aksen frontend.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-admin.input label="Primary" model="settings.theme_primary" type="color" />
            <x-admin.input label="Secondary" model="settings.theme_secondary" type="color" />
            <x-admin.input label="Tertiary" model="settings.theme_tertiary" type="color" />
            <x-admin.input label="Latar" model="settings.theme_surface" type="color" />
            <x-admin.input label="Teks" model="settings.theme_text" type="color" />
        </div>
    </section>

    <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <h2 class="font-black">Label & Link di Bawah Banner</h2>
        <p class="mt-1 text-sm text-zinc-500">Maksimal empat item per desa. Gunakan link internal seperti <code>/tentang</code> atau <code>#profil</code>.</p>
        <div class="mt-4 grid gap-3">
            @foreach($shortcutLinks as $index => $shortcut)
                <div class="grid gap-3 rounded-lg border border-zinc-200 p-4 sm:grid-cols-[1fr_1.25fr]" wire:key="shortcut-{{ $index }}">
                    <x-admin.input :label="'Label '.($index + 1)" :model="'shortcutLinks.'.$index.'.label'" />
                    <x-admin.input :label="'Link '.($index + 1)" :model="'shortcutLinks.'.$index.'.url'" placeholder="/statistik atau #profil" />
                </div>
            @endforeach
        </div>
    </section>

    <div class="flex justify-end">
        <button class="inline-flex min-h-11 items-center justify-center rounded-md bg-emerald-600 px-5 text-sm font-black text-white">Simpan Styling Website</button>
    </div>
</form>
