@php
    $loginVillage = \Illuminate\Support\Facades\DB::table('villages')->orderBy('id')->first();
    $defaultCmsLogo = asset('images/cms/ogan-ilir-logo.gif');
    $loginFavicon = $loginVillage?->favicon_url ?: $defaultCmsLogo;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ $loginFavicon }}">
    @if($loginVillage?->description)
        <meta name="description" content="{{ $loginVillage->description }}">
    @endif
    <title>Masuk CMS Desa - {{ $loginVillage?->name ?? 'Kabupaten Ogan Ilir' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-emerald-950 text-white antialiased">
    <livewire:auth.login />
    @livewireScripts
</body>
</html>
