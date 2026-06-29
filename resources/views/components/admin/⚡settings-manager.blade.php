<?php

use App\Services\OptimizedImageStorage;
use App\Rules\ValidCoordinates;
use App\Support\CoordinatePair;
use App\Support\CurrentVillage;
use App\Support\PublicSiteCache;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public int $villageId = 1;

    public array $districtOptions = [];

    public $logoUpload = null;

    public $faviconUpload = null;

    public array $villageForm = [
        'name' => '',
        'slug' => '',
        'logo_url' => '',
        'favicon_url' => '',
        'district' => '',
        'regency' => '',
        'province' => '',
        'address' => '',
        'phone' => '',
        'email' => '',
        'latitude' => '',
        'longitude' => '',
        'welcome_message' => '',
        'description' => '',
        'vision' => '',
        'mission' => '',
    ];

    public string $coordinates = '';

    public array $settings = [
        'site_tagline' => '',
        'site_footer_text' => '',
    ];

    public function mount(): void
    {
        $this->districtOptions = array_combine(config('regions.districts'), config('regions.districts'));
        $this->villageId = CurrentVillage::id();
        $village = DB::table('villages')->where('id', $this->villageId)->first() ?? DB::table('villages')->first();

        if ($village) {
            $this->villageId = $village->id;
            $this->villageForm = array_merge($this->villageForm, (array) $village);
            $this->villageForm['province'] = config('regions.province');
            $this->villageForm['regency'] = config('regions.regency');
            $this->coordinates = CoordinatePair::format($this->villageForm['latitude'], $this->villageForm['longitude']);
        }

        $stored = DB::table('site_settings')->where('village_id', $this->villageId)->pluck('value', 'key')->all();
        $this->settings = array_merge($this->settings, array_intersect_key($stored, $this->settings));
    }

    public function save(): void
    {
        $this->villageForm['regency'] = config('regions.regency');
        $this->villageForm['province'] = config('regions.province');

        $data = $this->validate([
            'villageForm.name' => ['required', 'string', 'max:255'],
            'villageForm.district' => ['required', 'string', Rule::in(config('regions.districts'))],
            'villageForm.address' => ['nullable', 'string'],
            'villageForm.phone' => ['nullable', 'string', 'max:40'],
            'villageForm.email' => ['nullable', 'email', 'max:255'],
            'coordinates' => ['nullable', 'string', new ValidCoordinates()],
            'villageForm.welcome_message' => ['nullable', 'string'],
            'villageForm.description' => ['nullable', 'string'],
            'villageForm.vision' => ['nullable', 'string'],
            'villageForm.mission' => ['nullable', 'string'],
            'settings.site_tagline' => ['nullable', 'string', 'max:255'],
            'settings.site_footer_text' => ['nullable', 'string'],
            'logoUpload' => ['nullable', 'image', 'max:4096'],
            'faviconUpload' => ['nullable', 'image', 'max:1024'],
        ]);

        $coordinatePair = trim($this->coordinates) === ''
            ? ['latitude' => null, 'longitude' => null]
            : CoordinatePair::parse($this->coordinates);

        if ($this->logoUpload) {
            $this->villageForm['logo_url'] = app(OptimizedImageStorage::class)->replace(
                $this->logoUpload,
                "villages/{$this->villageId}/branding",
                $this->villageForm['logo_url'] ?: null,
                'branding',
            );
        }

        if ($this->faviconUpload) {
            $this->villageForm['favicon_url'] = app(OptimizedImageStorage::class)->replace(
                $this->faviconUpload,
                "villages/{$this->villageId}/branding",
                $this->villageForm['favicon_url'] ?: null,
                'favicon',
            );
        }

        DB::table('villages')->where('id', $this->villageId)->update([
            ...$data['villageForm'],
            'regency' => config('regions.regency'),
            'province' => config('regions.province'),
            'latitude' => $coordinatePair['latitude'],
            'longitude' => $coordinatePair['longitude'],
            ...array_intersect_key($this->villageForm, array_flip(['logo_url', 'favicon_url'])),
            'slug' => UniqueSlug::make('villages', $data['villageForm']['name'], $this->villageId),
            'updated_at' => now(),
        ]);

        foreach ($this->settings as $key => $value) {
            DB::table('site_settings')->updateOrInsert(
                ['village_id' => $this->villageId, 'key' => $key],
                ['value' => $value, 'type' => 'text', 'updated_at' => now(), 'created_at' => now()],
            );
        }

        PublicSiteCache::forget($this->villageId);

        LivewireAlert::title('Tersimpan')->text('Pengaturan desa berhasil diperbarui.')->success()->timer(1200)->show();
    }
};
?>

