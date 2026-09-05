<form wire:submit="enregistrer" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold">Choisissez votre mot de passe</h2>
    <p class="mt-1 text-sm leading-relaxed text-slate-600">
        Celui qui vous a été remis est provisoire : la personne qui vous l'a donné le connaît.
        Choisissez-en un que vous êtes seul à savoir.
    </p>

    <div class="mt-5 space-y-4">
        <div>
            <label for="motDePasse" class="block text-sm font-medium text-slate-700">Nouveau mot de passe</label>
            <input id="motDePasse" type="password" wire:model="motDePasse" autocomplete="new-password" autofocus
                   class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
            @error('motDePasse') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="confirmation" class="block text-sm font-medium text-slate-700">Répétez-le</label>
            <input id="confirmation" type="password" wire:model="confirmation" autocomplete="new-password"
                   class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-kelasi-600 px-4 py-2.5 font-medium text-white transition hover:bg-kelasi-700">
            Enregistrer
        </button>
    </div>
</form>
