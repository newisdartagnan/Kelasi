<div>
    @if ($envoyee)
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
            <h2 class="text-lg font-semibold">Demande enregistrée</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Présentez-vous auprès de votre doyen ou du secrétariat académique :
                un mot de passe provisoire vous sera remis de la main à la main.
                Vous en choisirez un autre à votre première connexion.
            </p>
            <a href="{{ route('connexion') }}"
               class="mt-5 inline-block rounded-lg bg-kelasi-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-kelasi-700">
                Retour à la connexion
            </a>
        </div>
    @else
        <form wire:submit="envoyer" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Mot de passe oublié</h2>
            <p class="mt-1 text-sm leading-relaxed text-slate-600">
                Aucun lien n'est envoyé par courriel. Votre demande remonte à l'autorité
                de votre faculté, qui vous remettra un mot de passe provisoire.
            </p>

            <div class="mt-5 space-y-4">
                <div>
                    <label for="matricule" class="block text-sm font-medium text-slate-700">Matricule</label>
                    <input id="matricule" type="text" wire:model="matricule" autofocus autocapitalize="characters"
                           class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                    @error('matricule') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="motif" class="block text-sm font-medium text-slate-700">
                        Précisions <span class="font-normal text-slate-400">(facultatif)</span>
                    </label>
                    <input id="motif" type="text" wire:model="motif" placeholder="Téléphone perdu, mot de passe oublié..."
                           class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                </div>

                <button type="submit"
                        class="w-full rounded-lg bg-kelasi-600 px-4 py-2.5 font-medium text-white transition hover:bg-kelasi-700">
                    Envoyer la demande
                </button>
            </div>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            <a href="{{ route('connexion') }}" class="font-medium text-kelasi-600 hover:underline">Retour à la connexion</a>
        </p>
    @endif
</div>
