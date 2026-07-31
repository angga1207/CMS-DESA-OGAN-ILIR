<?php

use App\Services\LegacyWebsiteImporter;
use App\Support\CurrentVillage;
use Illuminate\Support\Facades\DB;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

new class extends Component
{
    public int $villageId;

    public string $sourceUrl = '';

    public array $selectedTypes = [];

    public array $summary = [];

    public array $runs = [];

    public bool $confirmed = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'developer', 403);

        $this->villageId = CurrentVillage::id();
        $this->sourceUrl = (string) config('legacy-import.default_source_url');
        $this->selectedTypes = array_keys(config('legacy-import.types', []));
        $this->loadRuns();
    }

    public function import(LegacyWebsiteImporter $importer): void
    {
        abort_unless(auth()->user()?->role === 'developer', 403);

        $data = $this->validate([
            'sourceUrl' => ['required', 'url:https', 'max:2048'],
            'selectedTypes' => ['required', 'array', 'min:1'],
            'selectedTypes.*' => ['required', 'string', 'in:'.implode(',', array_keys(config('legacy-import.types', [])))],
            'confirmed' => ['accepted'],
        ], [], [
            'sourceUrl' => 'Endpoint API',
            'selectedTypes' => 'Jenis data',
            'confirmed' => 'Konfirmasi migrasi',
        ]);

        try {
            $this->summary = $importer->import(
                $this->villageId,
                $data['sourceUrl'],
                $data['selectedTypes'],
                auth()->id(),
            );
            $this->confirmed = false;
            $this->loadRuns();
            LivewireAlert::title('Migrasi selesai')
                ->text('Data API lama sudah diproses. Periksa rekap untuk detail hasilnya.')
                ->success()->show();
        } catch (Throwable $exception) {
            report($exception);
            $this->loadRuns();
            $this->addError('sourceUrl', 'Migrasi gagal: '.$exception->getMessage());
            LivewireAlert::title('Migrasi gagal')->text($exception->getMessage())->error()->show();
        }
    }

    private function loadRuns(): void
    {
        $this->runs = DB::table('legacy_import_runs')
            ->where('village_id', $this->villageId)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (object $run): array {
                $row = (array) $run;
                $row['selected_types'] = json_decode((string) $run->selected_types, true) ?: [];
                $row['summary'] = json_decode((string) $run->summary, true) ?: [];

                return $row;
            })
            ->all();
    }
};
?>

<div class="space-y-5">
    <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div>
                <label for="source-url" class="text-sm font-black text-zinc-800">Endpoint API website lama</label>
                <input id="source-url" type="url" wire:model="sourceUrl" placeholder="https://domain-desa.go.id/api/v1"
                    class="mt-2 min-h-11 w-full rounded-md border border-zinc-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                @error('sourceUrl') <p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p> @enderror
                <p class="mt-2 text-xs leading-5 text-zinc-500">Importer hanya menerima HTTPS. Proses dapat dijalankan ulang; data dari ID sumber yang sama akan diperbarui, bukan diduplikasi.</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                <div class="font-black"><i class="fa-solid fa-shield-halved mr-2"></i>Desa tujuan</div>
                <p class="mt-2 leading-6">Semua hasil hanya masuk ke desa yang sedang aktif. Aset disalin ke storage CMS agar tidak bergantung pada website lama.</p>
            </div>
        </div>

        <fieldset class="mt-6">
            <legend class="text-sm font-black text-zinc-800">Data yang dimigrasikan</legend>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach(config('legacy-import.types', []) as $key => $label)
                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-md border border-zinc-200 px-3 hover:border-emerald-400 hover:bg-emerald-50">
                        <input type="checkbox" value="{{ $key }}" wire:model="selectedTypes" class="size-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm font-bold">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @error('selectedTypes') <p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p> @enderror
        </fieldset>

        <div class="mt-6 flex flex-col gap-4 border-t border-zinc-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
            <label class="flex items-start gap-3 text-sm text-zinc-700">
                <input type="checkbox" wire:model="confirmed" class="mt-1 size-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500">
                <span>Saya sudah memastikan desa aktif adalah tujuan migrasi yang benar.</span>
            </label>
            <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-700 px-5 text-sm font-black text-white hover:bg-emerald-800 disabled:cursor-wait disabled:opacity-60">
                <i wire:loading.remove wire:target="import" class="fa-solid fa-cloud-arrow-down"></i>
                <i wire:loading wire:target="import" class="fa-solid fa-spinner animate-spin"></i>
                <span wire:loading.remove wire:target="import">Mulai Migrasi</span>
                <span wire:loading wire:target="import">Mengambil data dan aset...</span>
            </button>
        </div>
        @error('confirmed') <p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p> @enderror
    </section>

    @if($summary)
        <section class="rounded-lg border border-emerald-200 bg-emerald-50 p-5">
            <h2 class="font-black text-emerald-950">Rekap migrasi terakhir</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($summary as $type => $counts)
                    <article class="rounded-md bg-white p-4 shadow-sm">
                        <div class="font-black">{{ config("legacy-import.types.{$type}", $type) }}</div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs font-bold">
                            <span class="text-emerald-700">Baru {{ $counts['created'] }}</span>
                            <span class="text-sky-700">Diperbarui {{ $counts['updated'] }}</span>
                            <span class="text-zinc-500">Tetap {{ $counts['unchanged'] }}</span>
                            <span class="text-red-700">Gagal {{ $counts['failed'] }}</span>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm">
        <header class="border-b border-zinc-200 p-5">
            <h2 class="font-black">Riwayat migrasi desa aktif</h2>
        </header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                    <tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Sumber</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Hasil</th></tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($runs as $run)
                        <tr>
                            <td class="px-5 py-4 font-semibold">{{ \Illuminate\Support\Carbon::parse($run['started_at'])->format('d M Y H:i') }}</td>
                            <td class="max-w-xs truncate px-5 py-4 text-zinc-600" title="{{ $run['source_url'] }}">{{ $run['source_url'] }}</td>
                            <td class="px-5 py-4">{{ count($run['selected_types']) }} jenis</td>
                            <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-black {{ $run['status'] === 'completed' ? 'bg-emerald-100 text-emerald-700' : ($run['status'] === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">{{ ucfirst($run['status']) }}</span></td>
                            <td class="px-5 py-4 text-zinc-600">{{ collect($run['summary'])->sum('created') }} baru, {{ collect($run['summary'])->sum('updated') }} diperbarui, {{ collect($run['summary'])->sum('failed') }} gagal</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-zinc-500">Belum ada migrasi untuk desa aktif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
