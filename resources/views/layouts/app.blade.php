<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('titre', 'Kelasi') &middot; Kelasi</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1e3a8a">
    <link rel="apple-touch-icon" href="/icones/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Kelasi">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased">

<div class="min-h-full pb-20 md:pb-0">
    @include('composants.entete')

    <main class="mx-auto w-full max-w-6xl px-4 py-6">
        @if (session('succes'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('succes') }}
            </div>
        @endif

        @if (session('erreur'))
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ session('erreur') }}
            </div>
        @endif

        @yield('contenu')
        {{ $slot ?? '' }}
    </main>

    @include('composants.navigation-mobile')
</div>

@livewireScripts
</body>
</html>
