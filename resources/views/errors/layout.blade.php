<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('images/cms/ogan-ilir-logo.gif') }}">
    <title>@yield('code') - @yield('title') | CMS Desa Ogan Ilir</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-[#10241f] font-sans text-zinc-900 antialiased">
    <main class="relative isolate flex min-h-dvh items-center justify-center overflow-hidden px-4 py-10 sm:px-6 lg:px-8">
        <div aria-hidden="true" class="absolute inset-0 opacity-70">
            <div class="absolute -left-24 -top-28 size-[28rem] rounded-full border border-emerald-200/10"></div>
            <div class="absolute -left-10 -top-14 size-[22rem] rounded-full border border-emerald-200/10"></div>
            <div class="absolute left-8 top-8 size-[16rem] rounded-full border border-amber-300/10"></div>
            <div class="absolute -bottom-48 -right-32 size-[34rem] rounded-full border border-emerald-200/10"></div>
            <div class="absolute -bottom-32 -right-20 size-[27rem] rounded-full border border-emerald-200/10"></div>
            <div class="absolute -bottom-14 -right-8 size-[19rem] rounded-full border border-amber-300/10"></div>
        </div>

        <section class="relative w-full max-w-5xl overflow-hidden rounded-[1.75rem] border border-white/10 bg-[#f7faf4] shadow-2xl shadow-black/30">
            <div class="grid lg:min-h-[34rem] lg:grid-cols-[0.8fr_1.2fr]">
                <div class="relative flex min-h-64 items-center justify-center overflow-hidden bg-emerald-950 px-8 py-12 text-white lg:min-h-full">
                    <div aria-hidden="true" class="absolute inset-0 bg-[radial-gradient(circle_at_25%_20%,rgba(251,191,36,0.16),transparent_35%),linear-gradient(145deg,transparent,rgba(15,118,110,0.22))]"></div>
                    <div aria-hidden="true" class="absolute -right-16 top-1/2 size-64 -translate-y-1/2 rounded-full border border-white/10"></div>
                    <div aria-hidden="true" class="absolute -right-7 top-1/2 size-48 -translate-y-1/2 rounded-full border border-white/10"></div>

                    <div class="relative text-center lg:text-left">
                        <div class="mb-6 inline-flex items-center gap-3 rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-black uppercase tracking-[0.16em] text-emerald-100">
                            <img src="{{ asset('images/cms/ogan-ilir-logo.gif') }}" alt="" class="size-8 rounded-md bg-white object-contain p-1">
                            CMS Desa
                        </div>
                        <div class="text-[6.5rem] font-black leading-none tracking-[-0.08em] text-amber-300 sm:text-[8rem]" aria-label="Kode error @yield('code')">@yield('code')</div>
                        <div class="mt-4 flex items-center justify-center gap-3 text-xs font-bold uppercase tracking-[0.2em] text-emerald-100/70 lg:justify-start">
                            <span class="h-px w-10 bg-amber-300"></span>
                            Status sistem
                        </div>
                    </div>
                </div>

                <div class="flex items-center px-6 py-10 sm:px-12 lg:px-16">
                    <div class="max-w-xl">
                        <div class="mb-5 inline-flex size-12 items-center justify-center rounded-xl bg-amber-100 text-amber-800">
                            <i class="fa-solid @yield('icon') text-lg" aria-hidden="true"></i>
                        </div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">@yield('eyebrow', 'Akses CMS terhenti')</p>
                        <h1 class="mt-3 text-3xl font-black tracking-tight text-emerald-950 sm:text-4xl">@yield('title')</h1>
                        <p class="mt-4 max-w-lg text-base leading-7 text-zinc-600">@yield('message')</p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            @auth
                                <a href="{{ url('/admin') }}" class="admin-btn-primary inline-flex min-h-12 items-center justify-center gap-2 rounded-lg px-5 text-sm font-black text-white focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">
                                    <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                                    Kembali ke dasbor
                                </a>
                            @else
                                <a href="{{ url('/login') }}" class="admin-btn-primary inline-flex min-h-12 items-center justify-center gap-2 rounded-lg px-5 text-sm font-black text-white focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">
                                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                                    Masuk ke CMS
                                </a>
                            @endauth
                            <button type="button" onclick="history.length > 1 ? history.back() : window.location.href = '{{ url('/') }}'" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-emerald-950/15 bg-white px-5 text-sm font-black text-emerald-950 transition hover:border-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">
                                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                Halaman sebelumnya
                            </button>
                        </div>

                        <div class="mt-8 border-t border-emerald-950/10 pt-5 text-sm text-zinc-500">
                            @yield('hint', 'Jika masalah terus terjadi, muat ulang halaman atau hubungi pengelola sistem.')
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
