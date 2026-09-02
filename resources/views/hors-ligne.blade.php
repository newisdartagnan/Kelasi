<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hors ligne &middot; Kelasi</title>
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-full place-items-center bg-slate-100 px-6 text-center">

<div class="max-w-sm">
    <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-300 text-2xl font-bold text-slate-600">K</span>
    <h1 class="mt-5 text-xl font-semibold">Pas de réseau</h1>
    <p class="mt-2 text-sm leading-relaxed text-slate-600">
        Cette page n'a pas encore ete consultée sur cet appareil, elle n'est donc pas disponible hors ligne.
    </p>
    <p class="mt-4 text-sm leading-relaxed text-slate-600">
        Les seances que vous avez saisies sont conservées sur l'appareil et remonteront d'elles-memes
        dès que la connexion reviendra. Vous pouvez fermer l'application sans rien perdre.
    </p>
    <button type="button" onclick="location.reload()"
            class="mt-6 rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-medium text-white">
        Réessayer
    </button>
</div>

</body>
</html>
