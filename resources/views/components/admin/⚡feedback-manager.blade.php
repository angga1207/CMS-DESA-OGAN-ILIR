<?php

use App\Support\CurrentVillage;
use App\Support\FeedbackSettings;
use Illuminate\Support\Facades\DB;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $villageId;

    public bool $enabled = true;

    public string $search = '';

    public string $statusFilter = '';

    public string $ratingFilter = '';

    public int $page = 1;

    public int $perPage = 15;

    public int $total = 0;

    public array $rows = [];

    public array $counts = [];

    public function mount(): void
    {
        $this->villageId = CurrentVillage::id();
        $this->enabled = FeedbackSettings::enabled($this->villageId);
        $this->loadData();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'ratingFilter'], true)) {
            $this->page = 1;
            $this->loadData();
        }
    }

    public function toggleEnabled(): void
    {
        $this->enabled = ! $this->enabled;
        FeedbackSettings::setEnabled($this->villageId, $this->enabled);

        LivewireAlert::title($this->enabled ? 'Fitur diaktifkan' : 'Fitur dinonaktifkan')
            ->text($this->enabled
                ? 'Warga dapat kembali mengirim dan melihat Kritik & Saran.'
                : 'Form dan daftar Kritik & Saran tidak tersedia di website publik.')
            ->success()
            ->timer(1600)
            ->show();
    }

    public function moderate(int $id, string $status): void
    {
        if (! in_array($status, ['pending', 'published', 'rejected'], true)) {
            return;
        }

        $updated = DB::table('feedback_entries')
            ->where('village_id', $this->villageId)
            ->where('id', $id)
            ->update([
                'moderation_status' => $status,
                'published_at' => $status === 'published' ? now() : null,
                'moderated_by' => auth()->id(),
                'moderated_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated) {
            $this->loadData();
            LivewireAlert::title('Status diperbarui')->success()->timer(1200)->show();
        }
    }

    public function delete(int $id): void
    {
        DB::table('feedback_entries')
            ->where('village_id', $this->villageId)
            ->where('id', $id)
            ->delete();

        $this->loadData();
        LivewireAlert::title('Kritik & Saran dihapus')->success()->timer(1200)->show();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->ratingFilter = '';
        $this->page = 1;
        $this->loadData();
    }

    public function previousPage(): void
    {
        $this->page = max($this->page - 1, 1);
        $this->loadData();
    }

    public function nextPage(): void
    {
        $this->page = min($this->page + 1, max((int) ceil($this->total / $this->perPage), 1));
        $this->loadData();
    }

    private function loadData(): void
    {
        $base = DB::table('feedback_entries')->where('village_id', $this->villageId);
        $this->counts = [
            'all' => (clone $base)->count(),
            'pending' => (clone $base)->where('moderation_status', 'pending')->count(),
            'published' => (clone $base)->where('moderation_status', 'published')->count(),
            'rejected' => (clone $base)->where('moderation_status', 'rejected')->count(),
        ];

        $query = (clone $base)
            ->when(trim($this->search) !== '', function ($query): void {
                $search = '%'.strtolower(trim($this->search)).'%';
                $query->where(fn ($query) => $query
                    ->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(whatsapp) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(message_original) LIKE ?', [$search]));
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('moderation_status', $this->statusFilter))
            ->when($this->ratingFilter !== '', fn ($query) => $query->where('rating', (int) $this->ratingFilter));

        $this->total = (clone $query)->count();
        $lastPage = max((int) ceil($this->total / $this->perPage), 1);
        $this->page = min(max($this->page, 1), $lastPage);
        $this->rows = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($this->page, $this->perPage)
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }
};
?>

<div class="space-y-5">
    <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-black text-zinc-950">Form Kritik & Saran Publik</h2>
                <p class="mt-1 text-sm text-zinc-500">Admin Desa dapat menghentikan sementara penerimaan masukan tanpa menghapus data.</p>
            </div>
            <button type="button" wire:click="toggleEnabled"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-4 text-sm font-black text-white {{ $enabled ? 'bg-emerald-600' : 'bg-zinc-600' }}">
                <i class="fa-solid {{ $enabled ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                {{ $enabled ? 'Fitur Aktif' : 'Fitur Nonaktif' }}
            </button>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            'all' => ['Semua', 'border-zinc-200 bg-zinc-50', 'text-zinc-700'],
            'pending' => ['Menunggu', 'border-amber-200 bg-amber-50', 'text-amber-700'],
            'published' => ['Dipublikasi', 'border-emerald-200 bg-emerald-50', 'text-emerald-700'],
            'rejected' => ['Ditolak', 'border-red-200 bg-red-50', 'text-red-700'],
        ] as $key => [$label, $cardClass, $labelClass])
            <article class="rounded-lg border p-4 {{ $cardClass }}">
                <p class="text-xs font-black uppercase tracking-wider {{ $labelClass }}">{{ $label }}</p>
                <p class="mt-2 text-2xl font-black text-zinc-950">{{ $counts[$key] ?? 0 }}</p>
            </article>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm">
        <div class="grid gap-3 border-b border-zinc-200 p-5 md:grid-cols-[minmax(0,1fr)_180px_150px_auto]">
            <x-admin.input label="Cari" model="search" placeholder="Nama, kontak, atau isi pesan" />
            <x-admin.select label="Status" model="statusFilter" :options="['' => 'Semua status', 'pending' => 'Menunggu', 'published' => 'Dipublikasi', 'rejected' => 'Ditolak']" />
            <x-admin.select label="Rating" model="ratingFilter" :options="['' => 'Semua rating', '5' => '5 Bintang', '4' => '4 Bintang', '3' => '3 Bintang', '2' => '2 Bintang', '1' => '1 Bintang']" />
            <button type="button" wire:click="resetFilters" class="min-h-11 self-end rounded-md border border-zinc-300 px-4 text-sm font-bold">
                <i class="fa-solid fa-rotate-left mr-2"></i>Reset
            </button>
        </div>

        <div class="divide-y divide-zinc-200">
            @forelse($rows as $row)
                @php
                    $whatsappDigits = preg_replace('/\D+/', '', $row['whatsapp']);
                    $whatsappLink = str_starts_with($whatsappDigits, '0') ? '62'.substr($whatsappDigits, 1) : $whatsappDigits;
                @endphp
                <article class="grid gap-4 p-5 xl:grid-cols-[220px_minmax(0,1fr)_220px]">
                    <div class="text-sm">
                        <h3 class="font-black text-zinc-950">{{ $row['name'] }}</h3>
                        <a href="https://wa.me/{{ $whatsappLink }}" target="_blank" rel="noopener noreferrer" class="mt-2 block font-semibold text-emerald-700">{{ $row['whatsapp'] }}</a>
                        <a href="mailto:{{ $row['email'] }}" class="mt-1 block break-all text-zinc-500">{{ $row['email'] }}</a>
                        <time class="mt-3 block text-xs text-zinc-400">{{ \Illuminate\Support\Carbon::parse($row['created_at'])->translatedFormat('d M Y H:i') }}</time>
                    </div>
                    <div>
                        <div class="text-amber-500" aria-label="{{ $row['rating'] }} dari 5 bintang">
                            @for($star = 1; $star <= 5; $star++)
                                <i class="fa-{{ $star <= $row['rating'] ? 'solid' : 'regular' }} fa-star"></i>
                            @endfor
                        </div>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-zinc-700">{{ $row['message_original'] }}</p>
                        @if($row['message_original'] !== $row['message_censored'])
                            <div class="mt-3 rounded-md bg-amber-50 p-3 text-xs leading-5 text-amber-900">
                                <strong>Versi tersensor:</strong> {{ $row['message_censored'] }}
                            </div>
                        @endif
                    </div>
                    <div>
                        @php($statusStyles = ['pending' => 'bg-amber-100 text-amber-800', 'published' => 'bg-emerald-100 text-emerald-800', 'rejected' => 'bg-red-100 text-red-800'])
                        @php($statusLabels = ['pending' => 'Menunggu', 'published' => 'Dipublikasi', 'rejected' => 'Ditolak'])
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-black {{ $statusStyles[$row['moderation_status']] ?? 'bg-zinc-100' }}">
                            {{ $statusLabels[$row['moderation_status']] ?? $row['moderation_status'] }}
                        </span>
                        <div class="mt-4 grid gap-2">
                            <button type="button" wire:click="moderate({{ $row['id'] }}, 'published')" class="min-h-10 rounded-md bg-emerald-600 px-3 text-xs font-black text-white">
                                <i class="fa-solid fa-check mr-2"></i>Publikasikan
                            </button>
                            <button type="button" wire:click="moderate({{ $row['id'] }}, 'rejected')" class="min-h-10 rounded-md bg-amber-100 px-3 text-xs font-black text-amber-900">
                                <i class="fa-solid fa-eye-slash mr-2"></i>Tolak
                            </button>
                            <button type="button" wire:click="delete({{ $row['id'] }})" wire:confirm="Hapus Kritik & Saran ini?" class="min-h-10 rounded-md bg-red-50 px-3 text-xs font-black text-red-700">
                                <i class="fa-solid fa-trash mr-2"></i>Hapus
                            </button>
                        </div>
                    </div>
                </article>
            @empty
                <div class="p-12 text-center text-sm text-zinc-500">Belum ada Kritik & Saran yang sesuai filter.</div>
            @endforelse
        </div>

        <div class="flex items-center justify-between gap-4 border-t border-zinc-200 px-5 py-4 text-sm">
            <span class="font-semibold text-zinc-500">Halaman {{ $page }} dari {{ max((int) ceil($total / $perPage), 1) }} · {{ $total }} data</span>
            <div class="flex gap-2">
                <button type="button" wire:click="previousPage" @disabled($page <= 1) class="rounded-md border border-zinc-200 px-3 py-2 font-bold disabled:opacity-40">Sebelumnya</button>
                <button type="button" wire:click="nextPage" @disabled($page >= max((int) ceil($total / $perPage), 1)) class="rounded-md border border-zinc-200 px-3 py-2 font-bold disabled:opacity-40">Berikutnya</button>
            </div>
        </div>
    </section>
</div>
