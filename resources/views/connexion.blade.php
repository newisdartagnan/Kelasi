<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Connexion &middot; Kelasi</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1e3a8a">
    <link rel="apple-touch-icon" href="/icones/apple-touch-icon.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-full place-items-center bg-slate-100 px-4 py-10">

<div class="w-full max-w-sm">
    <div class="mb-8 text-center">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-kelasi-600 text-2xl font-bold text-white">K</span>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight">Kelasi</h1>
        <p class="mt-1 text-sm text-slate-500">Le suivi des enseignements</p>
    </div>

    <form method="POST" action="{{ route('connexion') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        <div class="space-y-4">
            <div>
                <label for="matricule" class="block text-sm font-medium text-slate-700">Matricule</label>
                <input
                    id="matricule"
                    name="matricule"
                    type="text"
                    required
                    autofocus
                    autocapitalize="characters"
                    autocomplete="username"
                    value="{{ old('matricule') }}"
                    class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"
                >
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">Mot de passe</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"
                >
            </div>

            @error('matricule')
                <p class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $message }}</p>
            @enderror

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="memoriser" value="1" class="rounded border-slate-300 text-kelasi-600 focus:ring-kelasi-500">
                Rester connecté sur cet appareil
            </label>

            <button
                type="submit"
                class="w-full rounded-lg bg-kelasi-600 px-4 py-2.5 font-medium text-white transition hover:bg-kelasi-700 focus:outline-none focus:ring-2 focus:ring-kelasi-500 focus:ring-offset-2"
            >
                Se connecter
            </button>
        </div>
    </form>

    <p class="mt-6 text-center text-xs leading-relaxed text-slate-500">
        Votre compte est ouvert par le secrétariat académique à partir de votre matricule.
        Il n'y a pas d'inscription libre.
    </p>
</div>

</body>
</html>