<form wire:submit="save" class="grid gap-5 xl:grid-cols-[1fr_380px]">
    <section class="space-y-5">
        <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="font-black">Informasi Desa</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-admin.input label="Nama Desa" model="villageForm.name" />
                <x-admin.select label="Kecamatan" model="villageForm.district" :options="$districtOptions" />
                <x-admin.input label="Kabupaten" model="villageForm.regency" readonly />
                <x-admin.input label="Provinsi" model="villageForm.province" readonly />
                <x-admin.input label="Telepon" model="villageForm.phone" />
                <x-admin.input label="Email" model="villageForm.email" type="email" />
                <x-admin.input label="Koordinat" model="coordinates" placeholder="-3.238421, 104.715834" class="sm:col-span-2" />
                <x-admin.textarea label="Alamat" model="villageForm.address" class="sm:col-span-2" />
                <x-admin.textarea label="Pesan Sambutan" model="villageForm.welcome_message" class="sm:col-span-2" />
                <x-admin.textarea label="Deskripsi" model="villageForm.description" class="sm:col-span-2" />
                <x-admin.textarea label="Visi" model="villageForm.vision" class="sm:col-span-2" />
                <x-admin.textarea label="Misi" model="villageForm.mission" class="sm:col-span-2" />
            </div>
        </div>

    </section>

    <aside class="space-y-5">
        <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="font-black">Identitas Website</h2>
            <p class="mt-1 text-sm text-zinc-500">Judul dan deskripsi website otomatis menggunakan Nama Desa dan Deskripsi pada Informasi Desa.</p>
            <div class="mt-4 space-y-4">
                <x-admin.input label="Tagline" model="settings.site_tagline" />
                <x-admin.textarea label="Teks Footer" model="settings.site_footer_text" />
                <div>
                    <label class="text-sm font-bold">Logo Desa</label>
                    <input type="file" wire:model="logoUpload" accept="image/*" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                    @error('logoUpload') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                    @if($logoUpload)
                        <img src="{{ $logoUpload->temporaryUrl() }}" alt="Preview logo" class="mt-2 size-24 rounded-md object-cover">
                    @elseif($villageForm['logo_url'])
                        <img src="{{ $villageForm['logo_url'] }}" alt="Logo saat ini" class="mt-2 size-24 rounded-md object-cover">
                    @endif
                </div>
                <div>
                    <label class="text-sm font-bold">Favicon Desa</label>
                    <input type="file" wire:model="faviconUpload" accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                    @error('faviconUpload') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                    @if($faviconUpload)
                        <img src="{{ $faviconUpload->temporaryUrl() }}" alt="Preview favicon" class="mt-2 size-14 rounded-md border border-zinc-200 object-contain p-1">
                    @elseif($villageForm['favicon_url'])
                        <img src="{{ $villageForm['favicon_url'] }}" alt="Favicon saat ini" class="mt-2 size-14 rounded-md border border-zinc-200 object-contain p-1">
                    @endif
                </div>
                <button class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-emerald-600 px-4 text-sm font-black text-white">Simpan Pengaturan</button>
            </div>
        </div>
    </aside>
</form>
