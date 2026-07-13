<?php

use App\Support\CurrentVillage;
use App\Support\PublicSiteCache;
use Illuminate\Support\Facades\DB;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

new class extends Component
{
    public int $villageId = 1;

    public bool $enabled = true;

    public array $shortcutLinks = [
        ['label' => '', 'url' => ''],
        ['label' => '', 'url' => ''],
        ['label' => '', 'url' => ''],
        ['label' => '', 'url' => ''],
    ];

    public function mount(): void
    {
        $this->villageId = CurrentVillage::id();
        $stored = DB::table('site_settings')
            ->where('village_id', $this->villageId)
            ->whereIn('key', ['home_shortcuts_enabled', 'home_shortcuts'])
            ->pluck('value', 'key')
            ->all();

        $this->enabled = ! in_array(
            strtolower((string) ($stored['home_shortcuts_enabled'] ?? '1')),
            ['0', 'false', 'off', 'no'],
            true,
        );

        $shortcuts = json_decode((string) ($stored['home_shortcuts'] ?? '[]'), true);

        if (! is_array($shortcuts)) {
            return;
        }

        foreach (array_slice($shortcuts, 0, 4) as $index => $shortcut) {
            if (! is_array($shortcut)) {
                continue;
            }

            $this->shortcutLinks[$index] = [
                'label' => (string) ($shortcut['label'] ?? ''),
                'url' => (string) ($shortcut['url'] ?? ''),
            ];
        }
    }

    public function save(): void
    {
        $this->validate([
            'enabled' => ['boolean'],
            'shortcutLinks' => ['array', 'max:4'],
            'shortcutLinks.*.label' => ['nullable', 'string', 'max:80'],
            'shortcutLinks.*.url' => ['nullable', 'string', 'max:2048', 'regex:/^(\/|#)/'],
        ]);

        $shortcuts = collect($this->shortcutLinks)
            ->filter(fn (array $shortcut): bool => trim($shortcut['label']) !== '' && trim($shortcut['url']) !== '')
            ->take(4)
            ->values()
            ->all();

        DB::transaction(function () use ($shortcuts): void {
            DB::table('site_settings')->updateOrInsert(
                ['village_id' => $this->villageId, 'key' => 'home_shortcuts_enabled'],
                [
                    'value' => $this->enabled ? '1' : '0',
                    'type' => 'boolean',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            DB::table('site_settings')->updateOrInsert(
                ['village_id' => $this->villageId, 'key' => 'home_shortcuts'],
                [
                    'value' => json_encode($shortcuts),
                    'type' => 'json',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        });

        PublicSiteCache::forget($this->villageId);
        LivewireAlert::title('Tersimpan')->text('Shortcut beranda berhasil diperbarui.')->success()->timer(1200)->show();
    }
};
?>

<form wire:submit="save" class="space-y-5">
    <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="font-black">Label & Link di Bawah Banner</h2>
                <p class="mt-1 text-sm text-zinc-500">Maksimal empat shortcut dengan link internal seperti <code>/tentang</code> atau <code>#profil</code>.</p>
            </div>
            <label class="inline-flex items-start gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-bold text-zinc-700">
                <input type="checkbox" wire:model="enabled" class="mt-1 rounded border-zinc-300 text-emerald-600">
                <span>
                    Tampilkan Shortcut
                    <small class="mt-1 block font-medium leading-5 text-zinc-500">Berlaku untuk seluruh theme frontend.</small>
                </span>
            </label>
        </div>

        <div class="mt-5 grid gap-3">
            @foreach($shortcutLinks as $index => $shortcut)
                <div class="grid gap-3 rounded-lg border border-zinc-200 p-4 sm:grid-cols-[1fr_1.25fr]" wire:key="shortcut-{{ $index }}">
                    <x-admin.input :label="'Label '.($index + 1)" :model="'shortcutLinks.'.$index.'.label'" />
                    <x-admin.input :label="'Link '.($index + 1)" :model="'shortcutLinks.'.$index.'.url'" placeholder="/statistik atau #profil" />
                </div>
            @endforeach
        </div>
    </section>

    <div class="flex justify-end">
        <button class="admin-btn-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-5 text-sm font-black text-white">
            <i class="fa-solid fa-floppy-disk"></i>
            Simpan Shortcut
        </button>
    </div>
</form>
