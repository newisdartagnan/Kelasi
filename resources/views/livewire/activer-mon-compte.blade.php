<div>
    @if (! $inscription)
        <form wire:submit="verifier" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Ouvrir mon compte</h2>
            <p class="mt-1 text-sm leading-relaxed text-slate-600">
                Saisissez le matricule que l'université vous a délivré. Nous vérifions
                qu'il figure sur la liste des inscrits de cette année.
            </p>

            <div class="mt-5">
                <label for="matricule" class="block text-sm font-medium text-slate-700">Matricule</label>
                <input
                    id="matricule"
                    type="text"
                    wire:model="matricule"
                    autofocus
                    autocapitalize="characters"
                    class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"
                >
                @error('matricule')
                    <p class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="mt-4 w-full rounded-lg bg-kelasi-600 px-4 py-2.5 font-medium text-white transition hover:bg-kelasi-700"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="verifier">Continuer</span>
                <span wire:loading wire:target="verifier">Vérification...</span>
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Vous avez déjà un compte ?
            <a href="{{ route('connexion') }}" class="font-medium text-kelasi-600 hover:underline">Se connecter</a>
        </p>
    @else
        <form wire:submit="activer" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            {{-- On montre qui l'on a reconnu avant de demander un mot de passe :
                 un homonyme ou un matricule mal recopié se voit ici. --}}
            <div class="rounded-xl bg-kelasi-50 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-kelasi-700">Inscription reconnue</p>
                <p class="mt-1 font-semibold">
                    {{ $inscription->nom }} {{ $inscription->postnom }} {{ $inscription->prenom }}
                </p>
                <p class="mt-0.5 text-sm text-slate-600">
                    {{ $inscription->matricule }}
                    @if ($inscription->promotion)
                        &middot; {{ $inscription->promotion->nom_complet }}
                    @endif
                </p>
                <p class="mt-1 text-sm text-slate-600">
                    {{ \App\Models\User::ROLES[$inscription->role_prevu] ?? $inscription->role_prevu }}
                </p>
            </div>

            <button
                type="button"
                wire:click="recommencer"
                class="mt-2 text-xs font-medium text-slate-500 hover:underline"
            >
                Ce n'est pas moi
            </button>

            <div class="mt-5 space-y-4">
                <div>
                    <label for="motDePasse" class="block text-sm font-medium text-slate-700">
                        Choisissez un mot de passe
                    </label>
                    <input
                        id="motDePasse"
                        type="password"
                        wire:model="motDePasse"
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"
                    >
                    @error('motDePasse') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="motDePasseConfirmation" class="block text-sm font-medium text-slate-700">
                        Répétez-le
                    </label>
                    <input
                        id="motDePasseConfirmation"
                        type="password"
                        wire:model="motDePasseConfirmation"
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"
                    >
                </div>

                <div>
                    <label for="telephone" class="block text-sm font-medium text-slate-700">
                        Téléphone <span class="font-normal text-slate-400">(facultatif)</span>
                    </label>
                    <input
                        id="telephone"
                        type="tel"
                        wire:model="telephone"
                        inputmode="tel"
                        class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"
                    >
                    @error('telephone') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-kelasi-600 px-4 py-2.5 font-medium text-white transition hover:bg-kelasi-700"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="activer">Ouvrir mon compte</span>
                    <span wire:loading wire:target="activer">Ouverture...</span>
                </button>
            </div>
        </form>
    @endif
</div>
