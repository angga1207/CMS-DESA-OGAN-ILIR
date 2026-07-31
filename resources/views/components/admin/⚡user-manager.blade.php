<?php

use App\Models\User;
use App\Support\CurrentVillage;
use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

new class extends Component
{
    public array $users = [];

    public array $villages = [];

    public bool $showModal = false;

    public int $villageId = 1;

    public string $search = '';

    public string $roleFilter = '';

    public string $villageFilter = '';

    private array $roleLabels = [
        'developer' => 'Developer',
        'admin_desa' => 'Admin Desa',
        'editor' => 'Editor',
        'pengawas' => 'Pengawas',
    ];

    public array $form = [
        'id' => null,
        'name' => '',
        'username' => '',
        'email' => '',
        'role' => 'editor',
        'village_id' => '',
        'password' => '',
        'password_confirmation' => '',
    ];

    public function mount(): void
    {
        $this->villageId = CurrentVillage::id();
        $this->villages = DB::table('villages')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($row): array => (array) $row)
            ->all();
        $this->loadData();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'roleFilter', 'villageFilter'], true)) {
            $this->loadData();
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->roleFilter = '';
        $this->villageFilter = '';
        $this->loadData();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $user = User::query()
            ->when(! $this->isDeveloper(), fn ($query) => $query->where('role', '!=', 'developer'))
            ->when(! $this->isDeveloper(), fn ($query) => $query->where('village_id', $this->villageId))
            ->find($id);

        if (! $user) {
            return;
        }

        $this->form = [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'village_id' => $user->village_id ?: $this->villageId,
            'password' => '',
            'password_confirmation' => '',
        ];
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save(): void
    {
        if (! $this->isDeveloper() && $this->form['role'] === 'developer') {
            abort(403);
        }

        $id = $this->form['id'];

        $requiresVillage = $this->isDeveloper() && $this->form['role'] !== 'developer';

        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.username' => ['required', 'string', 'max:255', 'unique:users,username,'.($id ?: 'NULL').',id'],
            'form.email' => ['required', 'email', 'max:255', 'unique:users,email,'.($id ?: 'NULL').',id'],
            'form.role' => ['required', 'in:'.implode(',', array_keys($this->availableRoles()))],
            'form.village_id' => [$requiresVillage ? 'required' : 'nullable', 'integer', 'exists:villages,id'],
            'form.password' => PasswordPolicy::rules(! $id),
            'form.password_confirmation' => ['nullable', 'string'],
        ])['form'];

        $selectedVillageId = $data['role'] === 'developer' ? null : ($this->isDeveloper() ? (int) $data['village_id'] : $this->villageId);

        $payload = [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'role' => $data['role'],
            'village_id' => $selectedVillageId,
            'updated_at' => now(),
        ];

        if ($data['password']) {
            $payload['password'] = Hash::make($data['password']);
        }

        if ($id) {
            User::query()
                ->when(! $this->isDeveloper(), fn ($query) => $query->where('role', '!=', 'developer'))
                ->when(! $this->isDeveloper(), fn ($query) => $query->where('village_id', $this->villageId))
                ->where('id', $id)
                ->update($payload);
        } else {
            User::query()->create([...$payload, 'password' => $payload['password'], 'created_at' => now()]);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->loadData();
        LivewireAlert::title('Tersimpan')->text('Pengguna berhasil disimpan.')->success()->timer(1200)->show();
    }

    public function delete(int $id): void
    {
        if (auth()->id() === $id) {
            return;
        }

        User::query()
            ->when(! $this->isDeveloper(), fn ($query) => $query->where('role', '!=', 'developer'))
            ->when(! $this->isDeveloper(), fn ($query) => $query->where('village_id', $this->villageId))
            ->where('id', $id)
            ->delete();

        $this->loadData();
    }

    public function availableRoles(): array
    {
        return $this->isDeveloper() ? $this->roleLabels : collect($this->roleLabels)->except('developer')->all();
    }

    public function roleLabel(string $role): string
    {
        return $this->availableRoles()[$role] ?? $role;
    }

    private function loadData(): void
    {
        $this->users = DB::table('users')
            ->leftJoin('villages', 'users.village_id', '=', 'villages.id')
            ->when(! $this->isDeveloper(), fn ($query) => $query->where('role', '!=', 'developer'))
            ->when(! $this->isDeveloper(), fn ($query) => $query->where('users.village_id', $this->villageId))
            ->when(trim($this->search) !== '', function ($query): void {
                $search = '%'.strtolower(trim($this->search)).'%';
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(users.name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(users.username) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(users.email) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(villages.name, \'\')) LIKE ?', [$search]);
                });
            })
            ->when($this->roleFilter !== '', fn ($query) => $query->where('users.role', $this->roleFilter))
            ->when($this->isDeveloper() && $this->villageFilter !== '', fn ($query) => $query->where('users.village_id', $this->villageFilter))
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.username', 'users.email', 'users.role', 'users.village_id', 'users.created_at', 'villages.name as village_name'])
            ->map(fn (object $user): array => (array) $user)
            ->all();
    }

    private function resetForm(): void
    {
        $this->form = [
            'id' => null,
            'name' => '',
            'username' => '',
            'email' => '',
            'role' => $this->isDeveloper() ? 'admin_desa' : 'editor',
            'village_id' => $this->villageId,
            'password' => '',
            'password_confirmation' => '',
        ];
    }

    private function isDeveloper(): bool
    {
        return auth()->user()?->role === 'developer';
    }
};
?>

