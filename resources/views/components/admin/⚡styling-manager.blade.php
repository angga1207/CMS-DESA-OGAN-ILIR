<?php

use App\Support\CurrentVillage;
use App\Support\PublicSiteCache;
use Illuminate\Support\Facades\DB;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

new class extends Component {
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
        'cartoon' => [
            'theme_primary' => '#f97316',
            'theme_secondary' => '#1d4ed8',
            'theme_tertiary' => '#38bdf8',
            'theme_surface' => '#fff7ed',
            'theme_text' => '#1f2937',
            'font_style' => 'cartoon',
        ],
    ];

    public int $villageId = 1;

    public string $previewUrl = '';

    public array $settings = [
        'site_theme' => 'modern-style-1',
        'theme_primary' => '#8f1d2c',
        'theme_secondary' => '#102f28',
        'theme_tertiary' => '#d8e8a5',
        'theme_surface' => '#f7f7f2',
        'theme_text' => '#17221f',
        'font_style' => 'classic',
    ];

    public function mount(): void
    {
        $this->villageId = CurrentVillage::id();
        $stored = DB::table('site_settings')->where('village_id', $this->villageId)->pluck('value', 'key')->all();
        $this->settings = array_merge($this->settings, array_intersect_key($stored, $this->settings));
        $this->settings['site_theme'] = $this->settings['site_theme'] === 'tanjung-lubuk' ? 'modern-style-1' : $this->settings['site_theme'];
        $websiteUrl = (string) (DB::table('villages')->where('id', $this->villageId)->value('website_url') ?: '');
        $this->previewUrl = filter_var($websiteUrl, FILTER_VALIDATE_URL) ? rtrim($websiteUrl, '/') : 'http://localhost:3000';
    }

    public function updatedSettingsSiteTheme(string $theme): void
    {
        $preset = self::THEME_PRESETS[$theme] ?? null;

        if ($preset === null) {
            return;
        }

        $this->settings = array_merge($this->settings, $preset);
        $this->resetValidation(['settings.theme_primary', 'settings.theme_secondary', 'settings.theme_tertiary', 'settings.theme_surface', 'settings.theme_text', 'settings.font_style']);
    }

    public function save(): void
    {
        $this->validate([
            'settings.site_theme' => ['required', 'in:modern-style-1,modern-style-2,smooth-dynamic-style,creative-branding,cartoon'],
            'settings.theme_primary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.theme_secondary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.theme_tertiary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.theme_surface' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.theme_text' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.font_style' => ['required', 'in:classic,modern,friendly,cartoon,elegant,literary,system'],
        ]);

        foreach ($this->settings as $key => $value) {
            DB::table('site_settings')->updateOrInsert(
                ['village_id' => $this->villageId, 'key' => $key],
                [
                    'value' => $value,
                    'type' => 'text',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

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
                'cartoon' => 'Cartoon',
            ]" />
            <x-admin.select label="Font Style" model="settings.font_style" :options="[
                'classic' => 'Classic Editorial',
                'modern' => 'Modern Geometric',
                'friendly' => 'Friendly Rounded',
                'cartoon' => 'Cartoon Playful',
                'elegant' => 'Elegant Display',
                'literary' => 'Literary Serif',
                'system' => 'System Clean',
            ]" />
        </div>
        <p class="mt-3 text-xs leading-5 text-zinc-500">
            Mengganti template akan langsung menerapkan rekomendasi palet warna dan font. Setelah itu, warna dan font
            tetap dapat disesuaikan manual sebelum disimpan.
        </p>
    </section>

    <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <h2 class="font-black">Palet Warna</h2>
        <p class="mt-1 text-sm text-zinc-500">Warna diterapkan pada banner, tombol, judul, footer, dan elemen aksen
            frontend.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-admin.input label="Primary" model="settings.theme_primary" type="color" />
            <x-admin.input label="Secondary" model="settings.theme_secondary" type="color" />
            <x-admin.input label="Tertiary" model="settings.theme_tertiary" type="color" />
            <x-admin.input label="Latar" model="settings.theme_surface" type="color" />
            <x-admin.input label="Teks" model="settings.theme_text" type="color" />
        </div>

    </section>
    <div class="flex justify-end">
        <button
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-5 text-sm font-black text-white">
            <i class="fa-solid fa-floppy-disk"></i>Simpan Styling Website
        </button>
    </div>

    <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm" wire:ignore
        x-data="{
            loaded: false,
            previewUrl: @js($previewUrl),
            loadPreview() {
                if (!this.loaded) {
                    this.loaded = true;
                    return;
                }

                this.loaded = false;
                this.$nextTick(() => this.loaded = true);
            }
        }">
        <div class="flex flex-col gap-3 border-b border-zinc-200 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-black">Preview Frontend</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $previewUrl }}</p>
            </div>
            <div class="flex gap-2">
                <button type="button" @click="loadPreview()"
                    class="inline-flex min-h-10 items-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-black text-white">
                    <i class="fa-solid fa-play"></i>
                    <span x-text="loaded ? 'Muat Ulang' : 'Muat Preview'"></span>
                </button>
                <a href="{{ $previewUrl }}" target="_blank" rel="noopener"
                    class="grid size-10 place-items-center rounded-md border border-zinc-200 text-zinc-600"
                    title="Buka frontend di tab baru" aria-label="Buka frontend di tab baru">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                </a>
            </div>
        </div>
        <div class="relative aspect-[16/9] min-h-80 bg-zinc-100">
            <div x-show="!loaded" class="absolute inset-0 grid place-items-center p-6 text-center text-zinc-500">
                <i class="fa-solid fa-display text-4xl text-zinc-300"></i>
            </div>
            <iframe x-show="loaded" :src="loaded ? previewUrl : 'about:blank'"
                class="absolute inset-0 h-full w-full bg-white" title="Preview frontend website desa" loading="lazy"
                referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
    </section>
</form>
