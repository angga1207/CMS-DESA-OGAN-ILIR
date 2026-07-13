<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

new class extends Component {
    public string $username = '';
    public string $password = '';
    public string $captcha = '';
    public int $captchaVersion = 1;
    public bool $remember = false;

    public function login(): void
    {
        try {
            $credentials = $this->validate(
                [
                    'username' => ['required', 'string'],
                    'password' => ['required', 'string'],
                    'captcha' => ['required', 'captcha'],
                ],
                [
                    'captcha.captcha' => 'Kode captcha tidak sesuai.',
                ],
            );
        } catch (ValidationException $exception) {
            if ($exception->validator->errors()->has('captcha')) {
                $this->resetCaptcha();
            }

            throw $exception;
        }

        if (
            !Auth::attempt(
                [
                    'username' => $credentials['username'],
                    'password' => $credentials['password'],
                ],
                $this->remember,
            )
        ) {
            LivewireAlert::title('Login gagal')->text('Username atau password tidak sesuai.')->error()->show();

            $this->reset('password');
            $this->resetCaptcha();
            return;
        }

        request()->session()->regenerate();

        LivewireAlert::title('Berhasil masuk')->text('Selamat datang di dasbor pengelolaan desa.')->success()->timer(1200)->show();

        $this->redirectRoute('admin.dashboard', navigate: true);
    }

    private function resetCaptcha(): void
    {
        $this->reset('captcha');
        $this->captchaVersion++;
    }
};
?>

@php
    $village = DB::table('villages')->orderBy('id')->first();
    $defaultLogo = asset('images/cms/ogan-ilir-logo.gif');
    $brandLogo = $village?->logo_url ?: $defaultLogo;
    $brandName = $village?->name ?? 'Kabupaten Ogan Ilir';
@endphp

<main class="relative min-h-screen overflow-hidden">
    <img src="{{ asset('images/cms/login-background.jpg') }}" alt="Hamparan sawah desa"
        class="absolute inset-0 h-full w-full object-cover">
    <div
        class="absolute inset-0 bg-[linear-gradient(90deg,rgba(2,44,34,0.92)_0%,rgba(2,44,34,0.78)_38%,rgba(2,44,34,0.34)_100%)]">
    </div>
    <div
        class="absolute inset-0 bg-[radial-gradient(circle_at_78%_18%,rgba(250,204,21,0.22),transparent_28%),linear-gradient(180deg,rgba(2,6,23,0.08),rgba(2,6,23,0.52))]">
    </div>

    <div
        class="relative grid min-h-screen items-center gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[minmax(0,1fr)_560px] lg:px-10 xl:px-16">
        <section class="flex min-h-[42vh] flex-col justify-between lg:min-h-[calc(100vh-4rem)]">
            <div class="flex items-center gap-4">
                <img src="{{ $defaultLogo }}" alt="Logo Kabupaten Ogan Ilir"
                    class="size-16 rounded-lg bg-white object-contain p-2 shadow-xl ring-1 ring-white/60">
                <div>
                    <div class="text-xs font-black uppercase tracking-[0.24em] text-emerald-100">Desa Ogan Ilir</div>
                    <div class="mt-1 text-lg font-black text-white">Kabupaten Ogan Ilir</div>
                </div>
            </div>

            <div class="max-w-3xl pb-4 pt-16 lg:pb-16">
                <div
                    class="inline-flex rounded-md border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-black uppercase tracking-[0.18em] text-emerald-50 backdrop-blur">
                    Panel Administrasi Website Desa
                </div>
                <h1 class="mt-5 max-w-2xl text-4xl font-black leading-[1.05] text-white sm:text-5xl lg:text-6xl">
                    Kelola informasi desa dengan tertib, cepat, dan terbuka.
                </h1>
                <p class="mt-5 max-w-xl text-base leading-8 text-emerald-50/85 sm:text-lg">
                    Satu ruang kerja untuk berita, halaman, widget, data desa, integrasi SIDESI, dan publikasi website
                    resmi desa.
                </p>
            </div>
        </section>

        <section class="w-full">
            <div
                class="rounded-lg border border-white/20 bg-white/95 p-5 text-zinc-950 shadow-2xl shadow-emerald-950/45 backdrop-blur sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Akses Desa Ogan Ilir</p>
                        <h2 class="mt-2 text-2xl font-black">Masuk Panel Admin</h2>
                        <p class="mt-2 text-sm leading-6 text-zinc-600">Gunakan akun pengelola yang sudah diberikan oleh
                            administrator desa.</p>
                    </div>
                    <img src="{{ $defaultLogo }}" alt=""
                        class="size-14 shrink-0 rounded-md bg-white object-contain p-1.5 shadow-sm ring-1 ring-zinc-200">
                </div>

                <form wire:submit="login" class="mt-6 space-y-4">
                    <div>
                        <label for="username" class="text-sm font-bold">Username</label>
                        <input id="username" type="text" wire:model="username"
                            class="mt-1 min-h-11 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/15"
                            autocomplete="username" placeholder="Masukkan username">
                        @error('username')
                            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="text-sm font-bold">Password</label>
                        <input id="password" type="password" wire:model="password"
                            class="mt-1 min-h-11 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/15"
                            autocomplete="current-password" placeholder="Masukkan password">
                        @error('password')
                            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label for="captcha" class="text-sm font-bold">Kode Keamanan</label>
                        <div class="mt-1 grid gap-3 sm:grid-cols-[250px_1fr]">
                            <button type="button" class="overflow-hidden rounded-md border border-zinc-300 bg-zinc-50"
                                onclick="this.querySelector('img').src='{{ captcha_src('flat') }}&'+Math.random()"
                                aria-label="Muat ulang captcha">
                                <img src="{{ captcha_src('flat') }}&v={{ $captchaVersion }}" alt="Captcha" class="h-12 w-full object-cover">
                            </button>
                            <input id="captcha" type="text" wire:model="captcha"
                                class="min-h-12 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/15"
                                maxlength="4" inputmode="text" placeholder="Masukkan 4 karakter">
                        </div>
                        @error('captcha')
                            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm font-semibold text-zinc-700">
                        <input type="checkbox" wire:model="remember"
                            class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-600">
                        Ingat saya
                    </label>

                    <button type="submit"
                        class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-black text-white shadow-lg shadow-emerald-900/20 transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70"
                        wire:loading.attr="disabled">
                        <i class="fa-solid fa-arrow-right-to-bracket" wire:loading.remove></i>
                        <span wire:loading.remove>Masuk Dasbor</span>
                        <span wire:loading>Memeriksa...</span>
                    </button>
                </form>
            </div>
        </section>
    </div>
</main>
</div>