<div class="space-y-5">
    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-zinc-200 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-black">Pengguna</h2>
                <p class="text-sm text-zinc-500">Peran tersedia: {{ implode(', ', $this->availableRoles()) }}.</p>
            </div>
            <button type="button" wire:click="create"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-black text-white">
                <i class="fa-solid fa-user-plus"></i>
                Tambah
                Pengguna</button>
        </div>

        <div class="grid gap-3 border-b border-zinc-200 p-5 md:grid-cols-2 xl:grid-cols-5">
            <x-admin.input label="Cari Pengguna" model="search" placeholder="Nama, username, email, atau desa"
                class="xl:col-span-2" />
            <x-admin.select label="Peran" model="roleFilter" :options="['' => 'Semua peran', ...$this->availableRoles()]" />
            @if ($this->isDeveloper())
                <x-admin.select label="Desa" model="villageFilter" :options="collect($villages)->pluck('name', 'id')->prepend('Semua desa', '')->all()" />
            @endif
            <button type="button" wire:click="resetFilters"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-zinc-300 px-3 text-sm font-bold text-zinc-700 md:col-span-2 xl:col-span-1 xl:self-end">
                <i class="fa-solid fa-rotate-left"></i>
                Reset
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="px-5 py-3">Nama</th>
                        <th class="px-5 py-3">Username</th>
                        <th class="px-5 py-3">Email</th>
                        @if ($this->isDeveloper())
                            <th class="px-5 py-3">Village</th>
                        @endif
                        <th class="px-5 py-3">Peran</th>
                        <th class="w-72 px-5 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @forelse($users as $user)
                        <tr>
                            <td class="px-5 py-4 font-bold">{{ $user['name'] }}</td>
                            <td class="px-5 py-4">{{ $user['username'] }}</td>
                            <td class="px-5 py-4">{{ $user['email'] }}</td>
                            @if ($this->isDeveloper())
                                <td class="px-5 py-4">{{ $user['village_name'] ?: '-' }}</td>
                            @endif
                            <td class="px-5 py-4"><x-admin.pill :value="$user['role']" /></td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    @if ($this->isDeveloper() && $user['role'] !== 'developer' && (int) $user['id'] !== auth()->id())
                                        <form method="POST"
                                            action="{{ route('admin.users.impersonate', $user['id']) }}"
                                            data-swal-confirm="Anda akan masuk sebagai {{ $user['name'] }}."
                                            data-confirm-title="Masuk sebagai pengguna?"
                                            data-confirm-button="Ya, masuk">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex min-h-9 items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 text-xs font-black text-amber-900 transition hover:border-amber-400 hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                                                <i class="fa-solid fa-user-secret"></i>
                                                Masuk sebagai User
                                            </button>
                                        </form>
                                    @endif
                                    <button type="button" wire:click="edit({{ $user['id'] }})"
                                        class="inline-flex min-h-9 items-center gap-2 rounded-md bg-zinc-100 px-3 text-xs font-bold text-zinc-800 hover:bg-zinc-200">
                                        <i class="fa-solid fa-pen"></i>
                                        Edit
                                    </button>
                                    <button type="button" wire:click="delete({{ $user['id'] }})"
                                        wire:confirm="Hapus pengguna ini?"
                                        class="inline-flex min-h-9 items-center gap-2 rounded-md bg-red-50 px-3 text-xs font-bold text-red-700 hover:bg-red-100">
                                        <i class="fa-solid fa-trash"></i>
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $this->isDeveloper() ? 6 : 5 }}"
                                class="px-5 py-10 text-center text-zinc-500">Tidak ada pengguna yang cocok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($showModal)
        <div x-data @click.self="$wire.closeModal()" @keydown.escape.window="$wire.closeModal()"
            class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 p-4" role="dialog"
            aria-modal="true">
            <div class="w-full max-w-3xl rounded-lg bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                    <div>
                        <h3 class="text-lg font-black">{{ $form['id'] ? 'Edit' : 'Tambah' }} Pengguna</h3>
                        <p class="text-sm text-zinc-500">Atur akun dan peran pengelola CMS.</p>
                    </div>
                    <button type="button" wire:click="closeModal"
                        class="grid size-11 place-items-center rounded-md border border-zinc-300"
                        aria-label="Tutup modal">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit="save" class="grid gap-4 p-5 sm:grid-cols-2" x-data="{ showPassword: false, showPasswordConfirmation: false }">
                    <x-admin.input label="Nama" model="form.name" />
                    <x-admin.input label="Username" model="form.username" />
                    <x-admin.input label="Email" model="form.email" type="email" />
                    <x-admin.select label="Peran" model="form.role" :options="$this->availableRoles()" />
                    @if ($this->isDeveloper())
                        <x-admin.select label="Village" model="form.village_id" :options="collect($villages)->pluck('name', 'id')->prepend('Pilih village', '')->all()" />
                        <div class=""></div>
                    @endif
                    <div>
                        <label class="flex items-center gap-2 text-sm font-bold">
                            <i class="fa-solid fa-lock text-amber-600"></i>
                            Password
                        </label>
                        <div class="relative mt-1">
                            <input :type="showPassword ? 'text' : 'password'" wire:model.live="form.password"
                                autocomplete="new-password"
                                placeholder="{{ $form['id'] ? 'kosongkan jika tidak diganti' : 'Masukkan Password' }}"
                                class="min-h-[40px] w-full rounded-md border border-zinc-300 px-3 py-2 pr-12 text-sm placeholder:text-zinc-400 focus:border-emerald-600 focus:outline-none">
                            <button type="button" x-on:click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 grid w-11 place-items-center text-zinc-500 hover:text-zinc-800"
                                :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'">
                                <i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                        @error('form.password')
                            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <x-admin.password-guidance model="form.password" />
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm font-bold">
                            <i class="fa-solid fa-shield-halved text-amber-600"></i>
                            Konfirmasi Password
                        </label>
                        <div class="relative mt-1">
                            <input :type="showPasswordConfirmation ? 'text' : 'password'"
                                wire:model.live="form.password_confirmation" autocomplete="new-password-confirmation"
                                placeholder="{{ $form['id'] ? 'ulang jika password diganti' : 'Ulangi Password' }}"
                                class="min-h-[40px] w-full rounded-md border border-zinc-300 px-3 py-2 pr-12 text-sm placeholder:text-zinc-400 focus:border-emerald-600 focus:outline-none">
                            <button type="button" x-on:click="showPasswordConfirmation = !showPasswordConfirmation"
                                class="absolute inset-y-0 right-0 grid w-11 place-items-center text-zinc-500 hover:text-zinc-800"
                                :aria-label="showPasswordConfirmation ? 'Sembunyikan konfirmasi password' :
                                    'Tampilkan konfirmasi password'">
                                <i class="fa-solid" :class="showPasswordConfirmation ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                        @error('form.password_confirmation')
                            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="flex justify-end gap-2 border-t border-zinc-200 pt-5 sm:col-span-2">
                        <button type="button" wire:click="closeModal"
                            class="inline-flex min-h-11 items-center gap-2 rounded-md border border-zinc-300 px-4 text-sm font-bold"><i class="fa-solid fa-xmark"></i>Batal</button>
                        <button
                            class="inline-flex min-h-11 items-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-black text-white"><i class="fa-solid fa-floppy-disk"></i>Simpan
                            Pengguna</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
