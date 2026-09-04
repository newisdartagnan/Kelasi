<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Kelasi' }}</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1e3a8a">
    <link rel="apple-touch-icon" href="/icones/apple-touch-icon.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="grid min-h-full place-items-center bg-slate-100 px-4 py-10">

<div class="w-full max-w-sm">
    <div class="mb-8 text-center">
        <a href="{{ route('connexion') }}" class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-kelasi-600 text-2xl font-bold text-white">K</a>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight">Kelasi</h1>
        <p class="mt-1 text-sm text-slate-500">Le suivi des enseignements</p>
    </div>

    {{ $slot }}
</div>

@livewireScripts
</body>
</html>
