<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

new class extends Component
{
    public array $profileForm = [
        'name' => '',
        'username' => '',
        'email' => '',
    ];

    public array $passwordForm = [
        'current_password' => '',
        'password' => '',
        'password_confirmation' => '',
    ];

    public function mount(): void
    {
        $user = auth()->user();

        $this->profileForm = [
            'name' => (string) $user?->name,
            'username' => (string) $user?->username,
            'email' => (string) $user?->email,
        ];
    }

    public function saveProfile(): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $data = $this->validate([
            'profileForm.name' => ['required', 'string', 'max:255'],
            'profileForm.username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'profileForm.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ])['profileForm'];

        $user->forceFill($data)->save();
        $this->profileForm = $data;

        LivewireAlert::title('Tersimpan')->text('Profil user berhasil diperbarui.')->success()->timer(1200)->show();
    }

    public function updatePassword(): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $data = $this->validate([
            'passwordForm.current_password' => ['required', 'current_password'],
            'passwordForm.password' => ['required', 'string', 'min:8', 'confirmed'],
        ])['passwordForm'];

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        $this->reset('passwordForm');
        $this->passwordForm = [
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
        ];

        LivewireAlert::title('Password Diganti')->text('Password akun berhasil diperbarui.')->success()->timer(1200)->show();
    }
};
?>

<div class="grid gap-5 xl:grid-cols-[1fr_420px]">
    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
        <div class="border-b border-zinc-200 p-5">
            <h2 class="font-black">Data User</h2>
            <p class="text-sm text-zinc-500">Nama, username, dan email digunakan untuk identitas pengelola CMS.</p>
        </div>

        <form wire:submit="saveProfile" class="grid gap-4 p-5 sm:grid-cols-2">
            <x-admin.input label="Nama Lengkap" model="profileForm.name" />
            <x-admin.input label="Username" model="profileForm.username" />
            <x-admin.input label="Email" model="profileForm.email" type="email" class="sm:col-span-2" />

            <div class="flex justify-end border-t border-zinc-200 pt-5 sm:col-span-2">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-black text-white">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Simpan Profil
                </button>
            </div>
        </form>
    </section>

    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
        <div class="border-b border-zinc-200 p-5">
            <h2 class="font-black">Ganti Password</h2>
            <p class="text-sm text-zinc-500">Gunakan password kuat dan berbeda dari akun lain.</p>
        </div>

        <form wire:submit="updatePassword" class="space-y-4 p-5">
            <x-admin.input label="Password Saat Ini" model="passwordForm.current_password" type="password" />
            <x-admin.input label="Password Baru" model="passwordForm.password" type="password" />
            <x-admin.input label="Konfirmasi Password Baru" model="passwordForm.password_confirmation" type="password" />

            <div class="flex justify-end border-t border-zinc-200 pt-5">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-zinc-950 px-4 text-sm font-black text-white">
                    <i class="fa-solid fa-key"></i>
                    Ganti Password
                </button>
            </div>
        </form>
    </section>
</div>
